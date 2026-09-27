<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
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
