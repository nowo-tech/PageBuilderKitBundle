<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;

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
            ->orderBy('p.pageKey', 'ASC')
            ->getQuery()
            ->getResult();

        return $pages;
    }
}
