<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\DependencyInjection;

use ArrayObject;
use LogicException;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AssetStoragePortsPass;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * 2.0: `storage: service` must name a service on {@see PageBuilderAssetUploadStorageInterface}, the only asset storage
 * port; the 1.x id `PageBuilderAssetStorageInterface` is never registered.
 */
#[CoversClass(AssetStoragePortsPass::class)]
final class AssetStoragePortsPassTest extends TestCase
{
    private const string REMOVED = 'Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface';

    #[Test]
    public function withoutTheStorageNothingHappens(): void
    {
        $container = new ContainerBuilder();
        new AssetStoragePortsPass()->process($container);

        self::assertFalse($container->hasAlias(PageBuilderAssetUploadStorageInterface::class));
    }

    #[Test]
    public function aStorageOnThePortIsAliased(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.storage', LocalFilesystemAssetStorage::class);
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');

        new AssetStoragePortsPass()->process($container);

        self::assertSame(AssetStoragePortsPass::STORAGE_ID, (string) $container->getAlias(PageBuilderAssetUploadStorageInterface::class));
        self::assertFalse($container->has(self::REMOVED));
    }

    #[Test]
    public function aFactoryServiceIsAliasedAsIs(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.storage', ArrayObject::class)->setFactory([ArrayObject::class, 'create']);
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');

        new AssetStoragePortsPass()->process($container);

        self::assertSame(AssetStoragePortsPass::STORAGE_ID, (string) $container->getAlias(PageBuilderAssetUploadStorageInterface::class));
    }

    #[Test]
    public function aMissingServiceIsLeftToSymfony(): void
    {
        $container = new ContainerBuilder();
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.missing');

        new AssetStoragePortsPass()->process($container);

        self::assertSame(AssetStoragePortsPass::STORAGE_ID, (string) $container->getAlias(PageBuilderAssetUploadStorageInterface::class));
    }

    #[Test]
    public function aStorageOffThePortFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.storage', ArrayObject::class);
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('nowo_page_builder_kit.grapesjs.assets_upload.service is "app.storage" (ArrayObject), which does not implement "' . PageBuilderAssetUploadStorageInterface::class . '"');

        new AssetStoragePortsPass()->process($container);
    }

    #[Test]
    public function anUnloadableStorageFailsTheBuild(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.storage', 'App\Missing\Storage');
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('which cannot be loaded: the class or one of its interfaces is missing');

        new AssetStoragePortsPass()->process($container);
    }

    #[Test]
    public function aHostAliasOfThePortIsNeverReplaced(): void
    {
        $container = new ContainerBuilder();
        $container->register('app.storage', ArrayObject::class);
        $container->setAlias(AssetStoragePortsPass::STORAGE_ID, 'app.storage');
        $container->setAlias(PageBuilderAssetUploadStorageInterface::class, 'app.host');

        new AssetStoragePortsPass()->process($container);

        self::assertSame('app.host', (string) $container->getAlias(PageBuilderAssetUploadStorageInterface::class));
    }
}
