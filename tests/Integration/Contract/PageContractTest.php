<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Tests\Integration\Contract;

use LogicException;
use Nowo\PageBuilderKitBundle\Enum\PageStatus;
use Nowo\PageBuilderKitBundle\Tests\Support\PageFixtures;
use Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages;
use Nowo\UiKitBundle\Test\PageContractTestCase;
use Nowo\UiKitBundle\Test\PageFixture;

/**
 * REQ-UI-003 page contract: the admin pages extend UiKit scaffolds (list, settings, form, show) or are declared unique
 * (the GrapesJS canvas); no <html>, CDN, inline handler or confirm(); framework classes only through theme slots; the
 * stub theme is reflected; no raw or missing translation keys (strict rule); the CSRF forms of the actions keep their
 * hidden fields. Also renders the 1.x BC surfaces (deprecated layout for page copies, bridge around a 1.x host layout)
 * and checks the bundle-specific markup (dialogs carrying the action fields, canvas toolbar hooks, media library).
 */
final class PageContractTest extends PageContractTestCase
{
    use PageBuilderKitContractEnvironment;

    private const string VIEWS = '@NowoPageBuilderKitBundle/admin/pages/';

    public static function pageFixtures(): iterable
    {
        yield 'pages' => new PageFixture(self::VIEWS . 'index.html.twig', PageFixtures::index());
        yield 'pages (empty)' => new PageFixture(self::VIEWS . 'index.html.twig', PageFixtures::index(true));
        yield 'canvas (draft)' => new PageFixture(self::VIEWS . 'canvas.html.twig', PageFixtures::canvas());
        yield 'canvas (published)' => new PageFixture(self::VIEWS . 'canvas.html.twig', PageFixtures::canvas(PageStatus::Published));
        yield 'content' => new PageFixture(self::VIEWS . 'content.html.twig', PageFixtures::content());
        yield 'content (no fields, no media)' => new PageFixture(self::VIEWS . 'content.html.twig', PageFixtures::content(false, false));
        yield 'seo' => new PageFixture(self::VIEWS . 'seo.html.twig', PageFixtures::seo());
        yield 'sections' => new PageFixture(self::VIEWS . 'sections.html.twig', PageFixtures::sections());
        yield 'sections (empty, published)' => new PageFixture(self::VIEWS . 'sections.html.twig', PageFixtures::sections(true, PageStatus::Published));
        yield 'revisions' => new PageFixture(self::VIEWS . 'revisions.html.twig', PageFixtures::revisions());
        yield 'revisions (empty)' => new PageFixture(self::VIEWS . 'revisions.html.twig', PageFixtures::revisions(true));
        yield 'revision diff' => new PageFixture(self::VIEWS . 'revision_diff.html.twig', PageFixtures::revisionDiff());
        yield 'revision diff (identical)' => new PageFixture(self::VIEWS . 'revision_diff.html.twig', PageFixtures::revisionDiff(true));
        yield 'templates' => new PageFixture(self::VIEWS . 'templates.html.twig', PageFixtures::templates());
        yield 'templates (empty)' => new PageFixture(self::VIEWS . 'templates.html.twig', PageFixtures::templates(true));
    }

    /** A 1.x host layout (renders `{% block admin %}`, no canonical blocks). */
    private const string HOST_1X = '<div id="host1x"><title>{% block title %}{% endblock %}</title>{% block stylesheets %}<link id="host-css">{% endblock %}<h1 id="host-title">{% block page_title %}{% endblock %}</h1>{% block admin %}{% endblock %}{% block javascripts %}<script id="host-js"></script>{% endblock %}</div>';

    public function testDuplicateAndApplyAskTheirFieldsInsideTheConfirmDialog(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'index.html.twig', PageFixtures::index()))->html;

        self::assertMatchesRegularExpression('#<dialog[^>]*>.*Duplicate home.*name="pageKey"[^>]*value="home-copy".*</dialog>#s', $html);
        self::assertStringContainsString('action="/admin_page_builder_document_duplicate?pageKey=home"', $html);
        self::assertStringContainsString('value="REDACTED-TEST-page_builder_document"', $html);
        self::assertStringContainsString('Create page', $html);
        self::assertStringNotContainsString('data-pbk-confirm', $html);
        self::assertStringNotContainsString('d-none', $html);

        $templates = $this->renderPage(new PageFixture(self::VIEWS . 'templates.html.twig', PageFixtures::templates()))->html;
        self::assertMatchesRegularExpression('#<dialog[^>]*>.*Create a page from landing.*name="pageKey".*name="include_field_schema".*</dialog>#s', $templates);
        self::assertMatchesRegularExpression('#<dialog[^>]*>.*Delete template landing.*</dialog>#s', $templates);
    }

    public function testTheCanvasToolbarExposesTheSlotClassesToItsScript(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'canvas.html.twig', PageFixtures::canvas()))->html;

        self::assertStringContainsString('data-pbk-class-published="', $html);
        self::assertStringContainsString('data-pbk-class-tab-active="', $html);
        self::assertStringContainsString('data-pbk-locale-tab="es"', $html);
        self::assertMatchesRegularExpression('#data-pbk-action="publish"\s*>#', $html);
        self::assertMatchesRegularExpression('#data-pbk-action="unpublish" hidden>#', $html);
        self::assertStringContainsString('/assets/css/grapes.min.css', $html);
        self::assertStringContainsString('<script type="module" src="/assets/js/page-builder-canvas.js">', $html);
        self::assertStringContainsString('&lt;p&gt;Hi&#x20;&#x5C;&quot;there', $html);
        self::assertStringNotContainsString('unpkg.com', $html);
    }

    public function testTheContentPageUsesTheUiKitDialogForTheMediaLibrary(): void
    {
        $html = $this->renderPage(new PageFixture(self::VIEWS . 'content.html.twig', PageFixtures::content()))->html;

        self::assertMatchesRegularExpression('#<dialog id="pbk-media-library"[^>]*data-pbk-media-dialog#', $html);
        self::assertStringContainsString('data-nowo-ui-dialog-open="pbk-media-library"', $html);
        self::assertStringContainsString('src="https&#x3A;&#x2F;&#x2F;example.com&#x2F;hero.png"', $html);
        self::assertStringContainsString('id="field-hero_image"', $html);
        self::assertStringContainsString('/assets/js/page-builder-admin.js', $html);
        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('modal fade', $html);
        self::assertStringContainsString('name="_csrf_token" value="REDACTED-TEST-page_builder_content"', $html);
        self::assertStringContainsString('Hero title', $html);
        self::assertStringContainsString('>bold<', $html);

        $bare = $this->renderPage(new PageFixture(self::VIEWS . 'content.html.twig', PageFixtures::content(false, false)))->html;
        self::assertStringNotContainsString('pbk-media-library', $bare);
        self::assertStringNotContainsString('page-builder-admin.js', $bare);
        self::assertStringContainsString('No fields yet. Add one below.', $bare);
    }

    /**
     * 2.0: the 1.x layout bridge (deprecated since 1.12) and the `nowo_page_builder_layout_template()` function were
     * removed. A 1.x host layout fails with a message that says what to do; it is never rendered half-styled.
     */
    public function testAOneXHostLayoutFailsClearly(): void
    {
        $twig    = $this->createTwig();
        $factory = PageFixtures::factory(new PageBuilderKitPages('host_1x.html.twig', [], $twig));

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The layout "host_1x.html.twig" (nowo_page_builder_kit.web_ui.layout_template) does not render the UiKit canonical blocks');

        $twig->render(self::VIEWS . 'revision_diff.html.twig', PageFixtures::revisionDiff(false, $factory));
    }

    protected static function inlineTemplates(): array
    {
        return [
            'host_1x.html.twig' => self::HOST_1X,
        ];
    }
}
