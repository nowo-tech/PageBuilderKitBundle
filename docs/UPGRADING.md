# Upgrading

This document describes how to upgrade **Page Builder Kit Bundle** between released versions.

## Table of contents

- [1.2.0](#120)
- [1.1.1](#111)
- [1.1.0](#110)
- [1.0.0 — first release](#100--first-release)

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
