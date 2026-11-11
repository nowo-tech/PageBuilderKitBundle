<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Persistence\PageBuilderEntityManagerFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageBuilderEntityManagerFactory::class)]
final class PageBuilderEntityManagerFactoryTest extends TestCase
{
    #[Test]
    public function returnsTheManagerOfTheBundleEntities(): void
    {
        $em       = $this->createStub(EntityManagerInterface::class);
        $registry = $this->createMock(ManagerRegistry::class);
        $registry->expects(self::once())->method('getManagerForClass')->with(BuilderPage::class)->willReturn($em);

        self::assertSame($em, PageBuilderEntityManagerFactory::create($registry));
    }

    #[Test]
    public function failsClearlyWhenNothingMapsTheEntities(): void
    {
        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn(null);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No Doctrine ORM entity manager maps');
        PageBuilderEntityManagerFactory::create($registry);
    }
}
