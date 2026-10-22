<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use SortDirection;

/** @extends ServiceEntityRepository<BuilderPage> */
final class BuilderPageRepository extends ServiceEntityRepository implements BuilderPageRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderPage::class);
    }

    public function findOneByPageKey(string $pageKey): ?BuilderPage
    {
        return $this->findOneBy(['pageKey' => $pageKey]);
    }

    /**
     * @return list<BuilderPage>
     */
    public function findAllOrdered(): array
    {
        /** @var list<BuilderPage> $pages */
        $pages = $this->createQueryBuilder('p')
            ->orderBy('p.pageKey', SortDirection::Ascending)
            ->getQuery()
            ->getResult();

        return $pages;
    }

    /**
     * One page of the admin list, ordered by page key (REQ-PERF-001).
     *
     * @return list<BuilderPage>
     */
    public function findOrderedSlice(int $offset, int $limit): array
    {
        /** @var list<BuilderPage> $pages */
        $pages = $this->createQueryBuilder('p')
            ->orderBy('p.pageKey', SortDirection::Ascending)
            ->setFirstResult(max(0, $offset))
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();

        return $pages;
    }

    /**
     * Number of pages (admin list pagination).
     */
    public function countAll(): int
    {
        return $this->count([]);
    }
}
