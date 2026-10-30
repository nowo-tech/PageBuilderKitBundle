<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Routing;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\Loader\AttributeServicesLoader;
use Symfony\Component\Routing\RouterInterface;

use function array_filter;
use function array_keys;
use function array_values;
use function dirname;
use function str_starts_with;

/**
 * REQ-SF-004 rule 7: the bundle routes are opt-in. A host whose routing is only the Symfony default
 * `routing.controllers` resource must not get any bundle route; importing routing.yaml exposes them (under
 * web_ui.path_prefix) and the controllers keep their AbstractController container.
 */
final class BundleRoutesOptInTest extends TestCase
{
    private ?KernelInterface $kernel = null;

    public static function setUpBeforeClass(): void
    {
        new Filesystem()->remove(dirname(new RoutingTestKernel(false)->getCacheDir()));
        new Filesystem()->remove(dirname(new RoutingTestKernel(true)->getCacheDir()));
    }

    protected function tearDown(): void
    {
        $this->kernel?->shutdown();
        $this->kernel = null;
    }

    public function testRoutingControllersResourceDoesNotRegisterBundleRoutes(): void
    {
        if (!class_exists(AttributeServicesLoader::class)) {
            self::markTestSkipped('The routing.controllers resource needs symfony/routing >= 7.3.');
        }

        self::assertSame([], $this->bundleRouteNames(false), 'Bundle routes must exist only where the host imports routing.yaml.');
    }

    public function testImportedRoutesUseThePrefixAndServeAPage(): void
    {
        $names = $this->bundleRouteNames(true);
        self::assertContains('page_builder_public_render', $names);
        self::assertContains('admin_page_builder_list', $names);

        $router = $this->router();
        self::assertSame('/admin/page-builder/pages', $router->getRouteCollection()->get('admin_page_builder_list')?->getPath());
        self::assertSame('/p/{pageKey}', $router->getRouteCollection()->get('page_builder_public_render')?->getPath());

        $testContainer = $this->kernel?->getContainer()->get('test.service_container');
        self::assertInstanceOf(ContainerInterface::class, $testContainer);
        $entityManager = $testContainer->get(EntityManagerInterface::class);
        self::assertInstanceOf(EntityManagerInterface::class, $entityManager);
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
        $page = new BuilderPage()
            ->setPageKey('opt-in')
            ->setUuid('00000000-0000-0000-0000-000000000001')
            ->setStatus(PageStatus::Published);
        $page->setDocument(new BuilderDocument()->setPage($page)->setStructure([]));
        $entityManager->persist($page);
        $entityManager->flush();

        // AbstractController::render() needs the container.service_subscriber locator.
        $response = $this->kernel?->handle(Request::create('/p/opt-in'));
        self::assertNotNull($response);
        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('class="pbk-page"', (string) $response->getContent());
    }

    /** @return list<string> */
    private function bundleRouteNames(bool $importBundleRoutes): array
    {
        $this->kernel = new RoutingTestKernel($importBundleRoutes);
        $this->kernel->boot();

        return array_values(array_filter(
            array_keys($this->router()->getRouteCollection()->all()),
            static fn (string $name): bool => str_starts_with($name, 'admin_page_builder_') || str_starts_with($name, 'page_builder_'),
        ));
    }

    private function router(): RouterInterface
    {
        $router = $this->kernel?->getContainer()->get('router');
        self::assertInstanceOf(RouterInterface::class, $router);

        return $router;
    }
}
