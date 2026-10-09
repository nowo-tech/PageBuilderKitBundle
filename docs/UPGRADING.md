# Upgrading

This document describes how to upgrade **Page Builder Kit Bundle** between released versions.

## Table of contents

- [1.6.0](#160)
- [1.5.1](#151)
- [1.5.0](#150)
- [1.4.4](#144)
- [1.4.3](#143)
- [1.4.2](#142)
- [1.4.1](#141)
- [1.4.0](#140)
- [1.3.0](#130)
- [1.2.0](#120)
- [1.1.1](#111)
- [1.1.0](#110)
- [1.0.0 — first release](#100--first-release)

## 1.6.0

From **1.5.1** (suggested **1.6.0**):

```bash
composer update nowo-tech/page-builder-kit-bundle
php bin/console assets:install   # new js/page-builder-admin.js + rebuilt page-builder-inline-edit.js
```

1. **No breaking API changes**, but public output is stricter. The new always-on `PublicHtmlHardener` (requires PHP 8.4 `Dom\HTMLDocument` — already the bundle minimum, no fallback needed) removes from Grapes HTML, `/p/{pageKey}` and classic `text` / `html` widgets: `<style>`, `<form>`, `<math>`, `<template>`, `<noscript>`, SVG `<animate>` / `<set>` / `<foreignObject>`, `action` / `formaction` / `srcdoc` attributes and non-image `data:` URLs — even with `html.sanitize.strategy: none`. Put page CSS in the Grapes style manager (`page_tree.css`), not in `<style>` blocks inside HTML. `grapesjs.allow_scripts: true` still keeps `<script>` elements.
2. **Twig fallback**: when Grapes Twig fails (`twigError` is set) or `grapesjs.twig.enabled` is false, tokens are now printed as encoded text (`{{ &quot;x&quot; }}` stays escaped) instead of the decoded source. Fix the template error rather than relying on the fallback.
3. **Host template overrides** of `public/page.html.twig`, `widgets/text.html.twig`, `widgets/html.html.twig` or `public/_editable_field.html.twig` that print editor HTML with `|raw` should switch to `|pbk_harden_html` (and Grapes CSS to `|pbk_harden_css`). If your app registered its own `pbk_harden_html` / `pbk_harden_css` Twig filters, remove them (the kit now provides them; duplicate names would shadow one another).
4. **CSP**: set the request attribute `csp_nonce` (e.g. in a `kernel.request` listener that also emits the `Content-Security-Policy` header) and the kit's `<script>` / `<style>` tags will carry it. Overrides of `admin/pages/templates.html.twig` / `revisions.html.twig` should use `attr: {'data-pbk-confirm': '...'|trans}` and load `js/page-builder-admin.js` instead of inline `onsubmit`.
5. Callers of `GrapesDocumentSanitizer::sanitizeHtml()` that print the result should pass `false` as the second argument.

See [CHANGELOG.md](CHANGELOG.md#unreleased).

## 1.5.1

From **1.5.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

1. No breaking changes. **No application upgrade steps.**
2. The default `allowlist` sanitizer is stricter: elements carrying `id="pbk-root"` are now sanitized like any other, and `href` / `src` values with `javascript:` / `vbscript:` schemes (also when split by tabs, newlines or control characters) or non-image `data:` URLs are dropped. `data:image/{png,jpeg,gif,webp,avif}` remains allowed on `img[src]`.

See [CHANGELOG.md](CHANGELOG.md#151---2026-10-09).

## 1.5.0

From **1.4.4**:

1. Optional — replace host copies of the bind hydrator / section filter / status query:
   - `PbkBindHydrator` → inject `Nowo\PageBuilderKitBundle\Service\PublicBindHydratorInterface` and call `hydrate($html, $pageKey)` (the Twig `Environment` is now injected; it is no longer a method argument), or use the Twig function `nowo_page_builder_hydrate_binds(html, pageKey)`.
   - `PbkSectionFilter` → `Nowo\PageBuilderKitBundle\Util\ContentSectionFilter::sanitize()` / `filterSchema()`, or Twig `nowo_page_builder_sanitize_section` / `nowo_page_builder_filter_schema_by_section`.
   - `BuilderPageStatusQuery::find()` → `Nowo\PageBuilderKitBundle\Repository\BuilderPageStatusQueryInterface::findStatusByPageKey()`.
2. Required for bind pencils to show the right type/label — implement `Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface::definitions(string $pageKey)` (return `list<array{key, type, label}>`; `label` is a translation id) and alias it in the host:

   ```yaml
   Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface:
       alias: App\Site\PageBuilder\ClinicContentFieldDefinitionProvider
   ```

   Without it, binds fall back to type `string` and the field key as label.
3. If you construct `PageBuilderKitExtension` manually, note the new optional last argument `?PublicBindHydratorInterface`.
4. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#150---2026-10-07) and [USAGE.md](USAGE.md#public-bind-hydration-section-filter-and-status-query).

## 1.4.4

From **1.4.3**:

1. Optional — after rendering Grapes HTML, inject `Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer` and call `normalize()` so Nu Html Checker does not fail on DOMDocument `</source>` wrappers or skeleton images without `src`.
2. Optional — bind constructor `$webpPictureUpgrades` (`png` / `webp` / `classContains`) only for host-owned PNG→WebP pairs. Do not put clinic-specific paths in the kit.
3. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#144---2026-10-06) and [USAGE.md](USAGE.md#public-html-cleanup).

## 1.4.3

From **1.4.2**:

1. No host action required for Form Twig remediation (admin/demo templates only).
2. Optional — rebuild inline-edit assets (`pnpm run build:inline-edit`) if you vendor `src/Resources/public/js/page-builder-inline-edit.js`.
3. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#143---2026-10-02).

## 1.4.2

From **1.4.1**:

1. Optional — copy [`examples/acme-grapes-block-pack/`](../examples/acme-grapes-block-pack/) as a starting Composer Grapes block pack (see [COOKBOOK.md](COOKBOOK.md#ship-a-grapes-block-pack)).
2. Optional — rebuild canvas assets (`pnpm install && pnpm run build` at the bundle root) if you vendor `src/Resources/public/js/page-builder-canvas.js` (canvas is now fully typechecked without `@ts-nocheck`).
3. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#142---2026-10-02).

## 1.4.1

From **1.4.0**:

1. Optional — read [COOKBOOK.md](COOKBOOK.md) for Grapes block packs and template sharing.
2. Optional — rebuild canvas assets (`pnpm install && pnpm run build` at the bundle root) if you vendor `src/Resources/public/js/page-builder-canvas.js`.
3. Clear Symfony cache after deploy (admin legacy banners / translations).

See [CHANGELOG.md](CHANGELOG.md#141---2026-10-02).

## 1.4.0

From **1.3.0**:

1. Optional — split editor roles with `layout_roles` / `content_roles` / `publish_roles` / `templates_roles` (BlogKit-style). `access_roles` remains a shortcut that grants every capability. Alternatively set `security.access_checker` to a custom `PageBuilderKitAccessCheckerInterface` (guard). Controllers can inject `PageBuilderKitAccessGuard`.
2. Optional — define typed content fields on a page (`/admin/page-builder/pages/{pageKey}/content`) and reference them in Grapes HTML as `{{ fields.your_key }}` or slots `[[fields.your_key]]`.
3. Custom `PageBuilderKitAccessCheckerInterface` implementations must expose `canLayout()` / `canContent()` / `canPublish()` / `canTemplates()` (+ `canAccess()` / `can()`).
4. Optional — template apply checkbox / JSON `include_seo: true` to copy SEO meta from the template snapshot (default remains **false**).
5. Optional — media library `GET {path_prefix}/assets` (route `admin_page_builder_assets_list`) when uploads are enabled.
6. Frontend hosts that vendor bundle JS: rebuild with `pnpm install && pnpm run build` at the bundle root (Vite). Demo uses Pentatrion Vite.
7. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#140---2026-10-02).

## 1.3.0

From **1.2.0**:

1. Default `html.sanitize.strategy` is **`allowlist`**. Existing installs that relied on unsanitized Grapes/widget HTML must set `strategy: none` explicitly (trusted editors only).
2. Clear Symfony cache after deploy.

## 1.2.0

From **1.1.x**:

1. Bump to `^1.2` (or `^1.1` if you stay on the previous minor) and run `composer update nowo-tech/page-builder-kit-bundle`.
2. **No** Doctrine schema changes or breaking config/route renames.
3. Clear Symfony cache after deploy (canvas JS / CSS assets and new admin template routes).
4. Optional — **template sharing:** use admin Export / Import on `/templates`, or `PageTemplateService::export` / `import` (`formatVersion: 1`, kinds `page_builder_template` / `page_builder_templates`).
5. Optional — **Grapes block packs:** implement `GrapesBlockPackInterface` (tag `nowo_page_builder_kit.grapes_block_pack`). Blocks appear in the canvas BlockManager via `blockPacks`. See [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md#grapesjs-block-packs).
6. Revision **diff** UI now includes side-by-side HTML/CSS (or classic JSON) panels — no host action required.
7. Web Profiler panel icon is a layout SVG (label remains “Page Builder”).

See [CHANGELOG.md](CHANGELOG.md#120---2026-09-27).

## 1.1.1

From **1.1.0**:

1. Optional: bump to `^1.1.1` (or stay on `^1.1`) and run `composer update nowo-tech/page-builder-kit-bundle`.
2. **No** Doctrine schema changes, config keys, or route renames.
3. Spec Kit artifacts under `specs/002`–`004` document Phases 2–4 already shipped in 1.0/1.1 — useful for contributors only.

See [CHANGELOG.md](CHANGELOG.md#111---2026-09-27).

## 1.1.0

From **1.0.0**:

1. Bump the package constraint to `^1.1` and run `composer update nowo-tech/page-builder-kit-bundle`.
2. Ensure `doctrine/orm` is `^3.7` (required for `SortDirection` in association `OrderBy` / QueryBuilder).
3. Run Doctrine schema update / migration for new table `pb_page_template` (`BuilderPageTemplate`).
4. `/p/{pageKey}` now renders **drafts** for users who pass the access checker (sticky banner). Anonymous visitors still receive 404 for drafts — review if you relied on “always 404 while draft” for logged-in editors.
5. Optional: keep `debug.collector: true` (default) in `dev` for the Web Profiler **Page Builder** panel; set `false` to disable.
6. New admin routes: templates, duplicate, export/import, revision diff — no breaking route renames.
7. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#110---2026-09-27) and [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md).

## 1.0.0 — first release

There is no prior published version. To adopt the bundle:

```bash
composer require nowo-tech/page-builder-kit-bundle:^1.0
composer require twig/extra-bundle twig/string-extra
```

1. Register the bundle and import `@NowoPageBuilderKitBundle/Resources/config/routing.yaml`.
2. Add `config/packages/nowo_page_builder_kit.yaml` (see [CONFIGURATION.md](CONFIGURATION.md)).
3. Run Doctrine migrations (or `doctrine:schema:update` in development) for `pb_*` entities.
4. Configure Symfony Security so editors can reach `web_ui.path_prefix` (default `/admin/page-builder`).
5. Open admin at `{path_prefix}/pages` → create a page → GrapesJS canvas.

### Recommended first config

```yaml
nowo_page_builder_kit:
    default_locale: es
    locales: [es, en]
    security:
        access_roles: [ROLE_EDITOR]
        allow_unauthenticated: false
    web_ui:
        path_prefix: /admin/page-builder
        css_framework: bootstrap5
    doctrine:
        table_prefix: ''          # e.g. 'app_' in shared DBs
    revisions:
        enabled: false            # set true to keep document history
    debug:
        collector: true           # Web Profiler Page Builder panel when kernel.debug
    grapesjs:
        enabled: true
        allow_scripts: false
        twig:
            enabled: true
        assets_upload:
            enabled: false
```

### Public pages and i18n routes

- Bundle convenience route: `/p/{pageKey}` (published for everyone; draft preview for editors since 1.1.0 — see above).
- Production sites should map host routes → `pageKey` + locale (same path + locale **or** different paths per language). See [USAGE.md](USAGE.md#associating-pages-with-public-routes-i18n).

### Optional demo

```bash
make -C demo/symfony8 up
# http://localhost:8137  — login admin/admin
```

Future upgrades will be documented in this file with version-specific steps. See [CHANGELOG.md](CHANGELOG.md).
