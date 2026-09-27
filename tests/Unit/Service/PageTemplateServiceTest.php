<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTemplate;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageTemplateRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[CoversClass(PageTemplateService::class)]
final class PageTemplateServiceTest extends TestCase
{
    #[Test]
    public function saveFromPageAndApplyRoundTrip(): void
    {
        $page = (new BuilderPage())->setPageKey('pricing');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Plan</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]));

        $templates    = [];
        $templateRepo = new class($templates) implements BuilderPageTemplateRepositoryInterface {
            /** @param array<string, BuilderPageTemplate> $templates */
            public function __construct(private array &$templates)
            {
            }

            public function findOneByTemplateKey(string $templateKey): ?BuilderPageTemplate
            {
                return $this->templates[$templateKey] ?? null;
            }

            public function findAllOrdered(): array
            {
                return array_values($this->templates);
            }

            public function put(BuilderPageTemplate $template): void
            {
                $this->templates[$template->getTemplateKey()] = $template;
            }
        };

        $pages    = [];
        $pageRepo = new class($pages) implements BuilderPageRepositoryInterface {
            /** @param array<string, BuilderPage> $pages */
            public function __construct(private array &$pages)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $this->pages[$pageKey] ?? null;
            }

            public function findAllOrdered(): array
            {
                return array_values($this->pages);
            }

            public function put(BuilderPage $page): void
            {
                $this->pages[$page->getPageKey()] = $page;
            }
        };
        $pageRepo->put($page);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use ($templateRepo, $pageRepo): void {
            if ($entity instanceof BuilderPageTemplate) {
                $templateRepo->put($entity);
            }
            if ($entity instanceof BuilderPage) {
                $pageRepo->put($entity);
            }
        });
        $em->expects(self::atLeastOnce())->method('flush');
        $em->expects(self::once())->method('remove')->willReturnCallback(
            static function (object $entity) use (&$templates): void {
                if ($entity instanceof BuilderPageTemplate) {
                    unset($templates[$entity->getTemplateKey()]);
                }
            },
        );

        $documents = new DocumentService(
            $pageRepo,
            $em,
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );

        $service = new PageTemplateService($templateRepo, $em, $documents, new DocumentNormalizer());

        self::assertSame([], $service->list());

        $saved = $service->saveFromPage($page, 'pricing-tpl', 'Pricing');
        self::assertSame('pricing-tpl', $saved->getTemplateKey());
        self::assertSame($saved, $service->findByKey('pricing-tpl'));
        self::assertCount(1, $service->list());

        $created = $service->createPageFromTemplate('pricing-tpl', 'pricing-copy', 'Copy', 'es');
        self::assertSame('pricing-copy', $created->getPageKey());

        $service->delete('pricing-tpl');
        self::assertNull($service->findByKey('pricing-tpl'));
    }

    #[Test]
    public function saveFromPageRejectsInvalidKey(): void
    {
        $pages = new class implements BuilderPageRepositoryInterface {
            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return null;
            }

            public function findAllOrdered(): array
            {
                return [];
            }
        };

        $documents = new DocumentService(
            $pages,
            $this->createStub(EntityManagerInterface::class),
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );

        $service = new PageTemplateService(
            $this->createStub(BuilderPageTemplateRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $documents,
            new DocumentNormalizer(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->saveFromPage(new BuilderPage(), 'Bad Key!', 'x');
    }
}
