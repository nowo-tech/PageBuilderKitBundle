# Configuration

All options live under the root key `nowo_page_builder_kit`.

## Table of contents

- [Full YAML tree](#full-yaml-tree)
- [Top-level options](#top-level-options)
- [security](#security)
- [web_ui](#web_ui)
- [doctrine](#doctrine)
- [html.sanitize](#htmlsanitize)
- [grapesjs](#grapesjs)
- [Twig integration](#twig-integration)
- [Examples](#examples)

## Full YAML tree

```yaml
nowo_page_builder_kit:
    default_locale: es
    locales: [es, en]
    security:
        access_roles: [ROLE_EDITOR]
        access_checker: null
        allow_unauthenticated: false
    web_ui:
        layout_template: '@NowoPageBuilderKitBundle/admin/layout.html.twig'
        css_framework: tailwind
    seo:
        site_name: ''
        default_og_image: ''
        canonical_base_url: ''
        default_robots: 'index,follow'
    doctrine:
        table_prefix: ''
        connection: default
    html:
        sanitize:
            strategy: none      # none | strip | allowlist | service
            service: null
    grapesjs:
        enabled: true
        cdn_version: '0.22.9'
        height: 'calc(100vh - 220px)'
        allow_scripts: false
        allow_custom_code: true
        compound_examples: true
        a11y_helpers: true
        show_devices: true
        notice_on_unload: false
        asset_embed_as_base64: true
        assets_upload:
            enabled: false
            storage: local   # local | s3 | service
            max_bytes: 5242880
            local:
                directory: '%kernel.project_dir%/public/uploads/page-builder'
                public_prefix: /uploads/page-builder
            s3:
                helper_service: core_aws_s3.service.helper
                folder: page-builder
                private: false
                public_base_url: ''
        canvas_styles: []   # empty → derive from web_ui.css_framework
        assets: []          # empty → stock picsum/pravatar placeholders
        plugins:
            preset_webpage: true
            blocks_basic: true
            forms: true
            navbar: true
            countdown: true
            export: true
            tabs: true
            custom_code: true
            touch: true
            parser_postcss: true
            tooltip: true
            style_bg: true
            typed: true
            tui_image_editor: true
```

Production hosts should prefer `html.sanitize.strategy: allowlist` when untrusted HTML is possible. Keep `grapesjs.allow_scripts: false` unless editors are fully trusted (XSS risk).

## Top-level options

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `default_locale` | string | `es` | Default locale for fallback and `BuilderLocales`. |
| `locales` | list<string> | `[es, en]` | Locales persisted for widget props and admin UI. |
| `security` | map | see YAML | Access control for admin routes. |
| `web_ui` | map | see YAML | Admin shell layout and CSS framework hint. |
| `seo` | map | see YAML | Defaults for page-level meta / Open Graph / robots. |
| `doctrine` | map | see YAML | Table prefixing and connection name for host alignment. |
| `html` | map | see YAML | Rich-text sanitization for widgets that store HTML. |
| `grapesjs` | map | see YAML | GrapesJS canvas engine options. |

## security

| Key | Default | Description |
| --- | --- | --- |
| `access_roles` | `[ROLE_EDITOR]` | Any matching role grants admin access when no custom checker is configured. |
| `access_checker` | `null` | Optional service id implementing `PageBuilderKitAccessCheckerInterface`. |
| `allow_unauthenticated` | `false` | When `true`, the bundle uses an allow-all checker. Intended only for trusted demos. |

The bundle enforces access on route names beginning with `admin_page_builder_`.

## web_ui

| Key | Default | Description |
| --- | --- | --- |
| `layout_template` | `@NowoPageBuilderKitBundle/admin/layout.html.twig` | Base layout for admin screens. |
| `css_framework` | `tailwind` | Styling hint for admin and widget templates. Allowed: `bootstrap`, `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none`. |

## doctrine

| Key | Default | Description |
| --- | --- | --- |
| `table_prefix` | `''` | Prefix applied to bundle entity tables through `TablePrefixListener`. |
| `connection` | `default` | Connection name recorded for host alignment. |

Example:

```yaml
nowo_page_builder_kit:
    doctrine:
        table_prefix: 'tenant_a_'
```

## html.sanitize

| Key | Default | Description |
| --- | --- | --- |
| `strategy` | `none` | `none`, `strip`, `allowlist`, or `service` |
| `service` | `null` | Host service implementing `PageBuilderHtmlSanitizerInterface` when `strategy: service` |

Sanitization runs when `DocumentService` saves widget props and when widgets sanitize merged props for public render.

## grapesjs

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Feature flag for the GrapesJS canvas. |
| `cdn_version` | `0.22.9` | GrapesJS core version loaded from esm.sh / CSS CDN. |
| `height` | `calc(100vh - 220px)` | Editor container height. |
| `allow_scripts` | `false` | Persist/render `<script>` blocks. **XSS risk**. |
| `allow_custom_code` | `true` | Enable Custom HTML + `grapesjs-custom-code` plugin. |
| `compound_examples` | `true` | Built-in PBK compound DomComponents (Hero, CTA, …). |
| `a11y_helpers` | `true` | A11y traits (`aria-*`, `role`, `alt`) + landmark / skip-link blocks. |
| `show_devices` | `true` | Desktop / Tablet / Mobile device manager. |
| `notice_on_unload` | `false` | Browser “unsaved changes” prompt. |
| `asset_embed_as_base64` | `true` | Embed dropped files as base64 when upload is disabled. Forced `false` when `assets_upload.enabled`. |
| `assets_upload` | map | See [Asset uploads](#asset-uploads). |
| `canvas_styles` | `[]` | CSS URLs injected into the canvas iframe. Empty → derive from `web_ui.css_framework` (Bootstrap, etc.). |
| `assets` | `[]` | Initial Asset Manager entries (`type`, `src`, `name`, `width`, `height`). Empty → stock demo images. |
| `plugins.*` | mostly `true` | Toggle official plugins (see below). |

### Official plugins (loaded via esm.sh)

| Flag | Package | Adds |
| --- | --- | --- |
| `preset_webpage` | `grapesjs-preset-webpage` | Full webpage UI (panels, import HTML modal, blocks layout) |
| `blocks_basic` | `grapesjs-blocks-basic` | Column / flex grid blocks |
| `forms` | `grapesjs-plugin-forms` | Form inputs, select, checkbox, … |
| `navbar` | `grapesjs-navbar` | Navbar component |
| `countdown` | `grapesjs-component-countdown` | Countdown component |
| `export` | `grapesjs-plugin-export` | Export template |
| `tabs` | `grapesjs-tabs` | Tabs component |
| `custom_code` | `grapesjs-custom-code` | Custom code block |
| `touch` | `grapesjs-touch` | Touch support |
| `parser_postcss` | `grapesjs-parser-postcss` | Better CSS parsing |
| `tooltip` | `grapesjs-tooltip` | Tooltip component |
| `style_bg` | `grapesjs-style-bg` | Background / gradient style sector (Grapick) |
| `typed` | `grapesjs-typed` | Typed.js text animation block |
| `tui_image_editor` | `grapesjs-tui-image-editor` | TOAST UI image editor on assets |

Also available from GrapesJS core (always on): Block / Style / Layer / Trait / Selector managers, devices, Asset Manager, built-in RTE, undo/redo commands.

### Twig variables in GrapesJS HTML

| Key | Default | Description |
| --- | --- | --- |
| `twig.enabled` | `true` | Evaluate `{{ … }}` / `{% … %}` inside saved Grapes HTML on public render. |
| `twig.strict_variables` | `false` | Missing variables throw when `true`. |
| `twig.canvas_helpers` | `true` | Block Manager category **Twig** with insert helpers. |

Built-in context: `title`, `slug`, `pageKey`, `locale`, `status`, `page.*`, `seo.*`.

Host extras — implement `GrapesTwigContextProviderInterface` (auto-tagged):

```php
#[AutoconfigureTag('nowo_page_builder_kit.grapes_twig_context')]
final class AppGrapesTwigContextProvider implements GrapesTwigContextProviderInterface
{
    public function getContext(BuilderPage $page, string $locale): array
    {
        return ['productName' => 'Acme'];
    }
}
```

## SEO & Open Graph

Page-level SEO lives on `BuilderPageTranslation` (not in the Grapes HTML body — the sanitizer strips `<meta>` / `<link>` from canvas HTML).

| Key | Default | Description |
| --- | --- | --- |
| `seo.site_name` | `''` | `og:site_name` fallback. |
| `seo.default_og_image` | `''` | `og:image` when translation has none. |
| `seo.canonical_base_url` | `''` | Builds `{base}/p/{slug}` when `canonicalUrl` is empty. |
| `seo.default_robots` | `index,follow` | `robots` meta fallback. |

Admin: `/admin/page-builder/pages/{pageKey}/seo`. Public template includes `@NowoPageBuilderKitBundle/public/_seo_meta.html.twig` via `page_tree.seo`.

## Accessibility helpers

With `grapesjs.a11y_helpers: true` (default), the canvas exposes category **A11y** (skip link, main/nav/aside/footer landmarks, decorative icon, figure+figcaption) and adds traits `aria-label`, `role`, `alt`, etc. on common component types.

### Asset uploads

| Key | Default | Description |
| --- | --- | --- |
| `assets_upload.enabled` | `false` | Enable `POST /admin/page-builder/assets/upload` for GrapesJS Asset Manager. |
| `assets_upload.storage` | `local` | `local` \| `s3` \| `service`. |
| `assets_upload.service` | `null` | Custom `PageBuilderAssetStorageInterface` id when `storage=service`. |
| `assets_upload.max_bytes` | `5242880` | Max upload size. |
| `assets_upload.allowed_mime` | jpeg/png/gif/webp/svg | MIME allowlist. |
| `assets_upload.local.directory` | `%kernel.project_dir%/public/uploads/page-builder` | Disk path. |
| `assets_upload.local.public_prefix` | `/uploads/page-builder` | URL prefix returned as `src`. |
| `assets_upload.s3.helper_service` | `core_aws_s3.service.helper` | `AwsS3Helper` (or compatible) service id. |
| `assets_upload.s3.folder` | `page-builder` | Object key prefix. |
| `assets_upload.s3.private` | `false` | `false` → public-read ACL. |
| `assets_upload.s3.public_base_url` | `''` | Optional CDN/CloudFront base URL. |

Suggest: `core/aws-s3-bundle`. Custom backends implement `PageBuilderAssetStorageInterface`.

Or pass ad-hoc Twig context:

```php
$pageRenderProvider->getRenderedTree('home', 'es', ['promo' => 'Summer']);
// Twig: {{ nowo_page_builder_render('home', app.request.locale, { promo: 'Summer' }) }}
```

Sandbox allows tags `if` / `for` / `set` only (no `include` / `embed` / `extends`). HTML is sanitized before and after Twig.

New pages use schema **v2** (`engine: grapesjs`). Legacy **v1** remains readable.

## Twig integration

The bundle registers Twig functions (not globals):

| Function | Meaning |
| --- | --- |
| `nowo_page_builder_layout_template()` | Active admin layout template id |
| `nowo_page_builder_css_framework()` | Selected CSS framework hint |
| `nowo_page_builder_render(pageKey, locale?)` | Rendered page tree for Twig |
| `nowo_page_builder_widget_types()` | Registered widget type metadata |

Host overrides under `templates/bundles/NowoPageBuilderKitBundle/` take precedence over bundle templates (`TwigPathsPass`).

## Examples

**Custom locales:**

```yaml
nowo_page_builder_kit:
    default_locale: en
    locales: [en, es, fr]
```

**Role-based editor access:**

```yaml
nowo_page_builder_kit:
    security:
        access_roles: [ROLE_EDITOR, ROLE_ADMIN]
```

**Custom access checker:**

```yaml
nowo_page_builder_kit:
    security:
        access_checker: App\Security\PageBuilderAccessChecker
```

**Production HTML allowlist:**

```yaml
nowo_page_builder_kit:
    html:
        sanitize:
            strategy: allowlist
```

See also [USAGE.md](USAGE.md) and [SECURITY.md](SECURITY.md).
