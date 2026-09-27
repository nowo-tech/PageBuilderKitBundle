<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocumentLocale;
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
        $page->getDocument()?->getLocales()->add(
            (new BuilderDocumentLocale())
                ->setLocale('es')
                ->setWidgetProps(['hero' => ['title' => 'Plan']])
                ->setDocument($page->getDocument()),
        );

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
        self::assertSame(['hero' => ['title' => 'Plan']], $saved->getWidgetPropsByLocale()['es']);
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

    #[Test]
    public function saveFromPageRejectsBlankLabelAndMissingDocument(): void
    {
        $templateRepo = $this->createStub(BuilderPageTemplateRepositoryInterface::class);
        $documents    = $this->documents();
        $service      = new PageTemplateService(
            $templateRepo,
            $this->createStub(EntityManagerInterface::class),
            $documents,
            new DocumentNormalizer(),
        );

        $page = (new BuilderPage())->setPageKey('pricing');
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Plan</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]));

        try {
            $service->saveFromPage($page, 'pricing-tpl', '   ');
            self::fail('Expected blank label validation to fail.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Template label is required.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $service->saveFromPage((new BuilderPage())->setPageKey('missing'), 'pricing-tpl', 'Pricing');
    }

    #[Test]
    public function createPageFromTemplateRejectsUnknownTemplate(): void
    {
        $service = new PageTemplateService(
            $this->createStub(BuilderPageTemplateRepositoryInterface::class),
            $this->createStub(EntityManagerInterface::class),
            $this->documents(),
            new DocumentNormalizer(),
        );

        $this->expectException(InvalidArgumentException::class);
        $service->createPageFromTemplate('unknown', 'page', 'Title', 'es');
    }

    #[Test]
    public function createPageFromTemplateFiltersNonArrayWidgetPropsAndDeleteRejectsUnknown(): void
    {
        $template = (new BuilderPageTemplate())
            ->setTemplateKey('pricing-tpl')
            ->setLabel('Pricing')
            ->setStructure([
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '<p>Plan</p>',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
            ])
            ->setWidgetPropsByLocale([
                'es'   => ['hero' => ['title' => 'Hola']],
                'skip' => 'bad',
            ]);

        $templateRepo = new class($template) implements BuilderPageTemplateRepositoryInterface {
            public function __construct(private readonly BuilderPageTemplate $template)
            {
            }

            public function findOneByTemplateKey(string $templateKey): ?BuilderPageTemplate
            {
                return $templateKey === 'pricing-tpl' ? $this->template : null;
            }

            public function findAllOrdered(): array
            {
                return [$this->template];
            }
        };

        $service = new PageTemplateService(
            $templateRepo,
            $this->createStub(EntityManagerInterface::class),
            $this->documents(),
            new DocumentNormalizer(),
        );

        $page = $service->createPageFromTemplate('pricing-tpl', 'pricing-copy', 'Copy', 'es');

        self::assertSame('pricing-copy', $page->getPageKey());
        self::assertNotNull($page->getDocument());

        $this->expectException(InvalidArgumentException::class);
        $service->delete('missing');
    }

    private function documents(): DocumentService
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

        return new DocumentService(
            $pages,
            $this->createStub(EntityManagerInterface::class),
            new BuilderLocales('es', ['es', 'en']),
            new DocumentNormalizer(),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
        );
    }
}
