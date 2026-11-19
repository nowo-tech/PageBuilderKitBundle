<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use DateTimeImmutable;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Event\PageCreatedEvent;
use Nowo\PageBuilderKitBundle\Event\PageDocumentSavedEvent;
use Nowo\PageBuilderKitBundle\Event\PageMetadataSavedEvent;
use Nowo\PageBuilderKitBundle\Event\PagePublishedEvent;
use Nowo\PageBuilderKitBundle\Event\PageUnpublishedEvent;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderTransaction;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Widget\WidgetTypeRegistry;
use Psr\Clock\ClockInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Uid\Uuid;
use Throwable;

use function in_array;
use function is_array;
use function is_string;
use function sprintf;
use function strtolower;
use function trim;

/**
 * Page write service of the admin UI (entity-based) and the key-based {@see PagePublisherInterface} port (1.14).
 */
final readonly class DocumentService implements PagePublisherInterface
{
    private DocumentStructureValidator $structureValidator;

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
        ?DocumentStructureValidator $structureValidator = null,
        private ?ClockInterface $clock = null,
        private LoggerInterface $logger = new NullLogger(),
        private ?PageBuilderTransaction $transaction = null,
    ) {
        $this->structureValidator = $structureValidator ?? new DocumentStructureValidator($widgetTypeRegistry);
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
        $isGrapes            = $this->documentNormalizer->isGrapesStructure($normalizedStructure);
        if ($isGrapes) {
            $normalizedStructure = $this->grapesDocumentSanitizer->sanitizeStructure($normalizedStructure);
        }
        $this->validateStructure($normalizedStructure);

        // Sanitised before the unit: nothing but the write runs under the page lock (rule 9).
        $propsByLocale = [];
        foreach ($this->builderLocales->getAll() as $locale) {
            $rawProps               = $isGrapes ? [] : ($widgetPropsByLocale[$locale] ?? []);
            $propsByLocale[$locale] = $isGrapes ? [] : $this->sanitizeWidgetPropsByLocale($normalizedStructure, is_array($rawProps) ? $rawProps : []);
        }

        $this->unit(function () use ($page, $normalizedStructure, $propsByLocale): void {
            // The revision snapshot below records the content this save replaces: read it under the lock.
            $this->transaction?->lockPage($page);

            if ($this->pageRevisionStore?->isOnSave() === true) {
                $this->pageRevisionStore->snapshot($page, null, skipIfUnchanged: true);
            }

            $document = $page->getDocument();
            if (!$document instanceof BuilderDocument) {
                $document = new BuilderDocument();
                $document->setPage($page);
            }

            $document->setStructure($normalizedStructure);
            foreach ($propsByLocale as $locale => $props) {
                $document->upsertLocale($locale, $props);
            }

            $this->entityManager->persist($page);
            $this->commit(new PageDocumentSavedEvent($page->getPageKey(), $page->getUuid()));
        });
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

        $page = new BuilderPage()
            ->setUuid(Uuid::v4()->toRfc4122())
            ->setPageKey($pageKey)
            ->setStatus(PageStatus::Draft);

        $translation = new BuilderPageTranslation()
            ->setLocale($locale)
            ->setTitle($title)
            ->setSlug($pageKey);
        $page->addTranslation($translation);

        $document = new BuilderDocument()
            ->setPage($page)
            ->setStructure($this->documentNormalizer->emptyStructure());

        foreach ($this->builderLocales->getAll() as $configuredLocale) {
            $document->upsertLocale($configuredLocale, []);
        }

        $page->setDocument($document);

        return $this->unit(function () use ($page, $pageKey): BuilderPage {
            $this->entityManager->persist($page);

            try {
                $this->commit(new PageCreatedEvent($page->getPageKey(), $page->getUuid()));
            } catch (UniqueConstraintViolationException $e) {
                // 1.17 (REQ-RUNTIME-003 rule 4): two editors creating the same key at once; the unique key refused
                // the second one, which gets the answer of the check above (the only unique key of this insert).
                throw new InvalidArgumentException(sprintf('Page key "%s" already exists.', $pageKey), 0, $e);
            }

            return $page;
        });
    }

    /**
     * Clone structure, locale props, and translations into a new draft page.
     */
    public function duplicatePage(BuilderPage $source, string $newPageKey, ?string $title = null): BuilderPage
    {
        // 1.17: one write unit (a failure never leaves a half-copied page).
        return $this->unit(fn (): BuilderPage => $this->doDuplicatePage($source, $newPageKey, $title));
    }

    public function publish(BuilderPage $page): void
    {
        $this->unit(function () use ($page): void {
            // The required-field check and the revision read the content being published: read it under the lock.
            $this->transaction?->lockPage($page);
            $this->doPublish($page);
        });
    }

    public function unpublish(BuilderPage $page): void
    {
        $this->unit(function () use ($page): void {
            $this->transaction?->lockPage($page);
            $page->setStatus(PageStatus::Draft);
            $page->setPublishedAt(null);
            $this->commit(new PageUnpublishedEvent($page->getPageKey(), $page->getUuid()));
        });
    }

    private function doDuplicatePage(BuilderPage $source, string $newPageKey, ?string $title): BuilderPage
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
                $translation = new BuilderPageTranslation()->setLocale($locale);
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

    private function doPublish(BuilderPage $page): void
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

        $publishedAt = $this->clock?->now() ?? new DateTimeImmutable();
        $page->setStatus(PageStatus::Published);
        $page->setPublishedAt($publishedAt);
        $this->commit(new PagePublishedEvent($page->getPageKey(), $page->getUuid(), $publishedAt));
        $this->logger->info('Page builder page published.', ['bundle' => 'NowoPageBuilderKitBundle', 'action' => 'publish', 'page_key' => $page->getPageKey()]);
    }

    /**
     * Saves page changes outside the document (translations, SEO / Open Graph).
     */
    public function flushPage(BuilderPage $page): void
    {
        $this->entityManager->persist($page);

        try {
            $this->commit(new PageMetadataSavedEvent($page->getPageKey(), $page->getUuid()));
        } catch (Throwable $e) {
            // 1.17: one flush (the caller's form changed the entities), not a runner unit: a failed flush closes the
            // manager, which is replaced (closed only) so a kept-alive worker keeps writing.
            $this->transaction?->replaceClosedManager();

            throw $e;
        }
    }

    public function createPageByKey(string $pageKey, string $title, string $locale): string
    {
        return $this->createPage($pageKey, $title, $locale)->getUuid();
    }

    public function saveDocumentByKey(string $pageKey, array $structure, array $widgetPropsByLocale = []): void
    {
        $this->saveDocument($this->requirePage($pageKey), $structure, $widgetPropsByLocale);
    }

    public function publishByKey(string $pageKey): void
    {
        $this->publish($this->requirePage($pageKey));
    }

    public function unpublishByKey(string $pageKey): void
    {
        $this->unpublish($this->requirePage($pageKey));
    }

    /**
     * @param array<string, mixed> $structure
     */
    public function validateStructure(array $structure): void
    {
        $this->structureValidator->validate($structure, $this->documentNormalizer);
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

    private function requirePage(string $pageKey): BuilderPage
    {
        $page = $this->pageRepository->findOneByPageKey($pageKey);
        if (!$page instanceof BuilderPage) {
            throw new InvalidArgumentException(sprintf('Unknown page "%s".', $pageKey));
        }

        return $page;
    }

    /**
     * Runs one write unit (1.17, REQ-RUNTIME-003 rule 1): {@see PageBuilderTransaction::run()}, or the operation as is
     * when the service is built without the transaction service.
     *
     * @template T
     *
     * @param callable(): T $operation
     *
     * @return T
     */
    public function unit(callable $operation): mixed
    {
        return $this->transaction instanceof PageBuilderTransaction ? $this->transaction->run($operation) : $operation();
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
