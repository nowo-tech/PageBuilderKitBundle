<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocumentLocale;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRevisionRepositoryInterface;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\PageRevisionStore;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageRevisionStore::class)]
final class PageRevisionStoreTest extends TestCase
{
    #[Test]
    public function snapshotReturnsNullWhenDisabled(): void
    {
        $store = $this->createStore(enabled: false);
        $page  = $this->pageWithStructure();

        self::assertNull($store->snapshot($page, 'v1'));
    }

    #[Test]
    public function snapshotReturnsNullWhenLivePayloadMissing(): void
    {
        $store = $this->createStore(enabled: true);

        self::assertNull($store->snapshot(new BuilderPage(), 'empty'));
    }

    #[Test]
    public function snapshotPersistsRevisionWhenEnabled(): void
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist')->with(self::isInstanceOf(BuilderPageRevision::class));
        $em->expects(self::atLeastOnce())->method('flush');

        $repo = $this->repo(
            latest: null,
            list: [],
        );

        $store = new PageRevisionStore($em, $repo, enabled: true, maxPerPage: 50, onSave: true, onPublish: true);
        $page  = $this->pageWithStructure();

        $revision = $store->snapshot($page, 'Manual');

        self::assertInstanceOf(BuilderPageRevision::class, $revision);
        self::assertSame('Manual', $revision->getLabel());
        self::assertSame($page, $revision->getPage());
        self::assertNotSame([], $revision->getStructure());
    }

    #[Test]
    public function snapshotSkipsWhenUnchangedAgainstLatest(): void
    {
        $page     = $this->pageWithStructure();
        $existing = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure($page->getDocument()?->getStructure() ?? [])
            ->setWidgetPropsByLocale([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::never())->method('persist');

        $store = new PageRevisionStore(
            $em,
            $this->repo(latest: $existing, list: [$existing]),
            enabled: true,
        );

        self::assertNull($store->snapshot($page, null, skipIfUnchanged: true));
    }

    #[Test]
    public function pruneRemovesOldestBeyondMax(): void
    {
        $page = $this->pageWithStructure();
        $keep = (new BuilderPageRevision())->setPage($page)->setStructure(['version' => 2])->setLabel('keep');
        $drop = (new BuilderPageRevision())->setPage($page)->setStructure(['version' => 2])->setLabel('drop');
        $page->addRevision($keep);
        $page->addRevision($drop);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('remove')->with($drop);
        $em->expects(self::once())->method('flush');

        $store = new PageRevisionStore(
            $em,
            $this->repo(latest: $keep, list: [$keep, $drop]),
            enabled: true,
            maxPerPage: 1,
        );
        $store->prune($page);
    }

    #[Test]
    public function accessorsAndLookupHelpersReflectConfiguration(): void
    {
        $page     = $this->pageWithStructure();
        $revision = (new BuilderPageRevision())->setPage($page)->setStructure(['version' => 2])->setWidgetPropsByLocale([]);

        $repo = new class($revision) implements BuilderPageRevisionRepositoryInterface {
            public function __construct(private readonly BuilderPageRevision $revision)
            {
            }

            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return [$this->revision];
            }

            public function findLatestForPage(BuilderPage $page): BuilderPageRevision
            {
                return $this->revision;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return $revisionId === 7 ? $this->revision : null;
            }
        };

        $store = new PageRevisionStore(
            $this->createStub(EntityManagerInterface::class),
            $repo,
            enabled: true,
            maxPerPage: 7,
            onSave: false,
            onPublish: true,
        );

        self::assertTrue($store->isEnabled());
        self::assertFalse($store->isOnSave());
        self::assertTrue($store->isOnPublish());
        self::assertSame(7, $store->getMaxPerPage());
        self::assertSame([$revision], $store->listForPage($page));
        self::assertSame($revision, $store->findForPage($page, 7));
        self::assertNull($store->findForPage($page, 99));
    }

    #[Test]
    public function extractLivePayloadCollectsLocalePropsAndHandlesEmptyPage(): void
    {
        $store = $this->createStore(enabled: true);
        $page  = new BuilderPage();

        self::assertNull($store->extractLivePayload($page));

        $page->setPageKey('home');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([]));
        self::assertNull($store->extractLivePayload($page));

        $pageWithContent = $this->pageWithStructure();
        $document        = $pageWithContent->getDocument();
        self::assertInstanceOf(BuilderDocument::class, $document);
        $document->getLocales()->add(
            (new BuilderDocumentLocale())
                ->setLocale('es')
                ->setWidgetProps(['hero' => ['title' => 'Hola']])
                ->setDocument($document),
        );

        $payload = $store->extractLivePayload($pageWithContent);

        self::assertIsArray($payload);
        self::assertSame(['hero' => ['title' => 'Hola']], $payload['widgetPropsByLocale']['es']);
        self::assertNotSame('', $payload['fingerprint']);
        self::assertStringStartsWith('Published ', $store->defaultPublishLabel());
    }

    #[Test]
    public function snapshotCanIgnoreUnchangedGuardAndPruneCanBeDisabledByLimit(): void
    {
        $page     = $this->pageWithStructure();
        $existing = (new BuilderPageRevision())
            ->setPage($page)
            ->setStructure($page->getDocument()?->getStructure() ?? [])
            ->setWidgetPropsByLocale([]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('persist');
        $em->expects(self::once())->method('flush');
        $em->expects(self::never())->method('remove');

        $store = new PageRevisionStore(
            $em,
            $this->repo(latest: $existing, list: [$existing]),
            enabled: true,
            maxPerPage: 0,
        );

        $revision = $store->snapshot($page, 'Forced', skipIfUnchanged: false);
        $store->prune($page);

        self::assertInstanceOf(BuilderPageRevision::class, $revision);
        self::assertSame('Forced', $revision->getLabel());
    }

    private function createStore(bool $enabled): PageRevisionStore
    {
        return new PageRevisionStore(
            $this->createStub(EntityManagerInterface::class),
            $this->repo(latest: null, list: []),
            enabled: $enabled,
        );
    }

    /**
     * @param list<BuilderPageRevision> $list
     */
    private function repo(?BuilderPageRevision $latest, array $list): BuilderPageRevisionRepositoryInterface
    {
        return new class($latest, $list) implements BuilderPageRevisionRepositoryInterface {
            /**
             * @param list<BuilderPageRevision> $list
             */
            public function __construct(
                private readonly ?BuilderPageRevision $latest,
                private readonly array $list,
            ) {
            }

            public function findByPageNewestFirst(BuilderPage $page): array
            {
                return $this->list;
            }

            public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
            {
                return $this->latest;
            }

            public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
            {
                return null;
            }
        };
    }

    private function pageWithStructure(): BuilderPage
    {
        $page = (new BuilderPage())->setPageKey('home');
        $doc  = (new BuilderDocument())->setPage($page)->setStructure([
            'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'    => '<p>Hi</p>',
            'css'     => '',
            'grapes'  => [],
        ]);
        $page->setDocument($doc);

        return $page;
    }
}
