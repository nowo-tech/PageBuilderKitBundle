<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts;

use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AssetStoragePortsPass;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\AwsS3AssetStorage;
use Nowo\PageBuilderKitBundle\Media\LocalFilesystemAssetStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DddHost\MediaLibrary;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DddHost\MediaLibraryAssetStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DecoratingUploadStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\HostUploadStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

use function base64_decode;
use function file_put_contents;

/**
 * `lint:container` (Symfony's `CheckAliasValidityPass` + `CheckTypeDeclarationsPass`) passes for the built-in storages, a
 * host alias of the port, a decorator of it, and `storage: service`. The port alias resolves to a service that
 * implements it, the 1.x id `PageBuilderAssetStorageInterface` (removed in 2.0) no longer exists, and the upload handler
 * uses the host service (2.0: through `storeUpload()` only). Plus a DDD host whose adapter maps the VO to its own domain
 * service.
 */
#[CoversClass(AssetStoragePortsPass::class)]
final class AssetPortsKernelTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        new Filesystem()->remove(AssetPortsKernel::baseDir());
    }

    public static function tearDownAfterClass(): void
    {
        new Filesystem()->remove(AssetPortsKernel::baseDir());
    }

    /**
     * @return iterable<string, array{string, class-string, array{class-string, bool}|null, array{class-string, bool}|null}>
     */
    public static function variants(): iterable
    {
        $local = LocalFilesystemAssetStorage::class;

        // variant, handler storage, [port class, deprecated], the removed 1.x id (never registered since 2.0)
        yield 'built-in local' => ['local', $local, [$local, false], null];
        yield 'built-in s3' => ['s3', AwsS3AssetStorage::class, [AwsS3AssetStorage::class, false], null];
        yield 'host alias of the port' => ['new', HostUploadStorage::class, [HostUploadStorage::class, false], null];
        yield 'decorator of the port' => ['decorator', DecoratingUploadStorage::class, [DecoratingUploadStorage::class, false], null];
        yield 'storage: service' => ['service-new', HostUploadStorage::class, [HostUploadStorage::class, false], null];
        yield 'storage: service, DDD adapter' => ['ddd', MediaLibraryAssetStorage::class, [MediaLibraryAssetStorage::class, false], null];
    }

    /**
     * @param class-string $handlerStorage
     * @param array{class-string, bool}|null $newPort
     * @param array{class-string, bool}|null $legacyPort
     */
    #[Test]
    #[DataProvider('variants')]
    public function lintContainerPassesAndTheHandlerUsesTheHostService(string $variant, string $handlerStorage, ?array $newPort, ?array $legacyPort): void
    {
        // lint:container rebuilds the container on the kernel it runs on (and re-creates its bundles, so that kernel
        // cannot be shut down cleanly afterwards): lint on its own kernel, inspect the services on another one.
        $lintKernel = new AssetPortsKernel($variant);
        $lintKernel->boot();
        $application = new Application($lintKernel);
        $application->setAutoExit(false);
        $tester = new CommandTester($application->find('lint:container'));
        self::assertSame(0, $tester->execute([]), $tester->getDisplay());
        self::assertStringContainsString('linted successfully', $tester->getDisplay());
        unset($application, $tester, $lintKernel);

        $kernel = new AssetPortsKernel($variant);
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            self::assertSame($newPort, $container->getParameter('test.pbk_port.new'));
            self::assertSame($legacyPort, $container->getParameter('test.pbk_port.legacy'));

            $probe = $container->get(AssetPortsKernel::PROBE);
            self::assertInstanceOf(ServiceLocator::class, $probe);
            $handler = $probe->get(AssetUploadHandler::class);
            self::assertInstanceOf(AssetUploadHandler::class, $handler);
            $storage = new ReflectionProperty($handler, 'storage')->getValue($handler);
            self::assertInstanceOf($handlerStorage, $storage);
            if ($variant === 'decorator') {
                self::assertInstanceOf(DecoratingUploadStorage::class, $storage);
                self::assertInstanceOf(LocalFilesystemAssetStorage::class, $storage->inner);
            }
        } finally {
            $kernel->shutdown();
        }
    }

    #[Test]
    public function dddHostStoresThroughItsOwnDomainService(): void
    {
        $kernel = new AssetPortsKernel('ddd');
        $kernel->boot();

        try {
            $container = $kernel->getContainer();
            $probe     = $container->get(AssetPortsKernel::PROBE);
            self::assertInstanceOf(ServiceLocator::class, $probe);
            $handler = $probe->get(AssetUploadHandler::class);
            self::assertInstanceOf(AssetUploadHandler::class, $handler);

            $png  = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==', true);
            $path = AssetPortsKernel::baseDir() . '/ddd-upload';
            new Filesystem()->mkdir(AssetPortsKernel::baseDir());
            file_put_contents($path, $png);

            $assets = $handler->handle([new UploadedFile($path, 'hero.png', 'text/plain', null, true)]);

            self::assertSame([['src' => '/media/media-1', 'type' => 'image', 'name' => 'hero.png']], $assets);
            $library = $container->get(MediaLibrary::class);
            self::assertInstanceOf(MediaLibrary::class, $library);
            // The MIME type is the one detected on the server, not the client's "text/plain".
            self::assertSame(['media-1' => ['name' => 'hero.png', 'mime' => 'image/png', 'content' => $png]], $library->items);
        } finally {
            $kernel->shutdown();
        }
    }
}
