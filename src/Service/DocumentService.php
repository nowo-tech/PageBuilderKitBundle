<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Symfony\Component\Uid\Uuid;

use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

final readonly class DocumentService
{
    public function __construct(
        private BuilderPageRepositoryInterface $pageRepository,
        private EntityManagerInterface $entityManager,
        private BuilderLocales $builderLocales,
        private DocumentNormalizer $documentNormalizer,
        private WidgetTypeRegistry $widgetTypeRegistry,
        private PageBuilderProtection $protection,
        private WidgetPropsMerger $widgetPropsMerger,
        private GrapesDocumentSanitizer $grapesDocumentSanitizer = new GrapesDocumentSanitizer(),
        private ?PageRevisionStore $pageRevisionStore = null,
        private ?ContentFieldsNormalizer $contentFieldsNormalizer = null,
    ) {
    }

    public function loadPageByKey(string $pageKey): ?BuilderPage
    {
        return $this->pageRepository->findOneByPageKey($pageKey);
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, array<string, mixed>> $widgetPropsByLocale
     */
    public function saveDocument(BuilderPage $page, array $structure, array $widgetPropsByLocale): void
    {
        $normalizedStructure = $this->documentNormalizer->normalize($structure);
        if ($this->documentNormalizer->isGrapesStructure($normalizedStructure)) {
            $normalizedStructure = $this->grapesDocumentSanitizer->sanitizeStructure($normalizedStructure);
            $normalizedStructure = $this->sanitizeGrapesLocaleContent($normalizedStructure);
        }
        $this->validateStructure($normalizedStructure);

        if ($this->pageRevisionStore?->isOnSave() === true) {
            $this->pageRevisionStore->snapshot($page, null, skipIfUnchanged: true);
        }

        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            $document = new BuilderDocument();
            $document->setPage($page);
        }

        $document->setStructure($normalizedStructure);

        foreach ($this->builderLocales->getAll() as $locale) {
            if ($this->documentNormalizer->isGrapesStructure($normalizedStructure)) {
                $document->upsertLocale($locale, []);
                continue;
            }

            $rawProps = $widgetPropsByLocale[$locale] ?? [];
            if (!is_array($rawProps)) {
                $rawProps = [];
            }

            $sanitized = $this->sanitizeWidgetPropsByLocale($normalizedStructure, $rawProps);
            $document->upsertLocale($locale, $sanitized);
        }

        $this->entityManager->persist($page);
        $this->entityManager->flush();
    }

    public function createPage(string $pageKey, string $title, string $locale): BuilderPage
    {
        $pageKey = strtolower(trim($pageKey));
        if ($pageKey === '' || !preg_match('/^[a-z0-9_-]+$/', $pageKey)) {
            throw new InvalidArgumentException('Invalid page key.');
        }

        if (!in_array($locale, $this->builderLocales->getAll(), true)) {
            $locale = $this->builderLocales->getDefault();
        }

        if ($this->pageRepository->findOneByPageKey($pageKey) instanceof BuilderPage) {
            throw new InvalidArgumentException(sprintf('Page key "%s" already exists.', $pageKey));
        }

        $page = (new BuilderPage())
            ->setUuid(Uuid::v4()->toRfc4122())
            ->setPageKey($pageKey)
            ->setStatus(PageStatus::Draft);

        $translation = (new BuilderPageTranslation())
            ->setLocale($locale)
            ->setTitle($title)
            ->setSlug($pageKey);
        $page->addTranslation($translation);

        $document = (new BuilderDocument())
            ->setPage($page)
            ->setStructure($this->documentNormalizer->emptyStructure());

        foreach ($this->builderLocales->getAll() as $configuredLocale) {
            $document->upsertLocale($configuredLocale, []);
        }

        $page->setDocument($document);

        $this->entityManager->persist($page);
        $this->entityManager->flush();

        return $page;
    }

    /**
     * Clone structure, locale props, and translations into a new draft page.
     */
    public function duplicatePage(BuilderPage $source, string $newPageKey, ?string $title = null): BuilderPage
    {
        $sourceDocument = $source->getDocument();
        if (!$sourceDocument instanceof BuilderDocument) {
            throw new InvalidArgumentException(sprintf('Page "%s" has no document.', $source->getPageKey()));
        }

        $defaultLocale    = $this->builderLocales->getDefault();
        $firstTranslation = $source->getTranslations()->first();
        $sourceTitle      = $source->getTranslation($defaultLocale)?->getTitle()
            ?? ($firstTranslation instanceof BuilderPageTranslation ? $firstTranslation->getTitle() : null)
            ?? $source->getPageKey();
        $newTitle = $title !== null && trim($title) !== '' ? trim($title) : $sourceTitle . ' (copy)';

        $page = $this->createPage($newPageKey, $newTitle, $defaultLocale);

        $props = [];
        foreach ($sourceDocument->getLocales() as $localeDocument) {
            $props[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        $this->saveDocument($page, $sourceDocument->getStructure(), $props);

        foreach ($source->getTranslations() as $sourceTranslation) {
            $locale = $sourceTranslation->getLocale();
            if (!in_array($locale, $this->builderLocales->getAll(), true)) {
                continue;
            }

            $translation = $page->getTranslation($locale);
            if (!$translation instanceof BuilderPageTranslation) {
                $translation = (new BuilderPageTranslation())->setLocale($locale);
                $page->addTranslation($translation);
            }

            $translation
                ->setTitle($locale === $defaultLocale ? $newTitle : $sourceTranslation->getTitle())
                ->setSlug($locale === $defaultLocale ? $page->getPageKey() : $sourceTranslation->getSlug())
                ->setMetaTitle($sourceTranslation->getMetaTitle())
                ->setMetaDescription($sourceTranslation->getMetaDescription())
                ->setOgTitle($sourceTranslation->getOgTitle())
                ->setOgDescription($sourceTranslation->getOgDescription())
                ->setOgImage($sourceTranslation->getOgImage())
                ->setCanonicalUrl($sourceTranslation->getCanonicalUrl())
                ->setRobots($sourceTranslation->getRobots());
        }

        $this->flushPage($page);

        return $page;
    }

    public function publish(BuilderPage $page): void
    {
        $document = $page->getDocument();
        if ($document instanceof BuilderDocument && $this->contentFieldsNormalizer instanceof ContentFieldsNormalizer) {
            $structure = $this->documentNormalizer->normalize($document->getStructure());
            $errors    = $this->contentFieldsNormalizer->validateRequired(
                $structure,
                $this->builderLocales->getAll(),
            );
            if ($errors !== []) {
                throw new InvalidArgumentException($errors[0]['message']);
            }
        }

        if ($this->pageRevisionStore?->isOnPublish() === true) {
            $this->pageRevisionStore->snapshot(
                $page,
                $this->pageRevisionStore->defaultPublishLabel(),
                skipIfUnchanged: true,
            );
        }

        $page->setStatus(PageStatus::Published);
        $page->setPublishedAt(new DateTimeImmutable());
        $this->entityManager->flush();
    }

    public function unpublish(BuilderPage $page): void
    {
        $page->setStatus(PageStatus::Draft);
        $page->setPublishedAt(null);
        $this->entityManager->flush();
    }

    public function flushPage(BuilderPage $page): void
    {
        $this->entityManager->persist($page);
        $this->entityManager->flush();
    }

    /**
     * @param array<string, mixed> $structure
     */
    public function validateStructure(array $structure): void
    {
        if ($this->documentNormalizer->isGrapesStructure($structure)) {
            $this->validateGrapesStructure($structure);

            return;
        }

        if ((int) ($structure['version'] ?? 0) !== DocumentNormalizer::SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported document version.');
        }

        $sections = $structure['sections'] ?? null;
        if (!is_array($sections)) {
            throw new InvalidArgumentException('Document sections must be an array.');
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                throw new InvalidArgumentException('Each section must be an object.');
            }

            $columns = $section['columns'] ?? null;
            if (!is_array($columns) || $columns === []) {
                throw new InvalidArgumentException('Each section must contain at least one column.');
            }

            foreach ($columns as $column) {
                if (!is_array($column)) {
                    throw new InvalidArgumentException('Each column must be an object.');
                }

                $widgets = $column['widgets'] ?? null;
                if (!is_array($widgets)) {
                    throw new InvalidArgumentException('Each column must contain a widgets array.');
                }

                foreach ($widgets as $widget) {
                    $this->validateWidgetNode($widget, 0);
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function validateGrapesStructure(array $structure): void
    {
        if ((int) ($structure['version'] ?? 0) !== DocumentNormalizer::GRAPES_SCHEMA_VERSION) {
            throw new InvalidArgumentException('Unsupported document version.');
        }

        if (($structure['engine'] ?? null) !== DocumentNormalizer::ENGINE_GRAPESJS) {
            throw new InvalidArgumentException('GrapesJS documents require engine=grapesjs.');
        }

        if (!is_string($structure['html'] ?? null)) {
            throw new InvalidArgumentException('GrapesJS html must be a string.');
        }

        if (!is_string($structure['css'] ?? null)) {
            throw new InvalidArgumentException('GrapesJS css must be a string.');
        }

        if (!is_array($structure['grapes'] ?? null)) {
            throw new InvalidArgumentException('GrapesJS grapes project must be an object.');
        }

        $localeContent = $structure['localeContent'] ?? [];
        if (!is_array($localeContent)) {
            throw new InvalidArgumentException('GrapesJS localeContent must be an object.');
        }

        foreach ($localeContent as $locale => $content) {
            if (!is_string($locale) || $locale === '') {
                throw new InvalidArgumentException('Invalid locale key in localeContent.');
            }
            if (!is_array($content)) {
                throw new InvalidArgumentException(sprintf('localeContent.%s must be an object.', $locale));
            }
            if (!is_string($content['html'] ?? null) || !is_string($content['css'] ?? null) || !is_array($content['grapes'] ?? null)) {
                throw new InvalidArgumentException(sprintf('localeContent.%s must contain html, css and grapes.', $locale));
            }
        }
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return array<string, mixed>
     */
    private function sanitizeGrapesLocaleContent(array $structure): array
    {
        $localeContent = $structure['localeContent'] ?? [];
        if (!is_array($localeContent)) {
            $structure['localeContent'] = [];

            return $structure;
        }

        $sanitized = [];
        foreach ($localeContent as $locale => $content) {
            if (!is_string($locale) || !is_array($content)) {
                continue;
            }
            $sanitized[$locale] = [
                'html' => $this->grapesDocumentSanitizer->sanitizeHtml(
                    is_string($content['html'] ?? null) ? $content['html'] : '',
                ),
                'css' => $this->grapesDocumentSanitizer->sanitizeCss(
                    is_string($content['css'] ?? null) ? $content['css'] : '',
                ),
                'grapes' => is_array($content['grapes'] ?? null) ? $content['grapes'] : [],
            ];
        }
        $structure['localeContent'] = $sanitized;

        return $structure;
    }

    private function validateWidgetNode(mixed $widget, int $depth): void
    {
        if ($depth > DocumentNormalizer::MAX_NESTING_DEPTH) {
            throw new InvalidArgumentException('Widget nesting exceeds maximum depth.');
        }

        if (!is_array($widget)) {
            throw new InvalidArgumentException('Each widget must be an object.');
        }

        $type = $widget['type'] ?? null;
        if (!is_string($type) || $type === '') {
            throw new InvalidArgumentException('Widget type is required.');
        }

        if (!$this->widgetTypeRegistry->has($type)) {
            throw new InvalidArgumentException(sprintf('Unknown widget type "%s".', $type));
        }

        $children = $widget['children'] ?? [];
        if (!is_array($children)) {
            throw new InvalidArgumentException('Widget children must be an array.');
        }

        $allowsChildren = $this->widgetTypeRegistry->get($type)->allowsChildren();
        if (!$allowsChildren && $children !== []) {
            throw new InvalidArgumentException(sprintf('Widget type "%s" does not allow nested children.', $type));
        }

        foreach ($children as $child) {
            $this->validateWidgetNode($child, $depth + 1);
        }
    }

    /**
     * @param array<string, mixed> $localeWidgetProps
     * @param array<string, mixed> $fallbackWidgetProps
     *
     * @return array<string, mixed>
     */
    public function mergePropsWithFallbackLocale(
        array $localeWidgetProps,
        array $fallbackWidgetProps,
        string $locale,
        string $fallbackLocale,
    ): array {
        return $this->widgetPropsMerger->mergePropsWithFallbackLocale(
            $localeWidgetProps,
            $fallbackWidgetProps,
            $locale,
            $fallbackLocale,
        );
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $rawWidgetProps keyed by widget id
     *
     * @return array<string, mixed>
     */
    public function sanitizeWidgetPropsForStructure(array $structure, array $rawWidgetProps): array
    {
        return $this->sanitizeWidgetPropsByLocale($structure, $rawWidgetProps);
    }

    /**
     * @param array<string, mixed> $structure
     *
     * @return list<string>
     */
    public function collectWidgetIds(array $structure): array
    {
        $ids      = [];
        $sections = $structure['sections'] ?? [];
        if (!is_array($sections)) {
            return [];
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach ($section['columns'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                foreach ($column['widgets'] ?? [] as $widget) {
                    $this->collectWidgetIdsFromNode($widget, $ids);
                }
            }
        }

        return $ids;
    }

    /**
     * @param list<string> $ids
     */
    private function collectWidgetIdsFromNode(mixed $widget, array &$ids): void
    {
        if (!is_array($widget)) {
            return;
        }
        $id = $widget['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $ids[] = $id;
        }
        foreach ($widget['children'] ?? [] as $child) {
            $this->collectWidgetIdsFromNode($child, $ids);
        }
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $rawWidgetProps
     *
     * @return array<string, mixed>
     */
    private function sanitizeWidgetPropsByLocale(array $structure, array $rawWidgetProps): array
    {
        $sanitized = [];
        foreach ($this->collectWidgetIds($structure) as $widgetId) {
            $widgetTypeName = $this->resolveWidgetTypeForId($structure, $widgetId);
            if ($widgetTypeName === null) {
                continue;
            }

            $props                = is_array($rawWidgetProps[$widgetId] ?? null) ? $rawWidgetProps[$widgetId] : [];
            $type                 = $this->widgetTypeRegistry->get($widgetTypeName);
            $sanitized[$widgetId] = $type->sanitizeProps($props, $this->protection);
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $structure
     */
    private function resolveWidgetTypeForId(array $structure, string $widgetId): ?string
    {
        $sections = $structure['sections'] ?? [];
        if (!is_array($sections)) {
            return null;
        }

        foreach ($sections as $section) {
            if (!is_array($section)) {
                continue;
            }
            foreach ($section['columns'] ?? [] as $column) {
                if (!is_array($column)) {
                    continue;
                }
                foreach ($column['widgets'] ?? [] as $widget) {
                    $found = $this->findWidgetTypeById($widget, $widgetId);
                    if ($found !== null) {
                        return $found;
                    }
                }
            }
        }

        return null;
    }

    private function findWidgetTypeById(mixed $widget, string $widgetId): ?string
    {
        if (!is_array($widget)) {
            return null;
        }
        if (($widget['id'] ?? null) === $widgetId) {
            $type = $widget['type'] ?? null;

            return is_string($type) ? $type : null;
        }
        foreach ($widget['children'] ?? [] as $child) {
            $found = $this->findWidgetTypeById($child, $widgetId);
            if ($found !== null) {
                return $found;
            }
        }

        return null;
    }
}
