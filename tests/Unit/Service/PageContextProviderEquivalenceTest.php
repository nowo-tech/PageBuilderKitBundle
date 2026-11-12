<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Service;

use ArrayIterator;
use DateTimeImmutable;
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

/**
 * 1.15 (REQ-ARCH-001 rule 1): a Twig context provider on the entity-free {@see GrapesTwigPageContextProviderInterface}
 * renders the same HTML as one on the deprecated entity port, from the same page data; a provider implementing both
 * gets only the new method called.
 */
#[CoversClass(PageRenderProvider::class)]
#[CoversClass(PageContext::class)]
final class PageContextProviderEquivalenceTest extends TestCase
{
    private const string HTML = '<p>{{ owner }}|{{ ref }}|{{ state }}|{{ when }}|{{ heading }}|{{ path }}|{{ lang }}</p>';

    #[Test]
    public function newProviderRendersLikeTheDeprecatedOne(): void
    {
        $page = $this->page();

        $legacy = new class implements GrapesTwigContextProviderInterface {
            public function getContext(BuilderPage $page, string $locale): array
            {
                $translation = $page->getTranslation($locale);

                return [
                    'owner'   => (string) $page->getId(),
                    'ref'     => $page->getPageKey() . '/' . $page->getUuid(),
                    'state'   => $page->getStatus()->value,
                    'when'    => $page->getPublishedAt()?->format('Y-m-d') ?? '',
                    'heading' => $translation?->getTitle() ?? '',
                    'path'    => $translation?->getSlug() ?? '',
                    'lang'    => $locale,
                ];
            }
        };
        $new = new class implements GrapesTwigPageContextProviderInterface {
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

        foreach (['es', 'en'] as $locale) {
            $old = $this->render($page, $legacy, $locale);
            self::assertSame($old, $this->render($page, $new, $locale), $locale);
        }

        self::assertStringContainsString('<p>42|home/', $this->render($page, $new, 'es'));
        self::assertStringContainsString('|published|2026-11-12|Inicio|inicio|es</p>', $this->render($page, $new, 'es'));
        // No translation for "en": no title / slug, on both ports.
        self::assertStringContainsString('|published|2026-11-12|||en</p>', $this->render($page, $new, 'en'));
    }

    #[Test]
    public function providerImplementingBothGetsOnlyTheNewMethod(): void
    {
        $both = new class implements GrapesTwigContextProviderInterface, GrapesTwigPageContextProviderInterface {
            public function getContext(BuilderPage $page, string $locale): array
            {
                return ['owner' => 'legacy'];
            }

            public function getPageContext(PageContext $page, string $locale): array
            {
                return ['owner' => 'new'];
            }
        };

        self::assertStringContainsString('<p>new|', $this->render($this->page(), $both, 'es'));
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

    private function render(BuilderPage $page, GrapesTwigContextProviderInterface|GrapesTwigPageContextProviderInterface $contextProvider, string $locale): string
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
            new ArrayIterator([$contextProvider]),
        );

        return (string) $provider->getRenderedTree('home', $locale)['html'];
    }
}
