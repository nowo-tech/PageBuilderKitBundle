<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Repository;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Doctrine\Middleware\Debug\DebugDataHolder;
use Symfony\Bridge\Doctrine\Middleware\Debug\Middleware;

use function dirname;

/**
 * The admin page lists read `page.document`: the inverse one-to-one must be fetch-joined, not loaded per page.
 */
final class BuilderPageRepositoryQueryCountTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private DebugDataHolder $queries;
    private BuilderPageRepository $repository;

    protected function setUp(): void
    {
        $config = ORMSetup::createAttributeMetadataConfiguration([dirname(__DIR__, 3) . '/src/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $this->queries = new DebugDataHolder();
        $config->setMiddlewares([new Middleware($this->queries, null)]);
        $connection          = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config);
        $this->entityManager = new EntityManager($connection, $config);
        new SchemaTool($this->entityManager)->createSchema($this->entityManager->getMetadataFactory()->getAllMetadata());

        foreach (['a', 'b', 'c', 'd'] as $i => $key) {
            $page = new BuilderPage()->setPageKey($key)->setUuid('00000000-0000-0000-0000-00000000000' . $i);
            if ($key !== 'd') {
                $page->setDocument(new BuilderDocument()->setPage($page)->setStructure(['key' => $key]));
            }
            $this->entityManager->persist($page);
        }
        $this->entityManager->flush();
        $this->entityManager->clear();

        $registry = $this->createMock(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new BuilderPageRepository($registry);
    }

    public function testFindAllOrderedLoadsDocumentsInTheSameQuery(): void
    {
        $this->queries->reset();
        $structures = [];
        foreach ($this->repository->findAllOrdered() as $page) {
            $structures[$page->getPageKey()] = $page->getDocument()?->getStructure();
        }

        self::assertSame(['a' => ['key' => 'a'], 'b' => ['key' => 'b'], 'c' => ['key' => 'c'], 'd' => null], $structures);
        self::assertCount(1, $this->queries->getData()['default'] ?? []);
    }

    public function testFindOrderedSliceLoadsDocumentsInTheSameQuery(): void
    {
        $this->queries->reset();
        $keys = [];
        foreach ($this->repository->findOrderedSlice(1, 2) as $page) {
            $keys[] = [$page->getPageKey(), $page->getDocument()?->getStructure()];
        }

        self::assertSame([['b', ['key' => 'b']], ['c', ['key' => 'c']]], $keys);
        self::assertCount(1, $this->queries->getData()['default'] ?? []);
    }
}
