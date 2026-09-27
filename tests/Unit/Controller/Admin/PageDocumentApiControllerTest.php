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
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/** Controllers are outside the coverage whitelist; this exercises the unpublish JSON path. */
final class PageDocumentApiControllerTest extends TestCase
{
    #[Test]
    public function unpublishReturnsDraftStatusJson(): void
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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::once())->method('flush');

        $documents = new DocumentService(
            $repo,
            $em,
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
        );

        $request = Request::create('/admin/page-builder/pages/home/unpublish', 'POST');
        $request->headers->set('X-CSRF-TOKEN', 'ok');
        $request->headers->set('Accept', 'application/json');

        $response = $controller->unpublish('home', $request);

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(
            ['ok' => true, 'status' => 'draft'],
            json_decode((string) $response->getContent(), true),
        );
        self::assertSame(PageStatus::Draft, $page->getStatus());
    }
}
