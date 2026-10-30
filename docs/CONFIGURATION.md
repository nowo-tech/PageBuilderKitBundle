# Configuration

All options live under the root key `nowo_page_builder_kit`.

## Table of contents

- [Full YAML tree](#full-yaml-tree)
- [Top-level options](#top-level-options)
- [revisions](#revisions)
- [debug](#debug)
- [security](#security)
- [web_ui](#web_ui)
- [doctrine](#doctrine)
- [html.sanitize](#htmlsanitize)
- [grapesjs](#grapesjs)
  - [Official plugins (loaded via esm.sh)](#official-plugins-loaded-via-esmsh)
  - [Twig variables in GrapesJS HTML](#twig-variables-in-grapesjs-html)
- [SEO & Open Graph](#seo--open-graph)
- [Accessibility helpers](#accessibility-helpers)
  - [Asset uploads](#asset-uploads)
- [Twig integration](#twig-integration)
- [Examples](#examples)

## Full YAML tree

```yaml
nowo_page_builder_kit:
    default_locale: es
    locales: [es, en]
    security:
        access_roles: [ROLE_EDITOR]
        layout_roles: [ROLE_EDITOR]
        content_roles: [ROLE_EDITOR]
        publish_roles: [ROLE_EDITOR]
        templates_roles: [ROLE_EDITOR]
        access_checker: null
        allow_unauthenticated: false
    web_ui:
        path_prefix: /admin/page-builder
        layout_template: null          # null = nowo_ui_kit.layouts.admin (REQ-UI-003)
        templates: {}                  # per-page overrides, e.g. { index: 'admin/pbk/index.html.twig' }
    seo:
        site_name: ''
        default_og_image: ''
        canonical_base_url: ''
        default_robots: 'index,follow'
    doctrine:
        table_prefix: ''
        # connection: default   # deprecated since 1.8, ignored (see below)
    html:
        sanitize:
            strategy: allowlist # none | strip | allowlist | service
            service: null
    revisions:
        enabled: false
        max_per_page: 50
        on_save: true
        on_publish: true
    debug:
        collector: true   # Web Profiler panel; only when kernel.debug is true
    grapesjs:
        enabled: true
        cdn_version: '0.22.9'
        module_base_url: 'https://esm.sh'   # ESM mirror of GrapesJS + plugins (https:// URL or root-relative path)
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
        canvas_styles: []   # CSS URLs of the canvas iframe; empty → derived from the deprecated web_ui.css_framework
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

PHP default and Flex recipe use `html.sanitize.strategy: allowlist`. Set `none` only when every editor is fully trusted. Keep `grapesjs.allow_scripts: false` unless editors are fully trusted (XSS risk).

## Top-level options

| Key | Type | Default | Description |
| --- | --- | --- | --- |
| `default_locale` | string | `es` | Default locale for fallback and `BuilderLocales`. |
| `locales` | list<string> | `[es, en]` | Locales persisted for widget props and admin UI. |
| `security` | map | see YAML | Access control for admin routes. |
| `web_ui` | map | see YAML | Admin URL prefix, layout and per-page template overrides (UiKit page contract). |
| `seo` | map | see YAML | Defaults for page-level meta / Open Graph / robots. |
| `doctrine` | map | see YAML | Table prefixing and connection name for host alignment. |
| `html` | map | see YAML | Rich-text sanitization for widgets that store HTML. |
| `revisions` | map | see YAML | Optional document version history (`BuilderPageRevision`). |
| `debug` | map | see YAML | Web Profiler DataCollector (dev only). |
| `grapesjs` | map | see YAML | GrapesJS canvas engine options. |

## revisions

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `false` | Master switch for version snapshots and the admin Versions UI. |
| `max_per_page` | `50` | Oldest revisions are pruned when exceeded. |
| `on_save` | `true` | Snapshot the previous live document before each admin save. |
| `on_publish` | `true` | Snapshot the live document when publishing (labeled `Published …`). |

Admin: `/admin/page-builder/pages/{pageKey}/revisions` · JSON: `…/revisions.json` · Diff: `…/revisions/{id}/diff`. Twig: `nowo_page_builder_revisions_enabled()`.

## debug

| Key | Default | Description |
| --- | --- | --- |
| `collector` | `true` | When `kernel.debug` is also true, registers the **Page Builder Kit** Web Debug Toolbar panel (`nowo_page_builder_kit`). Shows renders (engine, Twig apply/error, context key names, timings), public `/p/{pageKey}` outcomes, and admin actions. Disabled automatically in production. Set `false` to opt out in `dev`. |

No HTML/CSS document dumps are collected (size / PII). FrankenPHP-safe via `kernel.reset` on the request-scoped trace.

## security

| Key | Default | Description |
| --- | --- | --- |
| `access_roles` | `[ROLE_EDITOR]` | Shortcut: any matching role grants **every** capability (`canLayout` / `canContent` / …). Empty list → grants nothing. |
| `layout_roles` | `[ROLE_EDITOR]` | Canvas, structure, revisions, field schema. Empty list → grants nothing (only `access_roles` holders get layout). |
| `content_roles` | `[ROLE_EDITOR]` | Content forms + SEO. Layout editors also pass. Empty list → grants nothing beyond `access_roles` / `layout_roles`. |
| `publish_roles` | `[ROLE_EDITOR]` | Publish / unpublish. Layout editors also pass. Empty list → grants nothing beyond `access_roles` / `layout_roles`. |
| `templates_roles` | `[ROLE_EDITOR]` | Templates admin. Layout editors also pass. Empty list → grants nothing beyond `access_roles` / `layout_roles`. |
| `access_checker` | `null` | Optional service id implementing `PageBuilderKitAccessCheckerInterface` (**custom guard**). |
| `allow_unauthenticated` | `false` | When `true`, registers allow-all checker + guard. Demos only. |

**Empty lists deny (fail closed).** An empty role list never means "everyone": it grants nothing, and when `access_roles` and every `*_roles` list are empty (with no `access_checker` and `allow_unauthenticated: false`) the container refuses to compile with a `LogicException`. Up to 1.9.1 an empty `*_roles` list granted that capability to every user reaching the admin.

Access is **roles by default** or a **custom guard** via `access_checker` (same pattern as BlogKit / MarketingKit / RoutingKit). Controllers can inject `PageBuilderKitAccessGuard` (`assertLayout()`, …). The admin subscriber maps routes to `canLayout()` / `canContent()` / `canPublish()` / `canTemplates()`.

Example — content editors without canvas:

```yaml
nowo_page_builder_kit:
    security:
        access_roles: []          # empty = no shortcut role (grants nothing by itself)
        layout_roles: [ROLE_PBK_LAYOUT]
        content_roles: [ROLE_PBK_CONTENT]
        publish_roles: [ROLE_PBK_LAYOUT]
        templates_roles: [ROLE_PBK_LAYOUT]
```

Custom guard:

```yaml
nowo_page_builder_kit:
    security:
        access_checker: App\Security\PageBuilderAccessChecker
```

Twig: `nowo_page_builder_can('content')`, `nowo_page_builder_can_edit()`.

## web_ui

| Key | Default | Description |
| --- | --- | --- |
| `path_prefix` | `/admin/page-builder` | URL prefix for **all admin** routes (list, canvas, sections, SEO, versions, document API, asset upload). Must start with `/` and must **not** end with `/`. Public `/p/{pageKey}` is unchanged. |
| `layout_template` | `null` | Layout the admin pages extend (REQ-UI-003). `null` = UiKit `nowo_ui_kit.layouts.admin` (set it once for every Nowo bundle). A layout rendering the UiKit canonical blocks is used as is; a 1.x layout rendering `{% block admin %}` is wrapped in the deprecated bridge `admin/_legacy_layout.html.twig`. The 1.x default `@NowoPageBuilderKitBundle/admin/layout.html.twig` is normalised to `null`. |
| `templates` | `{}` | Per-page template overrides, page id ⇒ template. Ids: `index`, `canvas`, `content`, `seo`, `sections`, `revisions`, `revision_diff`, `templates` (others fail the compilation). Use a thin `{% extends '@!NowoPageBuilderKitBundle/admin/pages/<page>.html.twig' %}` that overrides one data block — see [TEMPLATES](TEMPLATES.md). |
| `css_framework` | `tailwind` | **Deprecated since 1.12.** The admin pages take the CSS stack from `nowo_ui_kit.css_framework` (theme slots). The value only seeds the default GrapesJS canvas iframe styles when `grapesjs.canvas_styles` is empty (Bootstrap 4 / 5, Tabler, Foundation from jsDelivr) and feeds the deprecated `nowo_page_builder_css_framework()`. Allowed: `bootstrap`, `bootstrap4`, `bootstrap5`, `tabler`, `tailwind`, `foundation`, `custom`, `none`. |

**Host integration (UiKit page contract).** The admin pages are UiKit scaffolds (see [TEMPLATES](TEMPLATES.md)), so the
host configures the shell and the look once in UiKit, not here:

```yaml
nowo_ui_kit:
    css_framework: bootstrap5          # or tailwind | foundation | tabler | custom | none
    layouts:
        admin: 'layouts/admin.html.twig'   # your admin layout, or a bridge using @NowoUiKit/layout/_bridge.html.twig
    theme:
        bundles:
            NowoPageBuilderKitBundle: { classes: { table: 'table table-sm' } }   # optional per-bundle exception
```

CSS stack per host (no bundle template is copied; markup switches through the UiKit theme slots):

```yaml
# Bootstrap 5 host (Tabler: tabler)
nowo_ui_kit: { css_framework: bootstrap5, icon_set: bootstrap-icons }
```

```yaml
# Tailwind host: map the slots you want to restyle
nowo_ui_kit:
    css_framework: tailwind
    icon_set: svg_inline
    theme: { classes: { btn_primary: 'rounded bg-indigo-600 px-3 py-1.5 text-white', table: 'min-w-full text-sm' } }
```

```yaml
# Foundation host
nowo_ui_kit: { css_framework: foundation, icon_set: none }
```

```yaml
# Own design system: semantic nowo-ui-* classes + tokens only
nowo_ui_kit:
    css_framework: custom
    row_actions_display: text
    theme: { tokens: { primary: 'var(--brand)', radius: '0.5rem' } }
```

- **Row actions** (edit, content, SEO, export, duplicate, versions; diff / restore; export / create page / delete;
  remove field) follow `nowo_ui_kit.row_actions_display` (`icon`, `text`, `icon_text`) through the UiKit `action`
  partial.
- **Dialogs**: duplicate, restore, create-from-template, delete and remove use the UiKit confirm `<dialog>`; the media
  library of the content page is the UiKit `dialog` partial. Both need the UiKit `scripts` partial in the layout (the
  UiKit document layout and `@NowoUiKit/layout/_bridge.html.twig` include it).
- **Form themes**: the admin forms are FormKit Types themed by the FormKit profile
  `nowo_form_kit.profiles.page_builder_kit`, which the bundle prepends unless the host defines that key itself
  (REQ-TWIG-005 rule 11, the "Form theme" row of the UiKit page contract). The seeded profile is theme-neutral:
  translation domain `NowoPageBuilderKitBundle`, `nowo-ui-input` on fields (none on checkboxes / hidden inputs), no
  convention help / placeholder keys (`auto_help` / `auto_placeholder` off; labels and help are explicit on the types).
  The bundle pages set no `form_themes`; the widget markup comes from the host form theme (`twig.form_themes`, e.g. the
  UiKit theme, `bootstrap_5_layout.html.twig`, a Tailwind or Foundation theme). To change the field classes, define
  the whole profile in the host:

```yaml
# config/packages/nowo_form_kit.yaml
nowo_form_kit:
    profiles:
        page_builder_kit:
            alias: page_builder_kit
            translation_domain: NowoPageBuilderKitBundle
            auto_placeholder: false
            auto_help: false
            defaults:
                attr: { class: 'form-control' }
                row_attr: { class: 'mb-3' }
            field_types:
                checkbox: { attr: { class: '' } }
                hidden: { attr: { class: '' } }
```

Example — mount admin under `/cms`:

```yaml
nowo_page_builder_kit:
    web_ui:
        path_prefix: /cms
```

Then list is `/cms/pages`, canvas `/cms/pages/{pageKey}/canvas`, upload `/cms/assets/upload`. Prefer this over adding a second `prefix:` on the Symfony route import (which would stack). Firewall the same path in `security.access_control`.

## doctrine

| Key | Default | Description |
| --- | --- | --- |
| `table_prefix` | `''` | Prefix applied to bundle entity tables (`pb_page`, `pb_document`, …) through `TablePrefixListener`. Use when several apps/tenants share one database. |
| `connection` | `default` | **Deprecated since 1.8, ignored.** It was only stored as the `nowo_page_builder_kit.doctrine.connection` parameter and never wired to Doctrine. The bundle entities are mapped like any other mapping: to use another connection, add the bundle mapping to the matching entity manager in `doctrine.orm.entity_managers`. Setting the key triggers a deprecation notice; it will be removed in the next major. |

Example — tables become `tenant_a_pb_page`, etc.:

```yaml
nowo_page_builder_kit:
    doctrine:
        table_prefix: 'tenant_a_'
```

After changing `table_prefix`, generate a new migration (or `doctrine:schema:update` in dev). Existing data is **not** renamed automatically.

## html.sanitize

| Key | Default | Description |
| --- | --- | --- |
| `strategy` | `allowlist` | `allowlist` (default), `strip`, `service`, or `none` (trusted editors only) |
| `service` | `null` | Host service implementing `PageBuilderHtmlSanitizerInterface` when `strategy: service` |

Sanitization runs when `DocumentService` saves widget props and when widgets sanitize merged props for public render.

## grapesjs

| Key | Default | Description |
| --- | --- | --- |
| `enabled` | `true` | Feature flag for the GrapesJS canvas. |
| `cdn_version` | `0.22.9` | GrapesJS core version imported from `module_base_url` (`<base>/grapesjs@<cdn_version>`). The editor CSS is the vendored 0.22.9 copy: keep both in sync. |
| `module_base_url` | `https://esm.sh` | ESM base the canvas imports GrapesJS and the enabled plugins from (`<base>/<package>@<version>`). Set it to your own mirror (an `https://` URL or a root-relative path) to remove the esm.sh dependency and keep third-party hosts out of `script-src` — see [SECURITY](SECURITY.md#content-security-policy-nonce). Other schemes, protocol-relative URLs and backslashes are rejected. |
| `height` | `calc(100vh - 220px)` | Editor container height. |
| `allow_scripts` | `false` | Persist/render `<script>` blocks. **XSS risk**. |
| `allow_custom_code` | `true` | Enable Custom HTML + `grapesjs-custom-code` plugin. |
| `compound_examples` | `true` | Built-in PBK compound DomComponents (Hero, CTA, …). |
| `a11y_helpers` | `true` | A11y traits (`aria-*`, `role`, `alt`) + landmark / skip-link blocks. |
| `show_devices` | `true` | Desktop / Tablet / Mobile device manager. |
| `notice_on_unload` | `false` | Browser “unsaved changes” prompt. |
| `asset_embed_as_base64` | `true` | Embed dropped files as base64 when upload is disabled. Forced `false` when `assets_upload.enabled`. |
| `assets_upload` | map | See [Asset uploads](#asset-uploads). |
| `canvas_styles` | `[]` | CSS URLs injected into the canvas iframe (what the edited page looks like). Empty → derived from the deprecated `web_ui.css_framework` (Bootstrap, etc.); set it explicitly instead. The editor's own styles are local assets (`css/grapes.min.css`, `css/grapick.min.css`); the GrapesJS scripts are loaded at run time from `module_base_url` at `cdn_version`. |
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
| `assets_upload.enabled` | `false` | Enable `POST …/assets/upload` and `GET …/assets` (library) for GrapesJS + content admin. |
| `assets_upload.storage` | `local` | `local` \| `s3` \| `service`. |
| `assets_upload.service` | `null` | Custom `PageBuilderAssetStorageInterface` id when `storage=service`. |
| `assets_upload.max_bytes` | `5242880` | Max upload size. |
| `assets_upload.allowed_mime` | jpeg/png/gif/webp | Allowlist checked against the **detected** (finfo) MIME type. The stored extension is always derived from that MIME type (`jpg`/`png`/`gif`/`webp`/`avif`/`svg`, otherwise `bin`); uploads whose file-name extension does not match (e.g. a GIF polyglot named `x.php`) are rejected. `image/svg+xml` is **not** in the default list: SVG served from your origin can run scripts (stored XSS). Add it only if uploads come from fully trusted users or are served from a separate origin / with a restrictive CSP. |
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
| `nowo_page_builder_layout_template()` | **Deprecated since 1.12** — layout of 1.x page copies (`web_ui.layout_template`, else the deprecated `admin/layout.html.twig`, which extends the UiKit admin layout) |
| `nowo_page_builder_css_framework()` | **Deprecated since 1.12** — the deprecated `web_ui.css_framework` value |
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
        # optional split (BlogKit-style):
        # layout_roles: [ROLE_PBK_LAYOUT]
        # content_roles: [ROLE_PBK_CONTENT]
```

**Custom access checker (guard):**

```yaml
nowo_page_builder_kit:
    security:
        access_checker: App\Security\PageBuilderAccessChecker
```

`App\Security\PageBuilderAccessChecker` must implement `PageBuilderKitAccessCheckerInterface` (`canAccess`, `canLayout`, `canContent`, `canPublish`, `canTemplates`, `can`). Controllers may inject `PageBuilderKitAccessGuard`.

**Production HTML allowlist:**

```yaml
nowo_page_builder_kit:
    html:
        sanitize:
            strategy: allowlist
```

See also [USAGE.md](USAGE.md) and [SECURITY.md](SECURITY.md).
