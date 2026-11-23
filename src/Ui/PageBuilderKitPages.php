<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Ui;

use LogicException;
use Nowo\UiKitBundle\View\Label;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function sprintf;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003): resolves the layout the admin pages extend
 * (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`; since 2.0 a 1.x admin layout without the UiKit
 * canonical blocks fails with a LogicException, the 1.x bridge was removed) and resolves the per-page template
 * overrides (`web_ui.templates.<page>`).
 * Configuration only: no request state, safe in worker mode.
 */
final readonly class PageBuilderKitPages
{
    /** Bundle name of the page view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoPageBuilderKitBundle`). */
    public const string BUNDLE = 'NowoPageBuilderKitBundle';

    /** Translation domain of the bundle labels (REQ-I18N-003). */
    public const string DOMAIN = 'NowoPageBuilderKitBundle';

    /**
     * The 1.x default layout value (the template was deprecated in 1.12 and removed in 2.0): as
     * `web_ui.layout_template` it still means null (`nowo_ui_kit.layouts.admin`).
     */
    public const string LEGACY_DEFAULT_LAYOUT = '@NowoPageBuilderKitBundle/admin/layout.html.twig';

    /** Page ids accepted by `web_ui.templates` (page id => default template). */
    public const array TEMPLATES = [
        'index'         => '@NowoPageBuilderKitBundle/admin/pages/index.html.twig',
        'canvas'        => '@NowoPageBuilderKitBundle/admin/pages/canvas.html.twig',
        'content'       => '@NowoPageBuilderKitBundle/admin/pages/content.html.twig',
        'seo'           => '@NowoPageBuilderKitBundle/admin/pages/seo.html.twig',
        'sections'      => '@NowoPageBuilderKitBundle/admin/pages/sections.html.twig',
        'revisions'     => '@NowoPageBuilderKitBundle/admin/pages/revisions.html.twig',
        'revision_diff' => '@NowoPageBuilderKitBundle/admin/pages/revision_diff.html.twig',
        'templates'     => '@NowoPageBuilderKitBundle/admin/pages/templates.html.twig',
    ];

    /** A layout that defines this block renders the UiKit canonical blocks. */
    private const string CANONICAL_BLOCK = 'nowo_ui_page_header';

    /**
     * @param array<string, string> $templates `web_ui.templates` (page id => template)
     */
    public function __construct(
        private ?string $layoutTemplate = null,
        private array $templates = [],
        private ?Environment $twig = null,
    ) {
    }

    /**
     * Translation key of the bundle domain as a UiKit label.
     *
     * @param array<string, mixed> $params
     */
    public static function label(string $key, array $params = []): Label
    {
        return new Label($key, self::DOMAIN, $params);
    }

    /**
     * Template of a page: the `web_ui.templates.<page>` override, else the bundle template.
     */
    public function template(string $page): string
    {
        $override = $this->templates[$page] ?? null;

        return is_string($override) && $override !== '' ? $override : self::TEMPLATES[$page] ?? $page;
    }

    /**
     * Layout the scaffolds extend (`page.layout`): `web_ui.layout_template`, else null (UiKit `nowo_ui_layout.admin`).
     *
     * @throws LogicException when the configured layout does not render the UiKit canonical blocks (a 1.x layout; the
     *                        bridge that wrapped it, deprecated since 1.12, was removed in 2.0)
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout !== null && $this->isLegacy($layout)) {
            throw new LogicException(sprintf('The layout "%s" (nowo_page_builder_kit.web_ui.layout_template) does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...). The 1.x layout bridge (deprecated since 1.12) was removed in 2.0: render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig), or set web_ui.layout_template to null to use nowo_ui_kit.layouts.admin. See TEMPLATES.md and UPGRADING.md "To 2.0.0".', $layout));
        }

        return $layout;
    }

    /**
     * Shared variables of the admin pages, added to the controller variables. Since 2.0 there is none (the 1.x layout
     * bridge and its variables were removed): the page variables are returned as they are.
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        return $vars;
    }

    private function configuredLayout(): ?string
    {
        if (in_array($this->layoutTemplate, [null, '', self::LEGACY_DEFAULT_LAYOUT], true)) {
            return null;
        }

        return $this->layoutTemplate;
    }

    /**
     * Whether the configured layout is a 1.x layout (it loads and does not define the UiKit canonical blocks). A layout
     * that cannot be loaded is left to Twig, which names the missing template.
     */
    private function isLegacy(string $layout): bool
    {
        if (!$this->twig instanceof Environment) {
            return false;
        }

        try {
            return !$this->twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $this->twig->mergeGlobals([]));
        } catch (Throwable) {
            return false;
        }
    }
}
