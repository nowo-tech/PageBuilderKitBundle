<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PageBuilderKitBundle\Media\AssetUploadHandler;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetStorageInterface;
use Nowo\PageBuilderKitBundle\Media\PageBuilderAssetUploadStorageInterface;
use Nowo\PageBuilderKitBundle\NowoPageBuilderKitBundle;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DddHost\MediaLibrary;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DddHost\MediaLibraryAssetStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DecoratingUploadStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\DualHostStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\HostUploadStorage;
use Nowo\PageBuilderKitBundle\Tests\Integration\AssetPorts\Fixtures\LegacyHostStorage;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\PassConfig;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function getmypid;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function sys_get_temp_dir;

/**
 * Host kernel that wires the asset storage ports the way a host would (1.15):
 *
 * - `local` / `s3` (built-in storages); `new` / `legacy` / `both`: the host aliases the 1.15 port, the deprecated port,
 *   or both; `decorator`: a decorator of the 1.15 port over the built-in storage;
 * - `service-new` / `service-legacy` / `service-both`: `storage: service` with such a service;
 * - `ddd`: `storage: service` with a DDD host adapter of its own domain service.
 */
final class AssetPortsKernel extends Kernel
{
    use MicroKernelTrait;

    public const string PROBE = 'test.pbk_asset_ports';

    public function __construct(private readonly string $variant)
    {
        parent::__construct('test', false);
    }

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new SecurityBundle();
        yield new DoctrineBundle();
        yield new NowoUiKitBundle();
        yield new NowoFormKitBundle();
        yield new NowoPageBuilderKitBundle();
    }

    public function getProjectDir(): string
    {
        return self::baseDir();
    }

    public function getCacheDir(): string
    {
        return self::baseDir() . '/' . $this->variant . '/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir() . '/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir() . '/nowo-page-builder-kit-asset-ports/' . getmypid();
    }

    protected function build(ContainerBuilder $container): void
    {
        // Records where both port aliases point (and whether they are deprecated) before private aliases are removed.
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                foreach (['new' => PageBuilderAssetUploadStorageInterface::class, 'legacy' => PageBuilderAssetStorageInterface::class] as $key => $port) {
                    $value = null;
                    if ($container->hasAlias($port)) {
                        $alias = $container->getAlias($port);
                        $id    = (string) $alias;
                        while ($container->hasAlias($id)) {
                            $id = (string) $container->getAlias($id);
                        }
                        $value = [$container->findDefinition($id)->getClass(), $alias->isDeprecated()];
                    }
                    $container->setParameter('test.pbk_port.' . $key, $value);
                }
            }
        }, PassConfig::TYPE_BEFORE_REMOVING);
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'asset-ports-test',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'default_locale'       => 'en',
        ]);
        $container->extension('security', ['firewalls' => ['main' => ['security' => false]]]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///%kernel.cache_dir%/default.sqlite'],
            'orm'  => [],
        ]);

        $upload = ['enabled' => true, 'local' => ['directory' => self::baseDir() . '/uploads', 'public_prefix' => '/uploads']];
        $upload += match ($this->variant) {
            's3'             => ['storage' => 's3', 's3' => ['helper_service' => 'test.s3_helper']],
            'service-new'    => ['storage' => 'service', 'service' => HostUploadStorage::class],
            'service-legacy' => ['storage' => 'service', 'service' => LegacyHostStorage::class],
            'service-both'   => ['storage' => 'service', 'service' => DualHostStorage::class],
            'ddd'            => ['storage' => 'service', 'service' => MediaLibraryAssetStorage::class],
            default          => [],
        };
        $container->extension('nowo_page_builder_kit', [
            'web_ui'   => ['path_prefix' => '/admin/page-builder'],
            'grapesjs' => ['assets_upload' => $upload],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        $services->set('test.s3_helper', S3HelperStub::class);
        $services->set(HostUploadStorage::class);
        $services->set(LegacyHostStorage::class);
        $services->set(DualHostStorage::class);
        $services->set(MediaLibrary::class)->public();
        $services->set(MediaLibraryAssetStorage::class)->autowire();

        match ($this->variant) {
            'new'    => $services->alias(PageBuilderAssetUploadStorageInterface::class, HostUploadStorage::class),
            'legacy' => $services->alias(PageBuilderAssetStorageInterface::class, LegacyHostStorage::class),
            'both'   => [
                $services->alias(PageBuilderAssetUploadStorageInterface::class, DualHostStorage::class),
                $services->alias(PageBuilderAssetStorageInterface::class, DualHostStorage::class),
            ],
            'decorator' => $services->set(DecoratingUploadStorage::class)
                ->decorate(PageBuilderAssetUploadStorageInterface::class)
                ->args([service('.inner')]),
            default => null,
        };

        // Probe: the handler (its storage is the port the bundle resolved).
        $services->set(self::PROBE, ServiceLocator::class)
            ->args([[AssetUploadHandler::class => service(AssetUploadHandler::class)]])
            ->tag('container.service_locator')
            ->public();
    }
}
