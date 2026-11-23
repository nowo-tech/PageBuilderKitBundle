<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use ArrayIterator;
use DateTimeImmutable;
use InvalidArgumentException;
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
use Nowo\PageBuilderKitBundle\Service\GrapesTwigPageContextProviderInterface;
use Nowo\PageBuilderKitBundle\Service\GrapesTwigRenderer;
use Nowo\PageBuilderKitBundle\Service\PageContext;
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;
use Nowo\PageBuilderKitBundle\Service\PageSeoBuilder;
use Nowo\PageBuilderKitBundle\Service\WidgetPropsMerger;
use Nowo\PageBuilderKitBundle\Tests\Support\WidgetTypesFixture;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use stdClass;

/**
 * 1.15 (REQ-ARCH-001 rule 1): a Twig context provider on the entity-free {@see GrapesTwigPageContextProviderInterface}
 * gets the page's ids, key, status, publication date and the title and slug of the rendered locale. 2.0: it is the only
 * Twig context provider port (the entity-based `GrapesTwigContextProviderInterface` was removed): any other object is
 * refused.
 */
#[CoversClass(PageRenderProvider::class)]
#[CoversClass(PageContext::class)]
final class PageContextProviderEquivalenceTest extends TestCase
{
    private const string HTML = '<p>{{ owner }}|{{ ref }}|{{ state }}|{{ when }}|{{ heading }}|{{ path }}|{{ lang }}</p>';

    #[Test]
    public function theProviderGetsThePageData(): void
    {
        $page = $this->page();
        $new  = new class implements GrapesTwigPageContextProviderInterface {
            public function getPageContext(PageContext $page, string $locale): array
            {
                return [
                    'owner'   => (string) $page->id,
                    'ref'     => $page->pageKey . '/' . $page->uuid,
                    'state'   => $page->status->value,
                    'when'    => $page->publishedAt?->format('Y-m-d') ?? '',
                    'heading' => $page->title ?? '',
                    'path'    => $page->slug ?? '',
                    'lang'    => $locale,
                ];
            }
        };

        self::assertStringContainsString('<p>42|home/' . $page->getUuid() . '|published|2026-11-12|Inicio|inicio|es</p>', $this->render($page, $new, 'es'));
        // No translation for "en": no title / slug.
        self::assertStringContainsString('|published|2026-11-12|||en</p>', $this->render($page, $new, 'en'));
    }

    #[Test]
    public function aProviderOffThePortIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('A Twig context provider must implement "' . GrapesTwigPageContextProviderInterface::class . '"');

        $this->render($this->page(), new stdClass(), 'es');
    }

    private function page(): BuilderPage
    {
        $page = new BuilderPage()
            ->setPageKey('home')
            ->setStatus(PageStatus::Published)
            ->setPublishedAt(new DateTimeImmutable('2026-11-12T08:00:00+00:00'));
        new ReflectionProperty(BuilderPage::class, 'id')->setValue($page, 42);
        $page->addTranslation(new BuilderPageTranslation()->setLocale('es')->setTitle('Inicio')->setSlug('inicio'));
        $page->setDocument(new BuilderDocument()->setPage($page)->setStructure([
            'version'       => DocumentNormalizer::GRAPES_SCHEMA_VERSION,
            'engine'        => DocumentNormalizer::ENGINE_GRAPESJS,
            'html'          => self::HTML,
            'css'           => '',
            'grapes'        => [],
            'localeContent' => [],
            'sections'      => [],
        ]));

        return $page;
    }

    private function render(BuilderPage $page, object $contextProvider, string $locale): string
    {
        $provider = new PageRenderProvider(
            new readonly class($page) implements BuilderPageRepositoryInterface {
                public function __construct(private BuilderPage $page)
                {
                }

                public function findOneByPageKey(string $pageKey): BuilderPage
                {
                    return $this->page;
                }

                public function findAllOrdered(): array
                {
                    return [];
                }
            },
            new DocumentNormalizer(),
            new BuilderLocales('es', ['es', 'en']),
            WidgetTypesFixture::registry(),
            new PageBuilderProtection(new PageBuilderProtectionConfig(HtmlSanitizeStrategy::None, null)),
            new WidgetPropsMerger(),
            new GrapesDocumentSanitizer(),
            new GrapesTwigRenderer(true, false, new GrapesDocumentSanitizer()),
            new PageSeoBuilder('Demo', '', 'https://example.com'),
            new ArrayIterator([$contextProvider]), // @phpstan-ignore argument.type (a provider off the port, on purpose)
        );

        return (string) $provider->getRenderedTree('home', $locale)['html'];
    }
}
