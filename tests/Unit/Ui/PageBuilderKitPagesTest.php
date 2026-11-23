<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Unit\Ui;

use LogicException;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use PHPUnit\Framework\Attributes\CoversClass;
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
            self::assertSame(['a' => 1], $pages->context(['a' => 1]), 'no shared variable since 2.0');
        }
    }

    #[Test]
    public function aCanonicalLayoutIsUsedAsIs(): void
    {
        $pages = new PageBuilderKitPages('canonical.html.twig', [], $this->twig());

        self::assertSame('canonical.html.twig', $pages->layout());
        self::assertSame([], $pages->context([]));
    }

    #[Test]
    public function withoutTwigOrWithAMissingLayoutTheConfiguredLayoutIsUsed(): void
    {
        self::assertSame('legacy.html.twig', new PageBuilderKitPages('legacy.html.twig')->layout());
        self::assertSame('missing.html.twig', new PageBuilderKitPages('missing.html.twig', [], $this->twig())->layout());
    }

    /**
     * 2.0: the 1.x layout bridge (deprecated since 1.12) was removed: a layout without the UiKit canonical blocks fails
     * with a message that says what to do, never a silent fallback.
     */
    #[Test]
    public function aOneXLayoutFailsClearly(): void
    {
        foreach (['legacy.html.twig', 'copy.html.twig'] as $layout) {
            try {
                new PageBuilderKitPages($layout, [], $this->twig())->layout();
                self::fail('A 1.x layout must fail.');
            } catch (LogicException $exception) {
                self::assertStringContainsString('The layout "' . $layout . '" (nowo_page_builder_kit.web_ui.layout_template) does not render the UiKit canonical blocks', $exception->getMessage());
                self::assertStringContainsString('The 1.x layout bridge (deprecated since 1.12) was removed in 2.0', $exception->getMessage());
            }
        }
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
