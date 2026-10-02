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
use Nowo\PageBuilderKitBundle\Service\ContentFieldsNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\DocumentService;
use Nowo\PageBuilderKitBundle\Service\PageTemplateService;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function count;

#[CoversClass(PageTemplateService::class)]
final class PageTemplateServiceTest extends TestCase
{
    #[Test]
    public function saveFromPageAndApplyRoundTrip(): void
    {
        $page     = (new BuilderPage())->setPageKey('pricing');
        $document = (new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Plan</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ]);
        $page->setDocument($document);
        $document->getLocales()->add(
            (new BuilderDocumentLocale())
                ->setLocale('es')
                ->setWidgetProps(['hero' => ['title' => 'Plan']])
                ->setDocument($document),
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
    public function exportImportRoundTripSingleAndBundle(): void
    {
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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (BuilderPageTemplate $template) use ($templateRepo): void {
                $templateRepo->put($template);
            },
        );
        $em->expects(self::atLeastOnce())->method('flush');

        $service = new PageTemplateService(
            $templateRepo,
            $em,
            $this->documents(),
            new DocumentNormalizer(),
        );

        $seed = (new BuilderPageTemplate())
            ->setTemplateKey('hero-tpl')
            ->setLabel('Hero')
            ->setStructure([
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '<h1>Hi</h1>',
                'css'           => '.x{}',
                'grapes'        => [],
                'localeContent' => [],
            ])
            ->setWidgetPropsByLocale(['es' => []]);
        $templateRepo->put($seed);

        $single = $service->export('hero-tpl');
        self::assertSame(PageTemplateService::KIND_SINGLE, $single['kind']);
        self::assertSame('hero-tpl', $single['templateKey']);

        $keys = $service->import([
            ...$single,
            'templateKey' => 'hero-imported',
            'label'       => 'Hero imported',
        ]);
        self::assertSame(['hero-imported'], $keys);
        self::assertNotNull($templateRepo->findOneByTemplateKey('hero-imported'));

        $bundle = $service->exportAll();
        self::assertSame(PageTemplateService::KIND_BUNDLE, $bundle['kind']);
        self::assertGreaterThanOrEqual(2, count($bundle['templates']));

        $imported = $service->import([
            'formatVersion' => PageTemplateService::FORMAT_VERSION,
            'kind'          => PageTemplateService::KIND_BUNDLE,
            'templates'     => [
                [
                    'templateKey'         => 'from-bundle',
                    'label'               => 'From bundle',
                    'structure'           => $single['structure'],
                    'widgetPropsByLocale' => [],
                ],
            ],
        ]);
        self::assertSame(['from-bundle'], $imported);
    }

    #[Test]
    public function importRejectsBadFormatAndOverwriteConflict(): void
    {
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

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('persist')->willReturnCallback(
            static function (BuilderPageTemplate $template) use ($templateRepo): void {
                $templateRepo->put($template);
            },
        );
        $em->expects(self::atLeastOnce())->method('flush');

        $service = new PageTemplateService(
            $templateRepo,
            $em,
            $this->documents(),
            new DocumentNormalizer(),
        );

        $structure = [
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
        ];

        $templateRepo->put(
            (new BuilderPageTemplate())
                ->setTemplateKey('exists')
                ->setLabel('Exists')
                ->setStructure($structure),
        );

        try {
            $service->import(['formatVersion' => 99, 'kind' => PageTemplateService::KIND_SINGLE]);
            self::fail('Expected formatVersion rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('formatVersion', $exception->getMessage());
        }

        try {
            $service->export('missing');
            self::fail('Expected unknown export rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Unknown template', $exception->getMessage());
        }

        try {
            $service->import([
                'formatVersion' => PageTemplateService::FORMAT_VERSION,
                'kind'          => PageTemplateService::KIND_BUNDLE,
            ]);
            self::fail('Expected missing templates list rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('templates must be a list', $exception->getMessage());
        }

        try {
            $service->import([
                'formatVersion' => PageTemplateService::FORMAT_VERSION,
                'kind'          => 'other',
            ]);
            self::fail('Expected unsupported kind rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertStringContainsString('Unsupported template kind', $exception->getMessage());
        }

        $service->import([
            'formatVersion' => PageTemplateService::FORMAT_VERSION,
            'kind'          => PageTemplateService::KIND_BUNDLE,
            'templates'     => [
                'skip-me',
                [
                    'templateKey'         => 'exists',
                    'label'               => '',
                    'structure'           => $structure,
                    'widgetPropsByLocale' => ['es' => ['ok' => 1], 'bad' => 'x'],
                ],
            ],
        ]);
        self::assertSame('exists', $templateRepo->findOneByTemplateKey('exists')?->getLabel());

        try {
            $service->import([
                'formatVersion' => PageTemplateService::FORMAT_VERSION,
                'kind'          => PageTemplateService::KIND_SINGLE,
                'templateKey'   => 123,
                'structure'     => $structure,
            ]);
            self::fail('Expected non-string key rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid template key.', $exception->getMessage());
        }

        try {
            $service->import([
                'formatVersion' => PageTemplateService::FORMAT_VERSION,
                'kind'          => PageTemplateService::KIND_SINGLE,
                'templateKey'   => '!!!',
                'structure'     => $structure,
            ]);
            self::fail('Expected invalid key rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('Invalid template key.', $exception->getMessage());
        }

        try {
            $service->import([
                'formatVersion' => PageTemplateService::FORMAT_VERSION,
                'kind'          => PageTemplateService::KIND_SINGLE,
                'templateKey'   => 'no-structure',
            ]);
            self::fail('Expected structure rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('structure must be an object.', $exception->getMessage());
        }

        try {
            $service->import([
                'formatVersion'       => PageTemplateService::FORMAT_VERSION,
                'kind'                => PageTemplateService::KIND_SINGLE,
                'templateKey'         => 'bad-props',
                'structure'           => $structure,
                'widgetPropsByLocale' => 'nope',
            ]);
            self::fail('Expected widgetProps rejection.');
        } catch (InvalidArgumentException $exception) {
            self::assertSame('widgetPropsByLocale must be an object.', $exception->getMessage());
        }

        $this->expectException(InvalidArgumentException::class);
        $service->import([
            'formatVersion'       => PageTemplateService::FORMAT_VERSION,
            'kind'                => PageTemplateService::KIND_SINGLE,
            'templateKey'         => 'exists',
            'label'               => 'Exists',
            'structure'           => $structure,
            'widgetPropsByLocale' => [],
        ], overwrite: false);
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

    #[Test]
    public function createPageFromTemplateRespectsFieldOptions(): void
    {
        $template = (new BuilderPageTemplate())
            ->setTemplateKey('fields-tpl')
            ->setLabel('Fields')
            ->setStructure([
                'version' => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'  => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'    => '<p>{{ fields.hero_title }}</p>',
                'css'     => '',
                'grapes'  => [],
                'fields'  => [
                    ['key' => 'hero_title', 'type' => 'string', 'label' => 'Hero'],
                ],
                'fieldValues' => [
                    'es' => ['hero_title' => 'Hola'],
                ],
            ])
            ->setWidgetPropsByLocale([]);

        $templateRepo = new class($template) implements BuilderPageTemplateRepositoryInterface {
            public function __construct(private readonly BuilderPageTemplate $template)
            {
            }

            public function findOneByTemplateKey(string $templateKey): ?BuilderPageTemplate
            {
                return $templateKey === 'fields-tpl' ? $this->template : null;
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
            new ContentFieldsNormalizer(),
        );

        $schemaOnly = $service->createPageFromTemplate('fields-tpl', 'a', 'A', 'es');
        $structureA = $schemaOnly->getDocument()?->getStructure() ?? [];
        self::assertArrayHasKey('fields', $structureA);
        self::assertSame('hero_title', $structureA['fields'][0]['key']);
        self::assertSame([], $structureA['fieldValues'] ?? []);

        $layoutOnly = $service->createPageFromTemplate('fields-tpl', 'b', 'B', 'es', [
            'include_field_schema' => false,
        ]);
        $structureB = $layoutOnly->getDocument()?->getStructure() ?? [];
        self::assertSame([], $structureB['fields'] ?? ['x']);
        self::assertSame([], $structureB['fieldValues'] ?? ['x']);

        $withValues = $service->createPageFromTemplate('fields-tpl', 'c', 'C', 'es', [
            'include_field_schema' => true,
            'include_field_values' => true,
        ]);
        $structureC = $withValues->getDocument()?->getStructure() ?? [];
        self::assertSame('Hola', $structureC['fieldValues']['es']['hero_title']);
    }

    #[Test]
    public function createPageFromTemplateOptionallyCopiesSeo(): void
    {
        $template = (new BuilderPageTemplate())
            ->setTemplateKey('seo-tpl')
            ->setLabel('SEO')
            ->setStructure([
                'version'             => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'              => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'                => '<p>Hi</p>',
                'css'                 => '',
                'grapes'              => [],
                'fields'              => [],
                'fieldValues'         => [],
                'templateSeoByLocale' => [
                    'es' => [
                        'metaTitle'       => 'Meta ES',
                        'metaDescription' => 'Desc ES',
                        'ogTitle'         => 'OG ES',
                        'ogDescription'   => null,
                        'ogImage'         => null,
                        'canonicalUrl'    => 'https://example.test/es',
                        'robots'          => 'index,follow',
                    ],
                ],
            ])
            ->setWidgetPropsByLocale([]);

        $templateRepo = new class($template) implements BuilderPageTemplateRepositoryInterface {
            public function __construct(private readonly BuilderPageTemplate $template)
            {
            }

            public function findOneByTemplateKey(string $templateKey): ?BuilderPageTemplate
            {
                return $templateKey === 'seo-tpl' ? $this->template : null;
            }

            public function findAllOrdered(): array
            {
                return [$this->template];
            }
        };

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects(self::atLeastOnce())->method('flush');

        $service = new PageTemplateService(
            $templateRepo,
            $em,
            $this->documents(),
            new DocumentNormalizer(),
            new ContentFieldsNormalizer(),
        );

        $without = $service->createPageFromTemplate('seo-tpl', 'no-seo', 'No SEO', 'es');
        self::assertNull($without->getTranslation('es')?->getMetaTitle());

        $with = $service->createPageFromTemplate('seo-tpl', 'with-seo', 'With SEO', 'es', [
            'include_seo' => true,
        ]);
        $seo = $with->getTranslation('es');
        self::assertNotNull($seo);
        self::assertSame('Meta ES', $seo->getMetaTitle());
        self::assertSame('Desc ES', $seo->getMetaDescription());
        self::assertSame('OG ES', $seo->getOgTitle());
        self::assertSame('https://example.test/es', $seo->getCanonicalUrl());
        self::assertSame('index,follow', $seo->getRobots());
        // Live page structure must not keep template-only SEO blob.
        self::assertArrayNotHasKey('templateSeoByLocale', $with->getDocument()?->getStructure() ?? []);
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
