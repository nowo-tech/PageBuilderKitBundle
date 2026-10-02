<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepositoryInterface;

use function array_key_exists;
use function is_array;
use function is_string;
use function preg_match;
use function sprintf;
use function strtolower;
use function trim;

final readonly class PageTemplateService
{
    public const int FORMAT_VERSION = 1;

    public const string KIND_SINGLE = 'page_builder_template';

    public const string KIND_BUNDLE = 'page_builder_templates';

    public function __construct(
        private BuilderPageTemplateRepositoryInterface $templateRepository,
        private EntityManagerInterface $entityManager,
        private DocumentService $documentService,
        private DocumentNormalizer $documentNormalizer,
        private ContentFieldsNormalizer $contentFieldsNormalizer = new ContentFieldsNormalizer(),
    ) {
    }

    /**
     * @return list<BuilderPageTemplate>
     */
    public function list(): array
    {
        return $this->templateRepository->findAllOrdered();
    }

    public function findByKey(string $templateKey): ?BuilderPageTemplate
    {
        return $this->templateRepository->findOneByTemplateKey($templateKey);
    }

    public function saveFromPage(BuilderPage $page, string $templateKey, string $label): BuilderPageTemplate
    {
        $templateKey = strtolower(trim($templateKey));
        if ($templateKey === '' || !preg_match('/^[a-z0-9_-]+$/', $templateKey)) {
            throw new InvalidArgumentException('Invalid template key.');
        }

        $label = trim($label);
        if ($label === '') {
            throw new InvalidArgumentException('Template label is required.');
        }

        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            throw new InvalidArgumentException(sprintf('Page "%s" has no document.', $page->getPageKey()));
        }

        $structure = $this->documentNormalizer->normalize($document->getStructure());
        // Template-only metadata (stripped by DocumentNormalizer when applied to a live page).
        $structure['templateSeoByLocale'] = $this->snapshotSeoByLocale($page);
        $props                            = [];
        foreach ($document->getLocales() as $localeDocument) {
            $props[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            $template = (new BuilderPageTemplate())->setTemplateKey($templateKey);
        }

        $template
            ->setLabel($label)
            ->setStructure($structure)
            ->setWidgetPropsByLocale($props);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $template;
    }

    /**
     * @param array{
     *     include_field_schema?: bool,
     *     include_field_values?: bool,
     *     include_seo?: bool
     * } $options
     */
    public function createPageFromTemplate(
        string $templateKey,
        string $pageKey,
        string $title,
        string $locale,
        array $options = [],
    ): BuilderPage {
        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            throw new InvalidArgumentException(sprintf('Unknown template "%s".', $templateKey));
        }

        $includeFieldSchema = ($options['include_field_schema'] ?? true) !== false;
        $includeFieldValues = ($options['include_field_values'] ?? false) === true;
        $includeSeo         = ($options['include_seo'] ?? false) === true;

        $page = $this->documentService->createPage($pageKey, $title, $locale);

        /** @var array<string, array<string, mixed>> $props */
        $props = [];
        foreach ($template->getWidgetPropsByLocale() as $loc => $raw) {
            if (is_array($raw)) {
                /* @var array<string, mixed> $raw */
                $props[(string) $loc] = $raw;
            }
        }

        $rawStructure = $template->getStructure();
        $seoByLocale  = is_array($rawStructure['templateSeoByLocale'] ?? null)
            ? $rawStructure['templateSeoByLocale']
            : [];

        $structure = $this->documentNormalizer->normalize($rawStructure);
        $structure = $this->contentFieldsNormalizer->applyTemplateFieldOptions(
            $structure,
            $includeFieldSchema,
            $includeFieldValues,
        );

        $this->documentService->saveDocument($page, $structure, $props);

        if ($includeSeo && $seoByLocale !== []) {
            $this->applySeoByLocale($page, $seoByLocale, $locale, $title, $pageKey);
        }

        return $page;
    }

    public function delete(string $templateKey): void
    {
        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            throw new InvalidArgumentException(sprintf('Unknown template "%s".', $templateKey));
        }

        $this->entityManager->remove($template);
        $this->entityManager->flush();
    }

    /**
     * Portable JSON for sharing a template across projects.
     *
     * @return array{
     *     formatVersion: int,
     *     kind: string,
     *     templateKey: string,
     *     label: string,
     *     structure: array<string, mixed>,
     *     widgetPropsByLocale: array<string, mixed>
     * }
     */
    public function export(string $templateKey): array
    {
        $template = $this->templateRepository->findOneByTemplateKey($templateKey);
        if (!$template instanceof BuilderPageTemplate) {
            throw new InvalidArgumentException(sprintf('Unknown template "%s".', $templateKey));
        }

        return $this->exportTemplate($template);
    }

    /**
     * @return array{
     *     formatVersion: int,
     *     kind: string,
     *     templates: list<array{
     *         templateKey: string,
     *         label: string,
     *         structure: array<string, mixed>,
     *         widgetPropsByLocale: array<string, mixed>
     *     }>
     * }
     */
    public function exportAll(): array
    {
        $templates = [];
        foreach ($this->templateRepository->findAllOrdered() as $template) {
            $row = $this->exportTemplate($template);
            unset($row['formatVersion'], $row['kind']);
            $templates[] = $row;
        }

        return [
            'formatVersion' => self::FORMAT_VERSION,
            'kind'          => self::KIND_BUNDLE,
            'templates'     => $templates,
        ];
    }

    /**
     * Import a single template or a bundle exported by {@see export()} / {@see exportAll()}.
     *
     * @param array<string, mixed> $payload
     *
     * @return list<string> Imported template keys
     */
    public function import(array $payload, bool $overwrite = true): array
    {
        $format = (int) ($payload['formatVersion'] ?? 0);
        if ($format !== self::FORMAT_VERSION) {
            throw new InvalidArgumentException('Unsupported template formatVersion.');
        }

        $kind = is_string($payload['kind'] ?? null) ? $payload['kind'] : '';
        $keys = [];

        if ($kind === self::KIND_BUNDLE) {
            $rows = $payload['templates'] ?? null;
            if (!is_array($rows)) {
                throw new InvalidArgumentException('templates must be a list.');
            }
            foreach ($rows as $row) {
                if (!is_array($row)) {
                    continue;
                }
                /* @var array<string, mixed> $row */
                $keys[] = $this->importOne($row, $overwrite);
            }

            return $keys;
        }

        if ($kind !== self::KIND_SINGLE && $kind !== '') {
            throw new InvalidArgumentException(sprintf('Unsupported template kind "%s".', $kind));
        }

        $keys[] = $this->importOne($payload, $overwrite);

        return $keys;
    }

    /**
     * @return array{
     *     formatVersion: int,
     *     kind: string,
     *     templateKey: string,
     *     label: string,
     *     structure: array<string, mixed>,
     *     widgetPropsByLocale: array<string, mixed>
     * }
     */
    private function exportTemplate(BuilderPageTemplate $template): array
    {
        $raw       = $template->getStructure();
        $structure = $this->documentNormalizer->normalize($raw);
        $seo       = is_array($raw['templateSeoByLocale'] ?? null) ? $raw['templateSeoByLocale'] : null;
        if ($seo !== null) {
            $structure['templateSeoByLocale'] = $seo;
        }

        return [
            'formatVersion'       => self::FORMAT_VERSION,
            'kind'                => self::KIND_SINGLE,
            'templateKey'         => $template->getTemplateKey(),
            'label'               => $template->getLabel(),
            'structure'           => $structure,
            'widgetPropsByLocale' => $template->getWidgetPropsByLocale(),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function importOne(array $payload, bool $overwrite): string
    {
        $templateKey = is_string($payload['templateKey'] ?? null)
            ? strtolower(trim($payload['templateKey']))
            : '';
        if ($templateKey === '' || !preg_match('/^[a-z0-9_-]+$/', $templateKey)) {
            throw new InvalidArgumentException('Invalid template key.');
        }

        $label = is_string($payload['label'] ?? null) ? trim($payload['label']) : '';
        if ($label === '') {
            $label = $templateKey;
        }

        $structure = $payload['structure'] ?? null;
        if (!is_array($structure)) {
            throw new InvalidArgumentException('structure must be an object.');
        }

        /** @var array<string, mixed> $structure */
        $seoByLocale = is_array($structure['templateSeoByLocale'] ?? null)
            ? $structure['templateSeoByLocale']
            : null;
        $structure = $this->documentNormalizer->normalize($structure);
        if ($seoByLocale !== null) {
            $structure['templateSeoByLocale'] = $seoByLocale;
        }

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

        $existing = $this->templateRepository->findOneByTemplateKey($templateKey);
        if ($existing instanceof BuilderPageTemplate && !$overwrite) {
            throw new InvalidArgumentException(sprintf('Template "%s" already exists.', $templateKey));
        }

        $template = $existing instanceof BuilderPageTemplate
            ? $existing
            : (new BuilderPageTemplate())->setTemplateKey($templateKey);

        $template
            ->setLabel($label)
            ->setStructure($structure)
            ->setWidgetPropsByLocale($props);

        $this->entityManager->persist($template);
        $this->entityManager->flush();

        return $templateKey;
    }

    /**
     * @return array<string, array<string, string|null>>
     */
    private function snapshotSeoByLocale(BuilderPage $page): array
    {
        $out = [];
        foreach ($page->getTranslations() as $translation) {
            if (!$translation instanceof BuilderPageTranslation) {
                continue;
            }
            $out[$translation->getLocale()] = [
                'metaTitle'       => $translation->getMetaTitle(),
                'metaDescription' => $translation->getMetaDescription(),
                'ogTitle'         => $translation->getOgTitle(),
                'ogDescription'   => $translation->getOgDescription(),
                'ogImage'         => $translation->getOgImage(),
                'canonicalUrl'    => $translation->getCanonicalUrl(),
                'robots'          => $translation->getRobots(),
            ];
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $seoByLocale
     */
    private function applySeoByLocale(
        BuilderPage $page,
        array $seoByLocale,
        string $primaryLocale,
        string $title,
        string $pageKey,
    ): void {
        unset($primaryLocale);
        foreach ($seoByLocale as $locale => $row) {
            if (!is_string($locale) || $locale === '' || !is_array($row)) {
                continue;
            }

            $translation = $page->getTranslation($locale);
            if (!$translation instanceof BuilderPageTranslation) {
                $translation = (new BuilderPageTranslation())
                    ->setLocale($locale)
                    ->setTitle($title)
                    ->setSlug($pageKey);
                $page->addTranslation($translation);
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
                if (!array_key_exists($field, $row)) {
                    continue;
                }
                $value = $row[$field];
                if (is_string($value) || $value === null) {
                    $translation->{$setter}($value);
                }
            }
        }

        $this->entityManager->flush();
    }
}
