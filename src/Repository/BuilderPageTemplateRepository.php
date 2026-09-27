<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use SortDirection;

/**
 * @extends ServiceEntityRepository<BuilderPageTemplate>
 */
final class BuilderPageTemplateRepository extends ServiceEntityRepository implements BuilderPageTemplateRepositoryInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BuilderPageTemplate::class);
    }

    public function findOneByTemplateKey(string $templateKey): ?BuilderPageTemplate
    {
        return $this->findOneBy(['templateKey' => $templateKey]);
    }

    /**
     * @return list<BuilderPageTemplate>
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.label', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }
}
