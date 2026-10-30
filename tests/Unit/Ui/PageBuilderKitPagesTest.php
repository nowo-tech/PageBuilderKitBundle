<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Ui;

use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\IgnoreDeprecations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(PageBuilderKitPages::class)]
final class PageBuilderKitPagesTest extends TestCase
{
    #[Test]
    public function labelUsesTheBundleDomain(): void
    {
        $label = PageBuilderKitPages::label('admin.pages.title', ['%x%' => 1]);

        self::assertSame('admin.pages.title', $label->key);
        self::assertSame('NowoPageBuilderKitBundle', $label->domain);
        self::assertSame(['%x%' => 1], $label->params);
    }

    #[Test]
    public function templateResolvesOverridesThenDefaults(): void
    {
        $pages = new PageBuilderKitPages(null, ['index' => 'host/index.html.twig', 'seo' => '']);

        self::assertSame('host/index.html.twig', $pages->template('index'));
        self::assertSame(PageBuilderKitPages::TEMPLATES['seo'], $pages->template('seo'));
        self::assertSame(PageBuilderKitPages::TEMPLATES['canvas'], $pages->template('canvas'));
        self::assertSame('unknown', $pages->template('unknown'));
    }

    #[Test]
    public function nullOrLegacyDefaultLayoutMeansTheUiKitLayout(): void
    {
        foreach ([null, '', PageBuilderKitPages::LEGACY_DEFAULT_LAYOUT] as $layout) {
            $pages = new PageBuilderKitPages($layout, [], $this->twig());

            self::assertNull($pages->layout());
            self::assertSame(PageBuilderKitPages::LEGACY_DEFAULT_LAYOUT, $pages->legacyLayoutTemplate());
            self::assertSame(['a' => 1, 'nowo_page_builder_legacy_layout' => null, 'nowo_page_builder_legacy_blocks' => []], $pages->context(['a' => 1]));
        }
    }

    #[Test]
    public function aCanonicalLayoutIsUsedAsIs(): void
    {
        $pages = new PageBuilderKitPages('canonical.html.twig', [], $this->twig());

        self::assertSame('canonical.html.twig', $pages->layout());
        self::assertSame('canonical.html.twig', $pages->legacyLayoutTemplate());
        self::assertNull($pages->context([])['nowo_page_builder_legacy_layout']);
    }

    #[Test]
    public function withoutTwigOrWithAMissingLayoutTheConfiguredLayoutIsUsed(): void
    {
        self::assertSame('legacy.html.twig', new PageBuilderKitPages('legacy.html.twig')->layout());
        self::assertSame([], new PageBuilderKitPages('legacy.html.twig')->context([])['nowo_page_builder_legacy_blocks']);
        self::assertSame('missing.html.twig', new PageBuilderKitPages('missing.html.twig', [], $this->twig())->layout());
    }

    #[Test]
    #[IgnoreDeprecations]
    public function aOneXLayoutIsWrappedInTheDeprecatedBridge(): void
    {
        $pages = new PageBuilderKitPages('legacy.html.twig', [], $this->twig());

        $this->expectUserDeprecationMessageMatches('/The layout "legacy\.html\.twig" does not render the UiKit canonical blocks/');
        self::assertSame(PageBuilderKitPages::LEGACY_ADMIN_BRIDGE, $pages->layout());
        self::assertSame(['nowo_page_builder_legacy_layout' => 'legacy.html.twig', 'nowo_page_builder_legacy_blocks' => []], $pages->context([]));
    }

    #[Test]
    public function aCopyOfTheOneXBundleLayoutReportsTheCanonicalBlocksItRenders(): void
    {
        $pages = new PageBuilderKitPages('copy.html.twig', [], $this->twig());

        self::assertSame(['nowo_ui_flashes', 'nowo_ui_scripts'], $pages->context([])['nowo_page_builder_legacy_blocks']);
    }

    private function twig(): Environment
    {
        return new Environment(new ArrayLoader([
            'canonical.html.twig' => '{% block nowo_ui_page_header %}{% endblock %}{% block nowo_ui_content %}{% endblock %}',
            'legacy.html.twig'    => '<main>{% block admin %}{% endblock %}</main>',
            'copy.html.twig'      => '{% block nowo_ui_flashes %}{% endblock %}{% block admin %}{% endblock %}{% block nowo_ui_scripts %}{% endblock %}',
        ]));
    }
}
