<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Persistence;

use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\Persistence\Mapping\Driver\MappingDriver;
use Doctrine\Persistence\Mapping\Driver\MappingDriverChain;
use Doctrine\Persistence\Mapping\MappingException;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderEntityManagerPass;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageRevision;
use Nowo\PageBuilderKitBundle\Persistence\ExcludedEntityNamespaceDriver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(ExcludedEntityNamespaceDriver::class)]
final class ExcludedEntityNamespaceDriverTest extends TestCase
{
    #[Test]
    public function mapsNothing(): void
    {
        $driver = new ExcludedEntityNamespaceDriver();

        self::assertTrue($driver->isTransient(BuilderPage::class));
        self::assertSame([], $driver->getAllClassNames());

        $this->expectException(MappingException::class);
        $this->expectExceptionMessage(BuilderPage::class);
        $driver->loadMetadataForClass(BuilderPage::class, new ClassMetadata(BuilderPage::class));
    }

    /**
     * A chain asks its namespace drivers before its default driver: a default driver that claims every entity class
     * no longer claims the bundle entities.
     */
    #[Test]
    public function winsOverTheDefaultDriverOfAChain(): void
    {
        $catchAll = $this->createStub(MappingDriver::class);
        $catchAll->method('isTransient')->willReturn(false);
        $chain = new MappingDriverChain();
        $chain->setDefaultDriver($catchAll);
        self::assertFalse($chain->isTransient(BuilderPage::class));

        $chain->addDriver(new ExcludedEntityNamespaceDriver(), PageBuilderEntityManagerPass::ENTITY_NAMESPACE);

        self::assertTrue($chain->isTransient(BuilderPage::class));
        self::assertTrue($chain->isTransient(BuilderPageRevision::class));
        self::assertFalse($chain->isTransient(self::class), 'other classes still go to the default driver');
    }
}
