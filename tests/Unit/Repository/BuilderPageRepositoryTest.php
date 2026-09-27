<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Repository;

use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepository;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionNamedType;

final class BuilderPageRepositoryTest extends TestCase
{
    #[Test]
    public function implementsRepositoryContract(): void
    {
        $reflection = new ReflectionClass(BuilderPageRepository::class);

        self::assertTrue($reflection->implementsInterface(BuilderPageRepositoryInterface::class));
        self::assertTrue($reflection->hasMethod('findOneByPageKey'));
        self::assertTrue($reflection->hasMethod('findAllOrdered'));

        $findOne = $reflection->getMethod('findOneByPageKey');
        self::assertTrue($findOne->isPublic());
        self::assertCount(1, $findOne->getParameters());
        $returnType = $findOne->getReturnType();
        self::assertInstanceOf(ReflectionNamedType::class, $returnType);
        self::assertTrue($returnType->allowsNull());
        self::assertSame(BuilderPage::class, $returnType->getName());

        $findAll = $reflection->getMethod('findAllOrdered');
        self::assertTrue($findAll->isPublic());
        self::assertCount(0, $findAll->getParameters());
    }
}
