<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;

use function is_string;

/**
 * Scalar status lookup for {@see BuilderPage}: no inverse OneToOne document join.
 *
 * {@see BuilderPageRepository::findOneByPageKey()} loads the entity graph; public controllers
 * often only need the status before deciding between 404 and render.
 */
final readonly class BuilderPageStatusQuery implements BuilderPageStatusQueryInterface
{
    public function __construct(
        private EntityManagerInterface $em,
    ) {
    }

    public function findStatusByPageKey(string $pageKey): ?PageStatus
    {
        try {
            $value = $this->em->createQueryBuilder()
                ->select('p.status')
                ->from(BuilderPage::class, 'p')
                ->where('p.pageKey = :pageKey')
                ->setParameter('pageKey', $pageKey)
                ->setMaxResults(1)
                ->getQuery()
                ->getSingleScalarResult();
        } catch (NoResultException) {
            return null;
        }

        if ($value instanceof PageStatus) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            return PageStatus::tryFrom($value);
        }

        return null;
    }
}
