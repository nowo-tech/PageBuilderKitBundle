<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;

/** @extends ServiceEntityRepository<BuilderPageRevision> */
final class BuilderPageRevisionRepository extends ServiceEntityRepository implements BuilderPageRevisionRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderPageRevision::class);
    }

    /**
     * @return list<BuilderPageRevision>
     */
    public function findByPageNewestFirst(BuilderPage $page): array
    {
        /** @var list<BuilderPageRevision> $revisions */
        $revisions = $this->createQueryBuilder('r')
            ->andWhere('r.page = :page')
            ->setParameter('page', $page)
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->getQuery()
            ->getResult();

        return $revisions;
    }

    public function findLatestForPage(BuilderPage $page): ?BuilderPageRevision
    {
        /** @var BuilderPageRevision|null $revision */
        $revision = $this->createQueryBuilder('r')
            ->andWhere('r.page = :page')
            ->setParameter('page', $page)
            ->orderBy('r.createdAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $revision;
    }

    public function findOneForPage(BuilderPage $page, int $revisionId): ?BuilderPageRevision
    {
        /** @var BuilderPageRevision|null $revision */
        $revision = $this->createQueryBuilder('r')
            ->andWhere('r.page = :page')
            ->andWhere('r.id = :id')
            ->setParameter('page', $page)
            ->setParameter('id', $revisionId)
            ->getQuery()
            ->getOneOrNullResult();

        return $revision;
    }
}
