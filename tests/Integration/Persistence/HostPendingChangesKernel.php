<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Nowo\FormKitBundle\NowoFormKitBundle;
use Nowo\PageBuilderKitBundle\NowoPageBuilderKitBundle;
use Nowo\PageBuilderKitBundle\Tests\Integration\Http\AcceptingCsrfTokenManager;
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

use function dirname;
use function md5;
use function serialize;

/**
 * The host of the 2.0 data-loss test ({@see HostPendingChangesTestCase}): a standard Symfony application with ZERO
 * Doctrine configuration for the bundle (no `doctrine` key under `nowo_page_builder_kit`: the 1.x default, in which every
 * page builder write flushed the default manager), its own entities on the default manager (one of them with a
 * many-to-one to a bundle page), the bundle's admin routes, asset uploads on the local disk and revisions on. SQLite in
 * the test's directory, or a real server (the database group). A CSRF double accepts every token but "rejected".
 */
final class HostPendingChangesKernel extends Kernel implements CompilerPassInterface
{
    use MicroKernelTrait;

    public function __construct(private readonly string $directory, private readonly ?string $databaseUrl = null)
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
        return $this->directory;
    }

    public function getCacheDir(): string
    {
        return $this->directory . '/' . md5(serialize([$this->databaseUrl])) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->directory . '/log';
    }

    public function uploadDirectory(): string
    {
        return $this->directory . '/uploads';
    }

    /**
     * The CSRF double; DoctrineBundle's "clear every manager after each Messenger message" subscriber is removed (it
     * clears YOUR manager too, in 1.x as in 2.0: that is the framework, not the bundle).
     */
    public function process(ContainerBuilder $container): void
    {
        $container->setDefinition('security.csrf.token_manager', new Definition(AcceptingCsrfTokenManager::class));
        $container->removeDefinition('doctrine.orm.messenger.event_subscriber.doctrine_clear_entity_manager');
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'host-pending-secret',
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
        $fixtures = dirname(__DIR__, 2) . '/Fixtures';
        $container->extension('doctrine', [
            'dbal' => ['url' => $this->databaseUrl ?? 'sqlite:///' . $this->directory . '/host-pending.sqlite'],
            'orm'  => ['mappings' => [
                'Host' => [
                    'type'      => 'attribute',
                    'dir'       => $fixtures . '/Host',
                    'prefix'    => 'Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\Host',
                    'is_bundle' => false,
                ],
                'HostRef' => [
                    'type'      => 'attribute',
                    'dir'       => $fixtures . '/HostRef',
                    'prefix'    => 'Nowo\\PageBuilderKitBundle\\Tests\\Fixtures\\HostRef',
                    'is_bundle' => false,
                ],
            ]],
        ]);
        $container->services()->set('logger', NullLogger::class);
        // Zero Doctrine configuration for the bundle: no "doctrine" key at all.
        $container->extension('nowo_page_builder_kit', [
            'default_locale' => 'en',
            'locales'        => ['en', 'es'],
            'security'       => ['allow_unauthenticated' => true],
            'web_ui'         => ['path_prefix' => '/admin/page-builder'],
            'revisions'      => ['enabled' => true],
            'grapesjs'       => ['assets_upload' => ['enabled' => true, 'local' => ['directory' => $this->uploadDirectory(), 'public_prefix' => '/uploads']]],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@NowoPageBuilderKitBundle/Resources/config/routing.yaml');
    }
}
