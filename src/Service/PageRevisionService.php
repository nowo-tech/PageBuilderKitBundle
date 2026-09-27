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
}
