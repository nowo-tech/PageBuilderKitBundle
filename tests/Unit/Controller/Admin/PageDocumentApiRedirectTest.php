<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Controller\Admin\PageDocumentApiController;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentImportExportService;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Regression: the form `_redirect` field must stay same-origin (no `//host` / `/\host` open redirect). */
final class PageDocumentApiRedirectTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function redirectProvider(): iterable
    {
        yield 'local path' => ['/admin/page-builder', '/admin/page-builder'];
        yield 'protocol relative' => ['//evil.com', '/canvas/home'];
        yield 'backslash' => ['/\\evil.com', '/canvas/home'];
        yield 'tab smuggling' => ["/\t/evil.com", '/canvas/home'];
        yield 'newline' => ["/admin\r\nLocation: //evil.com", '/canvas/home'];
        yield 'absolute url' => ['https://evil.com', '/canvas/home'];
        yield 'empty' => ['', '/canvas/home'];
    }

    #[Test]
    #[DataProvider('redirectProvider')]
    public function unpublishOnlyFollowsSameOriginRedirects(string $redirect, string $expectedLocation): void
    {
        $page = (new BuilderPage())->setPageKey('home')->setStatus(PageStatus::Published);

        $repo = new class($page) implements BuilderPageRepositoryInterface {
            public function __construct(private readonly BuilderPage $page)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $pageKey === $this->page->getPageKey() ? $this->page : null;
            }

            public function findAllOrdered(): array
            {
                return [$this->page];
            }
        };

        $documents = new DocumentService(
            $repo,
            $this->createStub(EntityManagerInterface::class),
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(
                new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
            ),
            new WidgetPropsMerger(),
        );

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $token->getValue() === 'ok',
        );

        $controller = new PageDocumentApiController(
            $repo,
            $documents,
            new DocumentNormalizer(),
            $csrf,
            new DocumentImportExportService($documents, new DocumentNormalizer(), new BuilderLocales('es', ['es', 'en'])),
        );

        $request = Request::create('/admin/page-builder/pages/home/unpublish', 'POST', ['_redirect' => $redirect]);
        $request->headers->set('X-CSRF-TOKEN', 'ok');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $stack = new RequestStack();
        $stack->push($request);

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name, array $params = [], int $type = UrlGeneratorInterface::ABSOLUTE_PATH): string => '/canvas/' . ($params['pageKey'] ?? ''),
        );

        $container = new Container();
        $container->set('request_stack', $stack);
        $container->set('router', $router);
        $controller->setContainer($container);

        $response = $controller->unpublish('home', $request);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($expectedLocation, $response->getTargetUrl());
    }
}
