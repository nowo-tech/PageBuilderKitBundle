<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\NoResultException;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageStatusQuery;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class BuilderPageStatusQueryTest extends TestCase
{
    #[Test]
    public function returnsEnumFromStringScalar(): void
    {
        self::assertSame(PageStatus::Published, $this->query('published')->findStatusByPageKey('home'));
    }

    #[Test]
    public function returnsEnumInstanceAsIs(): void
    {
        self::assertSame(PageStatus::Draft, $this->query(PageStatus::Draft)->findStatusByPageKey('home'));
    }

    #[Test]
    public function returnsNullWhenPageMissing(): void
    {
        self::assertNull($this->query(new NoResultException())->findStatusByPageKey('nope'));
    }

    #[Test]
    public function returnsNullForEmptyOrUnknownValues(): void
    {
        self::assertNull($this->query('')->findStatusByPageKey('home'));
        self::assertNull($this->query(null)->findStatusByPageKey('home'));
        self::assertNull($this->query('bogus')->findStatusByPageKey('home'));
    }

    private function query(mixed $result): BuilderPageStatusQuery
    {
        $query = $this->createMock(Query::class);
        if ($result instanceof NoResultException) {
            $query->method('getSingleScalarResult')->willThrowException($result);
        } else {
            $query->method('getSingleScalarResult')->willReturn($result);
        }

        $qb = $this->createMock(QueryBuilder::class);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('createQueryBuilder')->willReturn($qb);

        return new BuilderPageStatusQuery($em);
    }
}
