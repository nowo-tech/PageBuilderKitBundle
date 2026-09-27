<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;

use function array_key_exists;
use function array_values;
use function in_array;
use function is_array;
use function is_string;
use function sprintf;

/**
 * Portable JSON export/import of a page document + translations (not revisions).
 */
final readonly class DocumentImportExportService
{
    public const int FORMAT_VERSION = 1;

    public function __construct(
        private DocumentService $documentService,
        private DocumentNormalizer $documentNormalizer,
        private BuilderLocales $builderLocales,
    ) {
    }

    /**
     * @return array{
     *     formatVersion: int,
     *     pageKey: string,
     *     status: string,
     *     structure: array<string, mixed>,
     *     widgetPropsByLocale: array<string, mixed>,
     *     translations: list<array<string, mixed>>
     * }
     */
    public function export(BuilderPage $page): array
    {
        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            throw new InvalidArgumentException(sprintf('Page "%s" has no document.', $page->getPageKey()));
        }

        $props = [];
        foreach ($document->getLocales() as $localeDocument) {
            $props[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        $translations = [];
        foreach ($page->getTranslations() as $translation) {
            $translations[] = [
                'locale'          => $translation->getLocale(),
                'title'           => $translation->getTitle(),
                'slug'            => $translation->getSlug(),
                'metaTitle'       => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'ogTitle'         => $translation->getOgTitle(),
                'ogDescription'   => $translation->getOgDescription(),
                'ogImage'         => $translation->getOgImage(),
                'canonicalUrl'    => $translation->getCanonicalUrl(),
                'robots'          => $translation->getRobots(),
            ];
        }

        return [
            'formatVersion'       => self::FORMAT_VERSION,
            'pageKey'             => $page->getPageKey(),
            'status'              => $page->getStatus()->value,
            'structure'           => $this->documentNormalizer->normalize($document->getStructure()),
            'widgetPropsByLocale' => $props,
            'translations'        => $translations,
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function import(array $payload, ?string $targetPageKey = null, bool $publish = false): BuilderPage
    {
        $format = (int) ($payload['formatVersion'] ?? 0);
        if ($format !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('Unsupported import formatVersion.');
        }

        $pageKey = $targetPageKey ?? (is_string($payload['pageKey'] ?? null) ? $payload['pageKey'] : '');
        if ($pageKey === '') {
            throw new InvalidArgumentException('pageKey is required.');
        }

        $structure = $payload['structure'] ?? null;
        if (!is_array($structure)) {
            throw new InvalidArgumentException('structure must be an object.');
        }

        /** @var array<string, mixed> $structure */
        $widgetProps = $payload['widgetPropsByLocale'] ?? [];
        if (!is_array($widgetProps)) {
            throw new InvalidArgumentException('widgetPropsByLocale must be an object.');
        }

        /** @var array<string, array<string, mixed>> $props */
        $props = [];
        foreach ($widgetProps as $locale => $raw) {
            if (is_array($raw)) {
                /* @var array<string, mixed> $raw */
                $props[(string) $locale] = $raw;
            }
        }

        $title        = $pageKey;
        $locale       = $this->builderLocales->getDefault();
        $translations = is_array($payload['translations'] ?? null) ? $payload['translations'] : [];
        foreach ($translations as $row) {
            if (!is_array($row)) {
                continue;
            }
            if (($row['locale'] ?? null) === $locale && is_string($row['title'] ?? null) && $row['title'] !== '') {
                $title = $row['title'];
            }
        }

        $existing = $this->documentService->loadPageByKey($pageKey);
        if ($existing instanceof BuilderPage) {
            $page = $existing;
        } else {
            $page = $this->documentService->createPage($pageKey, $title, $locale);
        }

        $this->documentService->saveDocument($page, $structure, $props);
        $this->applyTranslations($page, array_values($translations));
        $this->documentService->flushPage($page);

        if ($publish) {
            $this->documentService->publish($page);
        }

        return $page;
    }

    /**
     * @param list<mixed> $translations
     */
    private function applyTranslations(BuilderPage $page, array $translations): void
    {
        foreach ($translations as $row) {
            if (!is_array($row) || !is_string($row['locale'] ?? null)) {
                continue;
            }

            $locale = $row['locale'];
            if (!in_array($locale, $this->builderLocales->getAll(), true)) {
                continue;
            }

            $translation = $page->getTranslation($locale);
            if (!$translation instanceof BuilderPageTranslation) {
                $translation = (new BuilderPageTranslation())->setLocale($locale);
                $page->addTranslation($translation);
            }

            if (is_string($row['title'] ?? null)) {
                $translation->setTitle($row['title']);
            }
            if (is_string($row['slug'] ?? null)) {
                $translation->setSlug($row['slug']);
            }
            foreach ([
                'metaTitle'       => 'setMetaTitle',
                'metaDescription' => 'setMetaDescription',
                'ogTitle'         => 'setOgTitle',
                'ogDescription'   => 'setOgDescription',
                'ogImage'         => 'setOgImage',
                'canonicalUrl'    => 'setCanonicalUrl',
                'robots'          => 'setRobots',
            ] as $field => $setter) {
                if (array_key_exists($field, $row) && (is_string($row[$field]) || $row[$field] === null)) {
                    $value = $row[$field];
                    $translation->{$setter}($value);
                }
            }
        }
    }
}
