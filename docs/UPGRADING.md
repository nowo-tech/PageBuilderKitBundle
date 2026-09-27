# Upgrading

This document describes how to upgrade **Page Builder Kit Bundle** between released versions.

## Table of contents

- [1.0.0 — first release](#100--first-release)

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
    grapesjs:
        enabled: true
        allow_scripts: false
        twig:
            enabled: true
        assets_upload:
            enabled: false
```

### Public pages and i18n routes

- Bundle convenience route: `/p/{pageKey}` (published only; locale from the request).
- Production sites should map host routes → `pageKey` + locale (same path + locale **or** different paths per language). See [USAGE.md](USAGE.md#associating-pages-with-public-routes-i18n).

### Optional demo

```bash
make -C demo/symfony8 up
# http://localhost:8137  — login admin/admin
```

Future upgrades will be documented in this file with version-specific steps. See [CHANGELOG.md](CHANGELOG.md).
