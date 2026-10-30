<?php

declare(strict_types=1);

namespace Nowo\PageBuilderKitBundle\Ui;

use Nowo\UiKitBundle\View\Label;
use Throwable;
use Twig\Environment;

use function in_array;
use function is_string;
use function trigger_deprecation;

/**
 * Page plumbing of the UiKit page contract (REQ-UI-003): resolves the layout the admin pages extend
 * (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`), wraps a 1.x admin layout (block `admin`, no UiKit
 * canonical blocks) in a deprecated bridge, and resolves the per-page template overrides (`web_ui.templates.<page>`).
 * Configuration only: no request state, safe in worker mode.
 */
final readonly class PageBuilderKitPages
{
    /** Bundle name of the page view models (theme exceptions `nowo_ui_kit.theme.bundles.NowoPageBuilderKitBundle`). */
    public const string BUNDLE = 'NowoPageBuilderKitBundle';

    /** Translation domain of the bundle labels (REQ-I18N-003). */
    public const string DOMAIN = 'NowoPageBuilderKitBundle';

    /**
     * The 1.x default layout. Deprecated since 1.12: it extends the UiKit admin layout and only serves page copies that
     * still extend `nowo_page_builder_layout_template()`; as `web_ui.layout_template` it means null.
     */
    public const string LEGACY_DEFAULT_LAYOUT = '@NowoPageBuilderKitBundle/admin/layout.html.twig';

    /** BC bridge around a 1.x host admin layout that renders `{% block admin %}` (removed in 2.0). */
    public const string LEGACY_ADMIN_BRIDGE = '@NowoPageBuilderKitBundle/admin/_legacy_layout.html.twig';

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

    /** Canonical blocks a 1.x layout may already render (copies of the 1.x bundle layout; the bridge skips them). */
    private const array LEGACY_HOST_BLOCKS = ['nowo_ui_flashes', 'nowo_ui_styles', 'nowo_ui_scripts'];

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
     * Layout the scaffolds extend (`page.layout`): `web_ui.layout_template`, the legacy bridge for a 1.x layout, else
     * null (UiKit `nowo_ui_layout.admin`).
     */
    public function layout(): ?string
    {
        $layout = $this->configuredLayout();
        if ($layout === null) {
            return null;
        }

        return $this->legacy($layout) !== null ? self::LEGACY_ADMIN_BRIDGE : $layout;
    }

    /**
     * Layout of the deprecated `nowo_page_builder_layout_template()` Twig function (1.x page copies): the configured
     * layout, else the deprecated 1.x default layout (which extends the UiKit admin layout).
     */
    public function legacyLayoutTemplate(): string
    {
        return $this->configuredLayout() ?? self::LEGACY_DEFAULT_LAYOUT;
    }

    /**
     * Shared variables of the admin pages (added to the controller variables): the 1.x layout wrapped by the
     * deprecated bridge and the canonical blocks it already renders.
     *
     * @param array<string, mixed> $vars page variables
     *
     * @return array<string, mixed>
     */
    public function context(array $vars): array
    {
        $layout = $this->configuredLayout();
        $legacy = $layout !== null ? $this->legacy($layout, false) : null;

        return $vars + [
            'nowo_page_builder_legacy_layout' => $legacy,
            'nowo_page_builder_legacy_blocks' => $this->hostBlocks($legacy),
        ];
    }

    private function configuredLayout(): ?string
    {
        if (in_array($this->layoutTemplate, [null, '', self::LEGACY_DEFAULT_LAYOUT], true)) {
            return null;
        }

        return $this->layoutTemplate;
    }

    /**
     * Canonical blocks a 1.x layout defines itself (the bridge does not render them a second time).
     *
     * @return list<string>
     */
    private function hostBlocks(?string $legacy): array
    {
        if ($legacy === null || !$this->twig instanceof Environment) {
            return [];
        }

        $template = $this->twig->load($legacy);
        $context  = $this->twig->mergeGlobals([]);
        $blocks   = [];
        foreach (self::LEGACY_HOST_BLOCKS as $block) {
            if ($template->hasBlock($block, $context)) {
                $blocks[] = $block;
            }
        }

        return $blocks;
    }

    /**
     * The configured layout when it is a 1.x layout (it does not define the UiKit canonical blocks), else null.
     */
    private function legacy(string $layout, bool $deprecate = true): ?string
    {
        if (!$this->twig instanceof Environment) {
            return null;
        }

        try {
            $canonical = $this->twig->load($layout)->hasBlock(self::CANONICAL_BLOCK, $this->twig->mergeGlobals([]));
        } catch (Throwable) {
            return null;
        }

        if ($canonical) {
            return null;
        }

        if ($deprecate) {
            trigger_deprecation('nowo-tech/page-builder-kit-bundle', '1.12', 'The layout "%s" does not render the UiKit canonical blocks (nowo_ui_page_header, nowo_ui_content, ...); it is wrapped in a deprecated bridge. Render the canonical blocks (or extend @NowoUiKit/layout/admin.html.twig) or set nowo_page_builder_kit.web_ui.layout_template to null to use nowo_ui_kit.layouts.admin; see docs/UPGRADING.md.', $layout);
        }

        return $layout;
    }
}
