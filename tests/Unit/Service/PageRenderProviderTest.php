<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use ArrayIterator;
use Nowo\PageBuilderKitBundle\Entity\BuilderDocument;
use Nowo\PageBuilderKitBundle\Entity\BuilderPage;
use Nowo\PageBuilderKitBundle\Entity\BuilderPageTranslation;
use Nowo\PageBuilderKitBundle\Enum\HtmlSanitizeStrategy;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Locale\BuilderLocales;
use Nowo\PageBuilderKitBundle\Repository\BuilderPageRepositoryInterface;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtection;
use Nowo\PageBuilderKitBundle\Security\PageBuilderProtectionConfig;
use Nowo\PageBuilderKitBundle\Service\DocumentNormalizer;
use Nowo\PageBuilderKitBundle\Service\GrapesDocumentSanitizer;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigRenderer;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Nowo\PageBuilderKitBundle\Service\PageSeoBuilder;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[CoversClass(PageRenderProvider::class)]
final class PageRenderProviderTest extends TestCase
{
    #[Test]
    public function throwsWhenPageIsUnknown(): void
    {
        $provider = $this->createProvider(null);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unknown page "missing".');

        $provider->getRenderedTree('missing');
    }

    #[Test]
    public function throwsWhenPageHasNoDocument(): void
    {
        $page     = (new BuilderPage())->setPageKey('home');
        $provider = $this->createProvider($page);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Page "home" has no document.');

        $provider->getRenderedTree('home');
    }

    #[Test]
    public function skipsMalformedNodesAndUnknownWidgetTypes(): void
    {
        $page      = (new BuilderPage())->setPageKey('landing');
        $structure = [
            'version'  => DocumentNormalizer::SCHEMA_VERSION,
            'sections' => [
                'bad',
                [
                    'id'      => 'sec',
                    'columns' => [
                        'bad-col',
                        [
                            'id'      => 'col',
                            'widgets' => [
                                'bad-widget',
                                ['id' => 'w1', 'type' => 'unknown'],
                                ['id' => 'w2', 'type' => 'heading'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        $document = (new BuilderDocument())
            ->setPage($page)
            ->setStructure($structure);
        $page->setDocument($document);

        $tree = $this->createProvider($page)->getRenderedTree('landing');

        self::assertCount(1, $tree['sections'][0]['columns']);
        self::assertCount(1, $tree['sections'][0]['columns'][0]['widgets']);
        self::assertSame('w2', $tree['sections'][0]['columns'][0]['widgets'][0]['id']);
    }

    #[Test]
    public function rendersWidgetTreeWithMergedLocaleProps(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('landing')
            ->setStatus(PageStatus::Published);

        $translation = (new BuilderPageTranslation())
            ->setLocale('es')
            ->setTitle('Inicio')
            ->setSlug('inicio');
        $page->addTranslation($translation);

        $structure = (new DocumentNormalizer())->normalize([
            'sections' => [
                [
                    'id'      => 'sec-1',
                    'columns' => [
                        [
                            'id'      => 'col-1',
                            'widgets' => [
                                ['id' => 'w-head', 'type' => 'heading'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);

        $document = (new BuilderDocument())
            ->setPage($page)
            ->setStructure($structure);
        $document->upsertLocale('es', ['w-head' => ['text' => 'Hola', 'tag' => 'h1']]);
        $page->setDocument($document);

        $tree = $this->createProvider($page)->getRenderedTree('landing', 'es');

        self::assertSame('landing', $tree['pageKey']);
        self::assertSame('es', $tree['locale']);
        self::assertSame('published', $tree['status']);
        self::assertSame('Inicio', $tree['title']);
        self::assertCount(1, $tree['sections']);
        self::assertSame('Hola', $tree['sections'][0]['columns'][0]['widgets'][0]['settings']['text']);
        self::assertSame('h1', $tree['sections'][0]['columns'][0]['widgets'][0]['settings']['tag']);
        self::assertSame(
            '@NowoPageBuilderKitBundle/widgets/heading.html.twig',
            $tree['sections'][0]['columns'][0]['widgets'][0]['template'],
        );
    }

    #[Test]
    public function rendersGrapesHtmlAndCssForLocale(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('landing')
            ->setStatus(PageStatus::Published);

        $translation = (new BuilderPageTranslation())
            ->setLocale('es')
            ->setTitle('Inicio')
            ->setSlug('inicio');
        $page->addTranslation($translation);

        $structure = [
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Default</p>',
            'css'           => '.d{}',
            'grapes'        => [],
            'localeContent' => [
                'es' => [
                    'html'   => '<p>Hola</p><script>x</script>',
                    'css'    => '.es{color:red}',
                    'grapes' => [],
                ],
            ],
            'sections' => [],
        ];

        $document = (new BuilderDocument())
            ->setPage($page)
            ->setStructure($structure);
        $page->setDocument($document);

        $tree = $this->createProvider($page)->getRenderedTree('landing', 'es');

        self::assertSame(DocumentNormalizer::ENGINE_GRAPESJS, $tree['engine']);
        self::assertSame('Inicio', $tree['title']);
        self::assertStringContainsString('<p>Hola</p>', $tree['html']);
        self::assertStringNotContainsString('script', strtolower((string) $tree['html']));
        self::assertSame('.es{color:red}', $tree['css']);
        self::assertSame([], $tree['sections']);
    }

    #[Test]
    public function evaluatesTwigVariablesInGrapesHtml(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('twig-demo')
            ->setStatus(PageStatus::Published);
        $page->addTranslation(
            (new BuilderPageTranslation())->setLocale('es')->setTitle('Inicio Twig')->setSlug('inicio-twig'),
        );

        $structure = [
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<h1>{{ title }}</h1><p>{{ locale }} / {{ pageKey }}</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ];
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure($structure));

        $tree = $this->createProvider($page)->getRenderedTree('twig-demo', 'es', ['promo' => 'Summer']);

        self::assertTrue($tree['twigApplied']);
        self::assertNull($tree['twigError']);
        self::assertStringContainsString('Inicio Twig', $tree['html']);
        self::assertStringContainsString('es / twig-demo', $tree['html']);
    }

    #[Test]
    public function exposesSeoPayloadFromTranslation(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('seo-demo')
            ->setStatus(PageStatus::Published);
        $page->addTranslation(
            (new BuilderPageTranslation())
                ->setLocale('en')
                ->setTitle('SEO page')
                ->setSlug('seo-demo')
                ->setMetaTitle('Custom SEO title')
                ->setMetaDescription('Meta description for bots')
                ->setOgImage('https://cdn.example/og.png')
                ->setRobots('index,follow'),
        );
        $page->setDocument(
            (new BuilderDocument())->setPage($page)->setStructure([
                'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
                'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
                'html'          => '<p>Hi</p>',
                'css'           => '',
                'grapes'        => [],
                'localeContent' => [],
                'sections'      => [],
            ]),
        );

        $tree = $this->createProvider($page)->getRenderedTree('seo-demo', 'en');

        self::assertSame('Custom SEO title', $tree['seo']['title']);
        self::assertSame('Meta description for bots', $tree['seo']['description']);
        self::assertSame('https://cdn.example/og.png', $tree['seo']['og']['image']);
        self::assertSame('index,follow', $tree['seo']['robots']);
    }

    #[Test]
    public function mergesTwigContextFromProvidersAndUsesStructureHtmlFallback(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('ctx')
            ->setStatus(PageStatus::Published);
        $page->addTranslation((new BuilderPageTranslation())->setLocale('es')->setTitle('T')->setSlug('t'));
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>Fallback {{ promo }}</p>',
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ]));

        $provider = new PageRenderProvider(
            new class($page) implements BuilderPageRepositoryInterface {
                public function __construct(private readonly BuilderPage $page)
                {
                }

                public function findOneByPageKey(string $pageKey): ?BuilderPage
                {
                    return $pageKey === '__none__' ? null : $this->page;
                }

                public function findAllOrdered(): array
                {
                    return [];
                }
            },
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es']),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
            new GrapesDocumentSanitizer(),
            new GrapesTwigRenderer(true, false, new GrapesDocumentSanitizer()),
            new PageSeoBuilder('Demo', '', 'https://example.com'),
            new ArrayIterator([
                new class implements GrapesTwigContextProviderInterface {
                    public function getContext(BuilderPage $page, string $locale): array
                    {
                        return ['promo' => 'Sale'];
                    }
                },
            ]),
        );

        $tree = $provider->getRenderedTree('ctx', 'en', ['extra' => 1]);
        self::assertStringContainsString('Sale', $tree['html']);
    }

    #[Test]
    public function hardensGrapesHtmlAndCssEvenWhenTwigFails(): void
    {
        $page = (new BuilderPage())
            ->setPageKey('xss')
            ->setStatus(PageStatus::Published);
        $page->addTranslation((new BuilderPageTranslation())->setLocale('es')->setTitle('XSS')->setSlug('xss'));
        $page->setDocument((new BuilderDocument())->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => '<p>ok</p><p>{{ &lt;script&gt;alert(1)&lt;/script&gt; }}</p><a href="data:text/html;base64,PHNjcmlwdD4=">x</a><iframe srcdoc="x"></iframe>',
            'css'           => '.a{color:red}</sty</stylele><img src=x onerror=alert(1)>',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ]));

        $tree = $this->createProvider($page)->getRenderedTree('xss', 'es');

        self::assertFalse($tree['twigApplied']);
        self::assertNotNull($tree['twigError']);
        self::assertStringContainsString('<p>ok</p>', $tree['html']);
        self::assertStringNotContainsStringIgnoringCase('<script', $tree['html']);
        self::assertStringNotContainsString('data:text/html', $tree['html']);
        self::assertStringNotContainsString('srcdoc', $tree['html']);
        self::assertStringNotContainsString('<', $tree['css']);
    }

    private function createProvider(?BuilderPage $page): PageRenderProvider
    {
        $repository = new class($page) implements BuilderPageRepositoryInterface {
            public function __construct(private readonly ?BuilderPage $page)
            {
            }

            public function findOneByPageKey(string $pageKey): ?BuilderPage
            {
                return $pageKey === '__none__' ? null : $this->page;
            }

            public function findAllOrdered(): array
            {
                return [];
            }
        };

        return new PageRenderProvider(
            $repository,
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(
                new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null),
            ),
            new WidgetPropsMerger(),
            new GrapesDocumentSanitizer(),
            new GrapesTwigRenderer(true, false, new GrapesDocumentSanitizer()),
            new PageSeoBuilder('Demo', '', 'https://example.com'),
        );
    }
}
