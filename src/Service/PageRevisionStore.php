<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRevisionRepositoryInterface;

use function array_slice;
use function count;
use function hash;
use function json_encode;
use function sprintf;

use const JSON_THROW_ON_ERROR;

/**
 * Persists and prunes page document snapshots (BuilderPageRevision).
 */
final readonly class PageRevisionStore
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BuilderPageRevisionRepositoryInterface $revisionRepository,
        private bool $enabled = false,
        private int $maxPerPage = 50,
        private bool $onSave = true,
        private bool $onPublish = true,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function isOnSave(): bool
    {
        return $this->enabled && $this->onSave;
    }

    public function isOnPublish(): bool
    {
        return $this->enabled && $this->onPublish;
    }

    public function getMaxPerPage(): int
    {
        return $this->maxPerPage;
    }

    /**
     * Snapshot the current live document. Returns null when disabled, empty, or unchanged vs last.
     */
    public function snapshot(BuilderPage $page, ?string $label = null, bool $skipIfUnchanged = true): ?BuilderPageRevision
    {
        if (!$this->enabled) {
            return null;
        }

        $payload = $this->extractLivePayload($page);
        if ($payload === null) {
            return null;
        }

        if ($skipIfUnchanged) {
            $latest = $this->revisionRepository->findLatestForPage($page);
            if ($latest instanceof BuilderPageRevision && $this->fingerprint($latest->getStructure(), $latest->getWidgetPropsByLocale()) === $payload['fingerprint']) {
                return null;
            }
        }

        $revision = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure($payload['structure'])
            ->setWidgetPropsByLocale($payload['widgetPropsByLocale'])
            ->setLabel($label);
        $page->addRevision($revision);

        $this->entityManager->persist($revision);
        $this->entityManager->flush();
        $this->prune($page);

        return $revision;
    }

    /**
     * @return list<BuilderPageRevision>
     */
    public function listForPage(BuilderPage $page): array
    {
        return $this->revisionRepository->findByPageNewestFirst($page);
    }

    public function findForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
    {
        return $this->revisionRepository->findOneForPage($page, $revisionId);
    }

    public function prune(BuilderPage $page): void
    {
        if (!$this->enabled || $this->maxPerPage < 1) {
            return;
        }

        $all = $this->revisionRepository->findByPageNewestFirst($page);
        if (count($all) <= $this->maxPerPage) {
            return;
        }

        foreach (array_slice($all, $this->maxPerPage) as $stale) {
            $page->removeRevision($stale);
            $this->entityManager->remove($stale);
        }
        $this->entityManager->flush();
    }

    /**
     * @return array{structure: array<string, mixed>, widgetPropsByLocale: array<string, mixed>, fingerprint: string}|null
     */
    public function extractLivePayload(BuilderPage $page): ?array
    {
        $document = $page->getDocument();
        if (!$document instanceof BuilderDocument) {
            return null;
        }

        $structure = $document->getStructure();
        if ($structure === []) {
            return null;
        }

        $widgetPropsByLocale = [];
        foreach ($document->getLocales() as $localeDocument) {
            $widgetPropsByLocale[$localeDocument->getLocale()] = $localeDocument->getWidgetProps();
        }

        return [
            'structure'           => $structure,
            'widgetPropsByLocale' => $widgetPropsByLocale,
            'fingerprint'         => $this->fingerprint($structure, $widgetPropsByLocale),
        ];
    }

    public function defaultPublishLabel(): string
    {
        return sprintf('Published %s', date('Y-m-d H:i'));
    }

    /**
     * @param array<string, mixed> $structure
     * @param array<string, mixed> $widgetPropsByLocale
     */
    private function fingerprint(array $structure, array $widgetPropsByLocale): string
    {
        return hash('xxh128', json_encode([$structure, $widgetPropsByLocale], JSON_THROW_ON_ERROR));
    }
}
