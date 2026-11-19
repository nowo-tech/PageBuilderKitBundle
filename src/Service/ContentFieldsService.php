<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\ContentFieldType;
use Nowo\PageBuilderKitBundle\Event\PageDocumentSavedEvent;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;

use function array_filter;
use function array_key_exists;
use function array_merge;
use function array_values;
use function is_array;
use function is_string;
use function sprintf;
use function trim;

/**
 * Persist content-field schema and locale values without touching Grapes layout HTML.
 */
final readonly class ContentFieldsService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private DocumentNormalizer $documentNormalizer,
        private ContentFieldsNormalizer $contentFieldsNormalizer,
        private PageBuilderProtection $protection,
        private ?PageRevisionStore $pageRevisionStore = null,
        private ?PageBuilderTransaction $transaction = null,
    ) {
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    public function saveSchema(BuilderPage $page, array $fields): void
    {
        $this->unit($page, fn () => $this->doSaveSchema($page, $fields));
    }

    /**
     * @param array<string, array<string, mixed>> $fieldValues locale => key => value
     * @param list<array{key: string, type: string, label: string, labels?: array<string, string>, ...}>|null $fields optional schema override (e.g. updated labels)
     */
    public function saveValues(BuilderPage $page, array $fieldValues, ?array $fields = null): void
    {
        $this->unit($page, fn () => $this->doSaveValues($page, $fieldValues, $fields));
    }

    /**
     * Merges an editor's posted values onto the values stored under the page lock (1.17, REQ-RUNTIME-003 rule 4): the
     * content form of one locale never writes back other locales or fields it read before another editor's save.
     *
     * - `$postedByLocale`: locale => key => value, only the locales and keys the editor submitted. A `bool` field of
     *   the stored schema missing from a posted locale is saved as false (unchecked checkboxes are not posted).
     * - `$labels`: key => label for `$labelLocale` (label edits of the form); unknown keys and blank labels are ignored.
     *
     * @param array<string, array<string, mixed>> $postedByLocale
     * @param array<string, string> $labels
     */
    public function mergeValues(BuilderPage $page, array $postedByLocale, string $labelLocale = '', array $labels = []): void
    {
        $this->unit($page, function () use ($page, $postedByLocale, $labelLocale, $labels): void {
            $structure = $this->documentNormalizer->normalize($this->requireDocument($page)->getStructure());
            $schema    = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);
            $values    = $this->contentFieldsNormalizer->normalizeValues($structure['fieldValues'] ?? [], $schema);

            foreach ($postedByLocale as $locale => $bag) {
                foreach ($schema as $field) {
                    if (($field['type'] ?? '') === ContentFieldType::Bool->value && !array_key_exists($field['key'], $bag)) {
                        $bag[$field['key']] = '0';
                    }
                }
                $values[$locale] = array_merge($values[$locale] ?? [], $bag);
            }

            $labelsChanged = false;
            foreach ($schema as $index => $field) {
                $label = trim($labels[$field['key']] ?? '');
                if ($labelLocale === '' || $label === '') {
                    continue;
                }
                $schema[$index] = $this->contentFieldsNormalizer->mergeLabels($field, ['labels' => [$labelLocale => $label], 'label' => $label], $labelLocale);
                $labelsChanged  = true;
            }

            $this->doSaveValues($page, $values, $labelsChanged ? $schema : null);
        });
    }

    /**
     * Appends one field definition to the schema stored under the page lock (1.17): a field another editor added in
     * the meantime is kept.
     *
     * @param array<string, mixed> $field
     */
    public function saveSchemaWithField(BuilderPage $page, array $field): void
    {
        $this->unit($page, function () use ($page, $field): void {
            $structure = $this->documentNormalizer->normalize($this->requireDocument($page)->getStructure());
            $schema    = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);
            $schema[]  = $field;
            $this->doSaveSchema($page, $schema);
        });
    }

    /**
     * Removes one field from the schema stored under the page lock (1.17): the other fields stay as committed.
     */
    public function saveSchemaWithoutField(BuilderPage $page, string $key): void
    {
        $this->unit($page, function () use ($page, $key): void {
            $structure = $this->documentNormalizer->normalize($this->requireDocument($page)->getStructure());
            $schema    = array_values(array_filter(
                $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []),
                static fn (array $field): bool => $field['key'] !== $key,
            ));
            $this->doSaveSchema($page, $schema);
        });
    }

    /**
     * Upsert one field definition (from Twig component) and save a locale value.
     *
     * Read-modify-write of the page structure: under the page lock (1.17), so two editors saving different fields
     * at once both keep their value.
     *
     * @param array{type?: string, label?: string, labels?: array<string, string>, required?: bool, options?: list<string>, default?: mixed}|null $definition
     */
    public function saveFieldValue(
        BuilderPage $page,
        string $key,
        string $locale,
        mixed $value,
        ?array $definition = null,
    ): void {
        $this->unit($page, fn () => $this->doSaveFieldValue($page, $key, $locale, $value, $definition));
    }

    /**
     * @param list<array<string, mixed>> $fields
     */
    private function doSaveSchema(BuilderPage $page, array $fields): void
    {
        $document  = $this->requireDocument($page);
        $structure = $this->documentNormalizer->normalize($document->getStructure());

        if ($this->pageRevisionStore?->isOnSave() === true) {
            $this->pageRevisionStore->snapshot($page, null, skipIfUnchanged: true);
        }

        $schema    = $this->contentFieldsNormalizer->normalizeSchema($fields);
        $structure = $this->contentFieldsNormalizer->applyToStructure($structure, $schema);
        // @igor-ignore - Doctrine entity mutation via EM; request-scoped, flushed immediately.
        $document->setStructure($structure);
        $this->entityManager->persist($page);
        $this->commit(new PageDocumentSavedEvent($page->getPageKey(), $page->getUuid(), PageDocumentSavedEvent::CHANGE_CONTENT_FIELDS));
    }

    /**
     * @param array<string, array<string, mixed>> $fieldValues locale => key => value
     * @param list<array{key: string, type: string, label: string, labels?: array<string, string>, ...}>|null $fields
     */
    private function doSaveValues(BuilderPage $page, array $fieldValues, ?array $fields): void
    {
        $document  = $this->requireDocument($page);
        $structure = $this->documentNormalizer->normalize($document->getStructure());
        $schema    = $fields !== null
            ? $this->contentFieldsNormalizer->normalizeSchema($fields)
            : $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);

        if ($schema === []) {
            throw new InvalidArgumentException('No content fields defined for this page.');
        }

        if ($this->pageRevisionStore?->isOnSave() === true) {
            $this->pageRevisionStore->snapshot($page, null, skipIfUnchanged: true);
        }

        $normalized = $this->contentFieldsNormalizer->normalizeValues($fieldValues, $schema);
        $normalized = $this->sanitizeHtmlTypedValues($normalized, $schema);

        $structure = $this->contentFieldsNormalizer->applyToStructure($structure, $schema, $normalized);
        // @igor-ignore - Doctrine entity mutation via EM; request-scoped, flushed immediately.
        $document->setStructure($structure);
        $this->entityManager->persist($page);
        $this->commit(new PageDocumentSavedEvent($page->getPageKey(), $page->getUuid(), PageDocumentSavedEvent::CHANGE_CONTENT_FIELDS));
    }

    /**
     * @param array{type?: string, label?: string, labels?: array<string, string>, required?: bool, options?: list<string>, default?: mixed}|null $definition
     */
    private function doSaveFieldValue(BuilderPage $page, string $key, string $locale, mixed $value, ?array $definition): void
    {
        $document  = $this->requireDocument($page);
        $structure = $this->documentNormalizer->normalize($document->getStructure());
        $schema    = $this->contentFieldsNormalizer->normalizeSchema($structure['fields'] ?? []);

        $found = false;
        foreach ($schema as $index => $field) {
            if ($field['key'] !== $key) {
                continue;
            }
            $found = true;
            if ($definition !== null) {
                $schema[$index] = $this->contentFieldsNormalizer->mergeLabels($field, $definition, $locale);
            }
            break;
        }

        if (!$found) {
            if ($definition === null) {
                throw new InvalidArgumentException(sprintf('Unknown content field "%s".', $key));
            }
            $created = [
                'key'      => $key,
                'type'     => $definition['type'] ?? ContentFieldType::String->value,
                'label'    => $definition['label'] ?? $key,
                'labels'   => is_array($definition['labels'] ?? null) ? $definition['labels'] : [],
                'required' => (bool) ($definition['required'] ?? false),
                'options'  => $definition['options'] ?? [],
                'default'  => $definition['default'] ?? null,
            ];
            $created = $this->contentFieldsNormalizer->mergeLabels(
                $this->contentFieldsNormalizer->normalizeSchema([$created])[0],
                $definition,
                $locale,
            );
            $schema[] = $created;
            $schema   = $this->contentFieldsNormalizer->normalizeSchema($schema);
        }

        if ($this->pageRevisionStore?->isOnSave() === true) {
            $this->pageRevisionStore->snapshot($page, null, skipIfUnchanged: true);
        }

        $values = $this->contentFieldsNormalizer->normalizeValues($structure['fieldValues'] ?? [], $schema);
        $values[$locale] ??= [];
        $values[$locale][$key] = $value;
        $normalized            = $this->contentFieldsNormalizer->normalizeValues($values, $schema);
        $normalized            = $this->sanitizeHtmlTypedValues($normalized, $schema);

        $structure = $this->contentFieldsNormalizer->applyToStructure($structure, $schema, $normalized);
        // @igor-ignore - Doctrine entity mutation via EM; request-scoped, flushed immediately.
        $document->setStructure($structure);
        $this->entityManager->persist($page);
        $this->commit(new PageDocumentSavedEvent($page->getPageKey(), $page->getUuid(), PageDocumentSavedEvent::CHANGE_CONTENT_FIELDS));
    }

    /**
     * @param array<string, array<string, mixed>> $values
     * @param list<array<string, mixed>> $schema
     *
     * @return array<string, array<string, mixed>>
     */
    private function sanitizeHtmlTypedValues(array $values, array $schema): array
    {
        foreach ($values as $locale => $bag) {
            if (!is_array($bag)) {
                continue;
            }
            foreach ($schema as $field) {
                $key = $field['key'] ?? null;
                if (!is_string($key) || !array_key_exists($key, $bag)) {
                    continue;
                }
                $values[$locale][$key] = $this->sanitizeFieldValue($field, $bag[$key]);
            }
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $field
     */
    private function sanitizeFieldValue(array $field, mixed $value): mixed
    {
        $typeName = is_string($field['type'] ?? null) ? $field['type'] : '';
        $type     = ContentFieldType::tryFrom($typeName);
        if (!$type instanceof ContentFieldType) {
            return $value;
        }

        if ($type->sanitizeOnPersist() && is_string($value)) {
            return $this->protection->htmlSanitizer()->sanitize($value);
        }

        if ($type === ContentFieldType::Repeater && is_array($value)) {
            $subfields = is_array($field['fields'] ?? null) ? $field['fields'] : [];
            $rows      = [];
            foreach ($value as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $clean = $row;
                foreach ($subfields as $sub) {
                    if (!is_array($sub) || !isset($sub['key'], $row[$sub['key']])) {
                        continue;
                    }
                    $clean[$sub['key']] = $this->sanitizeFieldValue($sub, $row[$sub['key']]);
                }
                $rows[] = $clean;
            }

            return $rows;
        }

        if ($type === ContentFieldType::Group && is_array($value)) {
            $subfields = is_array($field['fields'] ?? null) ? $field['fields'] : [];
            $clean     = $value;
            foreach ($subfields as $sub) {
                if (!is_array($sub) || !isset($sub['key'], $value[$sub['key']])) {
                    continue;
                }
                $clean[$sub['key']] = $this->sanitizeFieldValue($sub, $value[$sub['key']]);
            }

            return $clean;
        }

        return $value;
    }

    private function requireDocument(BuilderPage $page): BuilderDocument
    {
        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            throw new InvalidArgumentException(sprintf('Page "%s" has no document.', $page->getPageKey()));
        }

        return $document;
    }

    /**
     * One write unit (1.17, REQ-RUNTIME-003 rules 1 and 4): the page's document is locked and read again first, so the
     * structure this save changes is the last committed one.
     *
     * @param callable(): void $operation
     */
    private function unit(BuilderPage $page, callable $operation): void
    {
        if (!$this->transaction instanceof PageBuilderTransaction) {
            $operation();

            return;
        }

        // @igor-ignore - Write-unit closure: run once, synchronously, by the runner; never stored.
        $this->transaction->run(function () use ($page, $operation): void {
            $this->transaction?->lockPage($page);
            $operation();
        });
    }

    /**
     * Flushes the bundle entity manager (unless the host controls the transaction) and records the write events.
     */
    private function commit(object ...$events): void
    {
        if ($this->transaction instanceof PageBuilderTransaction) {
            $this->transaction->commit(...$events);

            return;
        }

        $this->entityManager->flush();
    }
}
