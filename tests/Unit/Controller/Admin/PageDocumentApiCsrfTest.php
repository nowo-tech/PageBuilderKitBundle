<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface;
use Nowo\PageBuilderKitBundle\Controller\Admin\PageDocumentApiController;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\AllowAllPageBuilderKitAccessChecker;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessGuard;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentImportExportService;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

use const JSON_THROW_ON_ERROR;

/** CSRF contract for document save (403 without token, 200 with valid token) and import capability checks. */
final class PageDocumentApiCsrfTest extends TestCase
{
    #[Test]
    public function saveRejectsMissingCsrf(): void
    {
        $controller = $this->controller(csrfValid: false);
        $request    = Request::create('/admin/page-builder/pages/home/document', 'POST', content: json_encode([
            'structure'           => ['version' => 2, 'engine' => 'grapesjs', 'html' => '', 'css' => '', 'grapes' => [], 'localeContent' => [], 'sections' => []],
            'widgetPropsByLocale' => ['es' => []],
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('Accept', 'application/json');

        $response = $controller->saveDocument('home', $request);

        self::assertSame(403, $response->getStatusCode());
        self::assertSame(['error' => 'invalid_csrf'], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function saveAcceptsValidCsrfHeader(): void
    {
        $controller = $this->controller(csrfValid: true);
        $request    = Request::create('/admin/page-builder/pages/home/document', 'POST', content: json_encode([
            'structure'           => ['version' => 2, 'engine' => 'grapesjs', 'html' => '<p>Hi</p>', 'css' => '', 'grapes' => [], 'localeContent' => [], 'sections' => []],
            'widgetPropsByLocale' => ['es' => [], 'en' => []],
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('X-CSRF-TOKEN', 'ok');
        $request->headers->set('Accept', 'application/json');

        $response = $controller->saveDocument('home', $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['ok' => true], json_decode((string) $response->getContent(), true));
    }

    #[Test]
    public function importRequiresLayoutCapability(): void
    {
        $controller = $this->controller(csrfValid: true, checker: $this->checker(layout: false, publish: true));

        $this->expectException(AccessDeniedException::class);
        $controller->import($this->importRequest(publish: false));
    }

    #[Test]
    public function importWithPublishRequiresPublishCapability(): void
    {
        $controller = $this->controller(csrfValid: true, checker: $this->checker(layout: true, publish: false));

        $this->expectException(AccessDeniedException::class);
        $controller->import($this->importRequest(publish: true));
    }

    #[Test]
    public function importWithoutPublishOnlyNeedsLayout(): void
    {
        $controller = $this->controller(csrfValid: true, checker: $this->checker(layout: true, publish: false));

        $response = $controller->import($this->importRequest(publish: false));

        // Past the capability checks: the empty payload is rejected by the import service.
        self::assertSame(400, $response->getStatusCode());
    }

    private function importRequest(bool $publish): Request
    {
        $request = Request::create('/admin/page-builder/pages/import', 'POST', content: json_encode([
            'targetPageKey' => 'home',
            'publish'       => $publish,
        ], JSON_THROW_ON_ERROR));
        $request->headers->set('X-CSRF-TOKEN', 'ok');
        $request->headers->set('Accept', 'application/json');

        return $request;
    }

    private function checker(bool $layout, bool $publish): PageBuilderKitAccessCheckerInterface
    {
        $checker = $this->createStub(PageBuilderKitAccessCheckerInterface::class);
        $checker->method('canAccess')->willReturn(true);
        $checker->method('canLayout')->willReturn($layout);
        $checker->method('canPublish')->willReturn($publish);
        $checker->method('canTemplates')->willReturn(true);

        return $checker;
    }

    private function controller(bool $csrfValid, ?PageBuilderKitAccessCheckerInterface $checker = null): PageDocumentApiController
    {
        $page = new BuilderPage()->setPageKey('home')->setStatus(PageStatus::Draft);
        $doc  = new BuilderDocument()->setPage($page)->setStructure([
            'version'       => 2,
            'engine'        => 'grapesjs',
            'html'          => '',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ]);
        $page->setDocument($doc);

        $repo = new readonly class($page) implements BuilderPageRepositoryInterface {
            public function __construct(private BuilderPage $page)
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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist');
        $em->method('flush');

        $documents = new DocumentService(
            $repo,
            $em,
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(
                new PageBuilderProtectionConfig(HtmlSanitizeStrategy::Allowlist, null),
            ),
            new WidgetPropsMerger(),
        );

        $csrf = $this->createStub(CsrfTokenManagerInterface::class);
        $csrf->method('isTokenValid')->willReturnCallback(
            static fn (CsrfToken $token): bool => $csrfValid && $token->getValue() === 'ok',
        );

        $importExport = new DocumentImportExportService(
            $documents,
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
        );

        return new PageDocumentApiController(
            $repo,
            $documents,
            new DocumentNormalizer(),
            $csrf,
            $importExport,
            new PageBuilderKitAccessGuard($checker ?? new AllowAllPageBuilderKitAccessChecker()),
        );
    }
}
