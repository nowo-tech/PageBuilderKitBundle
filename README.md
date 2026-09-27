# Page Builder Kit Bundle

[![CI](https://github.com/nowo-tech/PageBuilderKitBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/nowo-tech/PageBuilderKitBundle/actions/workflows/ci.yml) [![Packagist Version](https://img.shields.io/packagist/v/nowo-tech/page-builder-kit-bundle.svg?style=flat)](https://packagist.org/packages/nowo-tech/page-builder-kit-bundle) [![Packagist Downloads](https://img.shields.io/packagist/dt/nowo-tech/page-builder-kit-bundle.svg)](https://packagist.org/packages/nowo-tech/page-builder-kit-bundle) [![License](https://img.shields.io/badge/license-MIT-blue.svg)](LICENSE) [![PHP](https://img.shields.io/badge/PHP-8.4%2B-777BB4?logo=php)](https://php.net) [![Symfony](https://img.shields.io/badge/Symfony-7.4%20%7C%208.0%20%7C%208.1%2B-000000?logo=symfony)](https://symfony.com) [![GitHub stars](https://img.shields.io/github/stars/nowo-tech/PageBuilderKitBundle.svg?style=social&label=Star)](https://github.com/nowo-tech/PageBuilderKitBundle) [![Coverage](https://img.shields.io/badge/Coverage-%E2%89%A590%25-brightgreen)](#tests-and-coverage)

> ⭐ **Found this useful?** Give it a star on GitHub! It helps us maintain and improve the project.

**Visual Symfony page builder** powered by **GrapesJS**, with Doctrine persistence, locale-aware content, CSRF-protected JSON document API, and Twig-based public rendering. Legacy section/column/widget documents (schema v1) remain supported for read/render.


> Compatible with **Symfony 7.4, 8.0, and 8.1+** on **PHP 8.4+**

![FrankenPHP Friendly Worker Mode](docs/images/frankenphp-friendly.png)

This bundle is **FrankenPHP worker mode friendly**. Shared services stay request-safe; optional `BuilderLocales` static binding is cleared after each request. See [FRANKENPHP-WORKER-AUDIT.md](docs/FRANKENPHP-WORKER-AUDIT.md).

## What is this?

Page Builder Kit Bundle gives Symfony applications a reusable visual page builder backed by Doctrine. Editors create pages with stable keys, compose layouts on an admin canvas (sections, columns, widgets), save locale-specific widget content, publish pages, and expose them on public routes or through Twig helpers. It complements [Page Layout Kit Bundle](https://github.com/nowo-tech/PageLayoutKitBundle) when you need a free-form canvas instead of fixed typed blocks.

## Features

- GrapesJS admin canvas with **preset-webpage** + official plugins (forms, navbar, countdown, export, tabs, custom-code, style-bg, typed, image editor, …), Asset Manager, devices, and PBK compound blocks
- Legacy schema v1 still supported: sections → columns → widgets
- Built-in GrapesJS blocks: section, heading, text, image, button, spacer, container, **compound examples** (Hero, Feature grid, CTA, Testimonial, Pricing, Media split, Stats, FAQ), optional custom HTML/script
- Locale tabs on the canvas with default-locale fallback for public render
- Admin page list and create form at `/admin/page-builder/pages`
- Visual canvas at `/admin/page-builder/pages/{pageKey}/canvas`
- JSON document API (GET/POST) and publish endpoint with CSRF headers
- Public route `/p/{pageKey}` for published pages (Grapes HTML/CSS or classic widget tree)
- `DocumentService`, `PageRenderProvider`, and Twig function `nowo_page_builder_render()`
- Configurable access guard: roles, custom checker, or demo-only unauthenticated mode
- Optional HTML sanitization (`html.sanitize` + `GrapesDocumentSanitizer`; `grapesjs.allow_scripts` gated)
- Twig namespace `NowoPageBuilderKitBundle` with host override precedence
- Extensible widget registry for classic documents (`WidgetTypeInterface` + compiler pass)
- Symfony 8 FrankenPHP demo in `demo/symfony8` (default port **8137**)

## Quick start

```bash
composer require nowo-tech/page-builder-kit-bundle:^1.0
composer require twig/extra-bundle twig/string-extra
```

```yaml
# config/packages/nowo_page_builder_kit.yaml
nowo_page_builder_kit:
    default_locale: es
    locales: [es, en]
    security:
        access_roles: [ROLE_EDITOR]
```

```yaml
# config/routes/nowo_page_builder_kit.yaml
nowo_page_builder_kit:
    resource: '@NowoPageBuilderKitBundle/Resources/config/routing.yaml'
```

```bash
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

Admin: `/admin/page-builder/pages` → open a page canvas.

## Page Layout Kit vs Page Builder Kit

| Topic | [Page Layout Kit](https://github.com/nowo-tech/PageLayoutKitBundle) | Page Builder Kit |
| --- | --- | --- |
| Mental model | Ordered list of **typed blocks** per configured page key | **Tree document**: sections, columns, widgets |
| Block / widget set | Fixed six types (`hero`, `text`, `cards`, …) | Core six widgets; registry open to extensions (Phase 4) |
| Admin UX | Reorder list + inline CMS modals on public pages | Full-page **canvas** with drag-and-drop |
| Page keys | Declared in config (`pages: [home, contact]`) | Created dynamically in admin (unique `pageKey`) |
| Content storage | One entity per block type + translations | Single JSON **structure** + per-locale **widget props** map |
| Public API | `PageBlockProvider::getLayout()` | `PageRenderProvider::getRenderedTree()` / Twig `nowo_page_builder_render()` |
| Best for | Marketing pages with known block patterns | Landing pages and layouts that change shape often |

Use both bundles in one app when some routes need rigid blocks and others need a visual builder.

## Roadmap

| Phase | Status | Scope |
| --- | --- | --- |
| **Phase 1** | **Shipped (1.0)** | Canvas admin, document API, six widgets, i18n props, publish flow, public render, security + sanitize hooks, FrankenPHP demo |
| **Phase 2** | Planned | Nested sections/widgets, responsive column settings, design tokens / per-widget style presets |
| **Phase 3** | Planned | Revision history UI on `BuilderPageRevision`, page templates, duplicate/import |
| **Phase 4** | Planned | External widget bundles, marketplace-style registration, host-defined widget packs |

Details: [SPEC-DRIVEN-DEVELOPMENT.md](docs/SPEC-DRIVEN-DEVELOPMENT.md#roadmap-phases-2-4).

## Development

```bash
make up
make test
make phpstan
make -C demo/symfony8 up
make demo-smoke
```

Demo default URL: `http://localhost:8137`.

## Documentation

- [Installation](docs/INSTALLATION.md)
- [Configuration](docs/CONFIGURATION.md)
- [PSR evaluation (REQ-CS-007)](docs/PSR.md)
- [Usage](docs/USAGE.md)
- [Contributing](docs/CONTRIBUTING.md)
- [Code of Conduct](CODE_OF_CONDUCT.md)
- [Changelog](docs/CHANGELOG.md)
- [Upgrading](docs/UPGRADING.md)
- [Release process](docs/RELEASE.md)
- [Security](docs/SECURITY.md)
- [Engram](docs/ENGRAM.md)
- [Spec-driven development](docs/SPEC-DRIVEN-DEVELOPMENT.md)
- [GitHub Spec Kit](docs/SPEC-KIT.md)

### Additional documentation

- [GitHub Actions CI requirements](docs/GITHUB_CI.md)
- [Demo with FrankenPHP](docs/DEMO-FRANKENPHP.md)
- [Use cases matrix](docs/USE-CASES.md)
- [FrankenPHP worker mode audit](docs/FRANKENPHP-WORKER-AUDIT.md)

## Tests and coverage

| Area | Status | Command |
| --- | --- | --- |
| PHP `src/` coverage target | ≥90% (CI gate) | `make test-coverage` |
| Unit and bundle QA | Enabled | `make test` |
| Full release checks | Enabled | `make release-check` |

```bash
make test
make test-coverage
make release-check
```

## License

MIT — see [LICENSE](LICENSE).
