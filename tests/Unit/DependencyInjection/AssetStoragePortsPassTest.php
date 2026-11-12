<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use ArrayObject;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AssetStoragePortsPass;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(AssetStoragePortsPass::class)]
final class AssetStoragePortsPassTest extends TestCase
{
    #[Test]
    public function withoutTheStorageNothingHappens(): void
    {
        $container = new ContainerBuilder();
        new AssetStoragePortsPass()->process($container);

        self::assertFalse($container->hasAlias(PageBuilderAssetStorageInterface::class));
    }

    #[Test]
    public function unresolvableClassKeepsThePreviousWiringWithoutADeprecation(): void
    {
        $container = $this->container();
        $container->register('app.storage', ArrayObject::class)->setFactory([ArrayObject::class, 'create']);
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');

        new AssetStoragePortsPass()->process($container);

        $alias = $container->getAlias(PageBuilderAssetStorageInterface::class);
        self::assertSame(AssetStoragePortsPass::STORAGE_ID, (string) $alias);
        self::assertFalse($alias->isDeprecated());
        self::assertFalse($container->has(PageBuilderAssetUploadStorageInterface::class));
        self::assertEquals(new Reference(PageBuilderAssetStorageInterface::class), $container->getDefinition(AssetUploadHandler::class)->getArgument('$storage'));
    }

    #[Test]
    public function missingServiceKeepsThePreviousWiring(): void
    {
        $container = $this->container();
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.missing');

        new AssetStoragePortsPass()->process($container);

        self::assertSame(AssetStoragePortsPass::STORAGE_ID, (string) $container->getAlias(PageBuilderAssetStorageInterface::class));
    }

    #[Test]
    public function hostAliasesAreNeverReplaced(): void
    {
        $container = $this->container();
        $container->register('app.storage', ArrayObject::class);
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');
        $container->setAlias(PageBuilderAssetStorageInterface::class, 'app.host_legacy');

        new AssetStoragePortsPass()->process($container);

        self::assertSame('app.host_legacy', (string) $container->getAlias(PageBuilderAssetStorageInterface::class));
        self::assertEquals(new Reference(PageBuilderAssetStorageInterface::class), $container->getDefinition(AssetUploadHandler::class)->getArgument('$storage'));
    }

    private function container(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(AssetUploadHandler::class, AssetUploadHandler::class);

        return $container;
    }
}
