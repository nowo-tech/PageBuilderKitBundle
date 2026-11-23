<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\RemovedPorts;

use LogicException;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\AssetStoragePortsPass;
use Nowo\PageBuilderKitBundle\DependencyInjection\Compiler\PageBuilderRemovedPortsPass;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigPageContextProviderInterface;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\ConfiguredStorage\PlainStorage;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\ImplementsStorage\LegacyAssetStorage;
use Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\Unrelated\OtherMissingInterfaceUser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

/**
 * W1-1 (XR-WF-M1) through the real `Bundle::build()`: every host service still tied to a port removed in 2.0
 * (`PageBuilderAssetStorageInterface` and `GrapesTwigContextProviderInterface`, deprecated since 1.15), registered by
 * resource autodiscovery as a standard host does, fails the container build with a message naming the service, the
 * removed interface, the version that deprecated it and the replacement. Without the check Symfony drops such a service
 * without a word (a class that cannot be loaded keeps only a loading error and is removed as unused; an `IGNORE`
 * decorator of a missing id is removed): a Twig context provider dropped that way renders pages without the host's
 * data, and a storage or a checking decorator of it silently stops running.
 */
#[CoversClass(PageBuilderRemovedPortsPass::class)]
#[CoversClass(AssetStoragePortsPass::class)]
final class RemovedPortsKernelTest extends TestCase
{
    private const string STORAGE = 'Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface';

    private const string CONTEXT = 'Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface';

    public static function setUpBeforeClass(): void
    {
        new Filesystem()->remove(RemovedPortsKernel::baseDir());
    }

    public static function tearDownAfterClass(): void
    {
        new Filesystem()->remove(RemovedPortsKernel::baseDir());
    }

    /**
     * @return iterable<string, array{string|null, list<string>, string|null, string, string}>
     */
    public static function refusedHosts(): iterable
    {
        $fixtures = 'Nowo\PageBuilderKitBundle\Tests\Fixtures\RemovedPorts\\';

        yield 'autodiscovered class implementing only the removed asset storage interface' => ['ImplementsStorage', [], null, 'The service "' . $fixtures . 'ImplementsStorage\LegacyAssetStorage" cannot be loaded: Class "' . self::STORAGE . '" not found', PageBuilderAssetUploadStorageInterface::class];
        yield 'autodiscovered class implementing only the removed Twig context provider interface' => ['ImplementsContext', [], null, 'The service "' . $fixtures . 'ImplementsContext\LegacyContextProvider" cannot be loaded: Class "' . self::CONTEXT . '" not found', GrapesTwigPageContextProviderInterface::class];
        yield '#[AsAlias] of the removed asset storage id' => ['AsAliasStorage', [], null, 'The service id "' . self::STORAGE . '" (an alias of "' . $fixtures . 'AsAliasStorage\HostAssetStorage") is no longer read', PageBuilderAssetUploadStorageInterface::class];
        yield '#[AsDecorator] of the removed asset storage id' => ['AsDecoratorStorage', [], null, 'The service "' . $fixtures . 'AsDecoratorStorage\CheckingStorageDecorator" decorates "' . self::STORAGE . '", which no longer exists', PageBuilderAssetUploadStorageInterface::class];
        yield '#[AsDecorator] of the removed asset storage id with onInvalid: IGNORE' => ['AsDecoratorIgnore', [], null, 'The service "' . $fixtures . 'AsDecoratorIgnore\OptionalStorageDecorator" decorates "' . self::STORAGE . '", which no longer exists', PageBuilderAssetUploadStorageInterface::class];
        yield 'tagged Twig context provider without the 2.0 port' => ['TaggedContext', [], null, 'The Twig context provider "' . $fixtures . 'TaggedContext\UntypedContextProvider" (tag "nowo_page_builder_kit.grapes_twig_context", class "' . $fixtures . 'TaggedContext\UntypedContextProvider") does not implement "' . GrapesTwigPageContextProviderInterface::class . '"', GrapesTwigPageContextProviderInterface::class];
        yield 'explicitly tagged Twig context provider on the removed interface' => [null, ['ctx:' . $fixtures . 'ImplementsContext\LegacyContextProvider'], null, 'The Twig context provider "' . $fixtures . 'ImplementsContext\LegacyContextProvider" (tag "nowo_page_builder_kit.grapes_twig_context") cannot be loaded', GrapesTwigPageContextProviderInterface::class];
    }

    /**
     * @param list<string> $explicit
     */
    #[DataProvider('refusedHosts')]
    #[Test]
    public function aHostServiceOnARemovedPortFailsTheBuild(?string $fixtures, array $explicit, ?string $uploadService, string $message, string $replacement): void
    {
        $kernel = new RemovedPortsKernel($fixtures, $explicit, $uploadService);

        try {
            $kernel->boot();
            self::fail('The container build must fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertStringContainsString('(deprecated since 1.15) was removed in 2.0', $exception->getMessage());
            self::assertStringContainsString('implement "' . $replacement . '"', $exception->getMessage());
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * `storage: service` naming a service that is not on the 2.0 port: a build error, never a fallback to another
     * storage nor a TypeError at the first upload.
     *
     * @return iterable<string, array{list<string>, string, string}>
     */
    public static function refusedStorages(): iterable
    {
        $legacy = LegacyAssetStorage::class;

        yield 'a loadable class without the port' => [[PlainStorage::class], PlainStorage::class, 'which does not implement "' . PageBuilderAssetUploadStorageInterface::class . '"'];
        yield 'a class on the removed interface (registered by hand)' => [[$legacy], $legacy, 'which cannot be loaded: the class or one of its interfaces is missing'];
    }

    /**
     * @param list<string> $explicit
     */
    #[DataProvider('refusedStorages')]
    #[Test]
    public function aConfiguredStorageOffThePortFailsTheBuild(array $explicit, string $service, string $message): void
    {
        $kernel = new RemovedPortsKernel(null, $explicit, $service);

        try {
            $kernel->boot();
            self::fail('The container build must fail.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('nowo_page_builder_kit.grapesjs.assets_upload.service is "' . $service . '"', $exception->getMessage());
            self::assertStringContainsString($message, $exception->getMessage());
            self::assertStringContainsString('"' . self::STORAGE . '" (deprecated since 1.15) was removed', $exception->getMessage());
        } finally {
            $kernel->shutdown();
        }
    }

    /**
     * Only the exact class name of a removed interface matches: an unused class implementing another missing interface
     * (whose name merely starts like it) keeps its lazy loading error and the container compiles.
     */
    #[Test]
    public function anUnrelatedMissingInterfaceStillCompiles(): void
    {
        $kernel = new RemovedPortsKernel('Unrelated');

        try {
            $kernel->boot();
            self::assertFalse($kernel->getContainer()->has(OtherMissingInterfaceUser::class));
            self::assertSame('test', $kernel->getContainer()->getParameter('kernel.environment'));
        } finally {
            $kernel->shutdown();
        }
    }
}
