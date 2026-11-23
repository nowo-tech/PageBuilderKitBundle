<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\RemovedPorts;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PageBuilderKitBundle\NowoPageBuilderKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function dirname;
use function getmypid;
use function md5;
use function serialize;
use function str_starts_with;
use function substr;
use function sys_get_temp_dir;

/**
 * Host kernel of the W1-1 tests (2.0): loads `tests/Fixtures/RemovedPorts/<fixtures>/` by resource autodiscovery
 * (autowired, autoconfigured), as a host's `services.yaml` does, plus optional explicitly registered services and a
 * `storage: service` upload configuration.
 */
final class RemovedPortsKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param string|null $fixtures directory under tests/Fixtures/RemovedPorts loaded by autodiscovery
     * @param list<string> $explicit classes registered one by one (no autodiscovery, so no loading error); "ctx:<class>"
     *                               also tags it as a Twig context provider
     * @param string|null $uploadService `grapesjs.assets_upload.service` with `storage: service`
     */
    public function __construct(
        private readonly ?string $fixtures,
        private readonly array $explicit = [],
        private readonly ?string $uploadService = null,
    ) {
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
        return dirname(__DIR__, 3);
    }

    public function getCacheDir(): string
    {
        return self::baseDir() . '/' . md5(serialize([$this->fixtures, $this->explicit, $this->uploadService])) . '/cache';
    }

    public function getLogDir(): string
    {
        return self::baseDir() . '/log';
    }

    public static function baseDir(): string
    {
        return sys_get_temp_dir() . '/nowo-page-builder-kit-removed-ports/' . getmypid();
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'removed-ports-test',
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
        if ($this->uploadService !== null) {
            $upload += ['storage' => 'service', 'service' => $this->uploadService];
        }
        $container->extension('nowo_page_builder_kit', [
            'web_ui'   => ['path_prefix' => '/admin/page-builder'],
            'grapesjs' => ['assets_upload' => $upload],
        ]);

        $services = $container->services();
        $services->set('logger', NullLogger::class);
        foreach ($this->explicit as $class) {
            // "ctx:<class>": tagged as a Twig context provider by hand, as a host's services.yaml can do.
            if (str_starts_with($class, 'ctx:')) {
                $services->set(substr($class, 4))->tag('nowo_page_builder_kit.grapes_twig_context');

                continue;
            }
            $services->set($class);
        }

        if ($this->fixtures !== null) {
            $services->defaults()->autowire()->autoconfigure()
                ->load('Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\RemovedPorts\\' . $this->fixtures . '\\', '%kernel.project_dir%/tests/Fixtures/RemovedPorts/' . $this->fixtures . '/*');
        }
    }
}
