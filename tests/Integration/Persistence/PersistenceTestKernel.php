<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Persistence;

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
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

use function md5;
use function serialize;
use function sys_get_temp_dir;

/**
 * Host kernel for the rule 6 tests (1.14): real DoctrineBundle on SQLite files, configurable managers and bundle
 * `doctrine` options. Routes: `/t/create/{key}` (key-based write port), `/t/trace/{user}` (debug
 * trace of the request) and `/t/fail` (failing bundle write). `$debugCollector` boots in debug with the trace on.
 */
final class PersistenceTestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * @param array<string, mixed> $doctrine host `doctrine` config
     * @param array<string, mixed> $bundleDoctrine `nowo_page_builder_kit.doctrine`
     */
    public function __construct(
        private readonly array $doctrine,
        private readonly array $bundleDoctrine = [],
        private readonly bool $revisions = false,
        private readonly bool $debugCollector = false,
    ) {
        parent::__construct('test', $debugCollector);
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
        return sys_get_temp_dir() . '/nowo-page-builder-kit-persistence';
    }

    public function getCacheDir(): string
    {
        return $this->getProjectDir() . '/' . md5(serialize([$this->doctrine, $this->bundleDoctrine, $this->revisions, $this->debugCollector])) . '/cache';
    }

    public function getLogDir(): string
    {
        return $this->getProjectDir() . '/log';
    }

    private function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret'               => 'persistence-test-secret',
            'test'                 => true,
            'http_method_override' => false,
            'router'               => ['utf8' => true],
            'csrf_protection'      => true,
            'form'                 => ['csrf_protection' => true],
            'session'              => ['storage_factory_id' => 'session.storage.factory.mock_file'],
            'default_locale'       => 'en',
            'translator'           => ['fallbacks' => ['en']],
        ]);
        $container->extension('security', [
            'firewalls' => ['main' => ['security' => false]],
        ]);
        $container->extension('doctrine', $this->doctrine);
        $container->services()->set('logger', NullLogger::class);
        $container->services()->set(PersistenceTestController::class)
            ->autowire()
            ->public()
            ->tag('controller.service_arguments');
        $container->extension('nowo_page_builder_kit', [
            'web_ui'    => ['path_prefix' => '/admin/page-builder'],
            'doctrine'  => $this->bundleDoctrine,
            'revisions' => ['enabled' => $this->revisions],
            'debug'     => ['collector' => $this->debugCollector],
        ]);
    }

    private function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->add('t_create', '/t/create/{key}')->controller([PersistenceTestController::class, 'create']);
        $routes->add('t_commit_fail', '/t/commit-then-fail/{key}')->controller([PersistenceTestController::class, 'commitThenFail']);
        $routes->add('t_trace', '/t/trace/{user}')->controller([PersistenceTestController::class, 'trace']);
        $routes->add('t_fail', '/t/fail')->controller([PersistenceTestController::class, 'fail']);
    }
}
