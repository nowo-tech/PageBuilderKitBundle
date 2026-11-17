<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Http;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PageBuilderKitBundle\NowoPageBuilderKitBundle;
use Nowo\UiKitBundle\NowoUiKitBundle;
use Psr\Log\NullLogger;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function md5;
use function serialize;
use function sys_get_temp_dir;

/**
 * Host kernel for the HTTP tests of the admin and public controllers (REQ-TEST-003): real routes, SQLite, a CSRF double.
 * One cache directory and one database file per configuration and process.
 */
final class AdminHttpKernel extends Kernel
{
    use MicroKernelTrait;

    private readonly string $key;

    /**
     * @param array<string, mixed> $bundle `nowo_page_builder_kit` configuration merged over the test defaults
     */
    public function __construct(private readonly array $bundle = [], private readonly string $name = 'default')
    {
        $this->key = md5(serialize([$bundle, $name]));
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
        return sys_get_temp_dir() . '/nowo-page-builder-kit-http/' . getmypid();
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . $this->key . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    public function databaseFile(): string
    {
        return $this->getProjectDir() . '/' . $this->key . '/test.sqlite';
    }

    public function uploadDirectory(): string
    {
        return $this->getProjectDir() . '/' . $this->key . '/uploads';
    }

    protected function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new class implements CompilerPassInterface {
            public function process(ContainerBuilder $container): void
            {
                $container->setDefinition('security.csrf.token_manager', new Definition(AcceptingCsrfTokenManager::class));
            }
        });
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'http-test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'default_locale'       => 'en',
            'translator'           => ['fallbacks' => ['en']],
            'messenger'            => ['enabled' => false],
        ]);
        $container->extension('security', ['firewalls' => ['main' => ['security' => false]]]);
        $container->extension('doctrine', [
            'dbal' => ['url' => 'sqlite:///' . $this->databaseFile()],
            'orm'  => ['auto_mapping' => true],
        ]);
        $container->services()->set('logger', NullLogger::class);
        $container->extension('nowo_page_builder_kit', $this->bundle + [
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'security'       => ['allow_unauthenticated' => true],
            'web_ui'         => ['path_prefix' => '/admin/page-builder'],
            'revisions'      => ['enabled' => true],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPageBuilderKitBundle/Resources/config/routing.yaml');
    }
}
