# Upgrading

This document describes how to upgrade **Page Builder Kit Bundle** between released versions.

## Table of contents

- [Unreleased — GrapesJS canvas](#unreleased--grapesjs-canvas)
- [1.0.0 — first release](#100--first-release)

## Unreleased — GrapesJS canvas

- Admin canvas now uses **GrapesJS** (CDN). New pages are created with schema **v2** (`engine: grapesjs`).
- Existing schema **v1** documents continue to render; open them in the canvas to migrate by saving (editor starts empty unless you seed Grapes content).
- Add optional config:

```yaml
nowo_page_builder_kit:
    grapesjs:
        enabled: true
        allow_scripts: false
        allow_custom_code: true
```

- Public templates: if `page_tree.engine == 'grapesjs'`, render `page_tree.html` + `page_tree.css` (already handled by `@NowoPageBuilderKitBundle/public/page.html.twig`).

## 1.0.0 — first release

There is no prior published version. To adopt the bundle:

```bash
composer require nowo-tech/page-builder-kit-bundle:^1.0
composer require twig/extra-bundle twig/string-extra
```

1. Register routes from `@NowoPageBuilderKitBundle/Resources/config/routing.yaml`.
2. Add `config/packages/nowo_page_builder_kit.yaml` (see [CONFIGURATION.md](CONFIGURATION.md)).
3. Run Doctrine migrations for bundle entities.
4. Configure Symfony Security so editors can reach `/admin/page-builder/`.
5. Optionally run the demo: `make -C demo/symfony8 up` → `http://localhost:8137`.

Future upgrades will be documented in this file with version-specific steps. See [CHANGELOG.md](CHANGELOG.md).
