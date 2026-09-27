<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;

use function sprintf;

/**
 * Host-facing API for listing, creating, and restoring page revisions.
 */
final readonly class PageRevisionService
{
    public function __construct(
        private PageRevisionStore $store,
        private DocumentService $documentService,
        private DocumentNormalizer $documentNormalizer = new DocumentNormalizer(),
        private DocumentDiff $documentDiff = new DocumentDiff(),
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->store->isEnabled();
    }

    /**
     * @return list<BuilderPageRevision>
     */
    public function list(BuilderPage $page): array
    {
        return $this->store->listForPage($page);
    }

    public function create(BuilderPage $page, ?string $label = null): ?BuilderPageRevision
    {
        if (!$this->store->isEnabled()) {
            throw new InvalidArgumentException('Page revisions are disabled.');
        }

        return $this->store->snapshot($page, $label, skipIfUnchanged: false);
    }

    public function restore(BuilderPage $page, int $revisionId): BuilderPageRevision
    {
        if (!$this->store->isEnabled()) {
            throw new InvalidArgumentException('Page revisions are disabled.');
        }

        $revision = $this->store->findForPage($page, $revisionId);
        if (!$revision instanceof BuilderPageRevision) {
            throw new InvalidArgumentException(sprintf('Revision %d not found for page "%s".', $revisionId, $page->getPageKey()));
        }

        // Snapshot current state before overwrite (safety net when on_save is enabled).
        if ($this->store->isOnSave()) {
            $this->store->snapshot($page, 'Before restore', skipIfUnchanged: true);
        }

        /** @var array<string, array<string, mixed>> $props */
        $props = $revision->getWidgetPropsByLocale();
        $this->documentService->saveDocument($page, $revision->getStructure(), $props);

        return $revision;
    }

    /**
     * @return array{
     *     revisionId: int|null,
     *     identical: bool,
     *     leftEngine: string,
     *     rightEngine: string,
     *     structureChanged: bool,
     *     propsChanged: bool,
     *     summary: list<string>,
     *     changedPaths: list<string>,
     *     panels: array<string, mixed>
     * }
     */
    public function diff(BuilderPage $page, int $revisionId): array
    {
        if (!$this->store->isEnabled()) {
            throw new InvalidArgumentException('Page revisions are disabled.');
        }

        $revision = $this->store->findForPage($page, $revisionId);
        if (!$revision instanceof BuilderPageRevision) {
            throw new InvalidArgumentException(sprintf('Revision %d not found for page "%s".', $revisionId, $page->getPageKey()));
        }

        $liveStructure = $this->documentNormalizer->normalize($page->getDocument()?->getStructure() ?? []);
        $liveProps     = [];
        foreach ($page->getDocument()?->getLocales() ?? [] as $localeDocument) {
            $liveProps[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        $diff = $this->documentDiff->compare(
            $liveStructure,
            $this->documentNormalizer->normalize($revision->getStructure()),
            $liveProps,
            $revision->getWidgetPropsByLocale(),
        );

        return [
            'revisionId' => $revision->getId(),
            ...$diff,
        ];
    }
}
