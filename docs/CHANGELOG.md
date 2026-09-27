# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)

- [[1.0.0] - 2026-09-27](#100---2026-09-27)

## [Unreleased]

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder `orderBy`/`addOrderBy` with `SortDirection::Ascending`/`Descending` (doctrine/orm deprecation, https://github.com/doctrine/orm/issues/11313); require `doctrine/orm` `^3.7` where applicable.


### Added

- **Web Profiler DataCollector** (`debug.collector`, REQ-DEBUG-001): toolbar panel with renders, public outcomes, admin actions, Twig/timing diagnostics (dev only, FrankenPHP-safe reset).
- **Draft preview** on `/p/{pageKey}` for users who pass `PageBuilderKitAccessCheckerInterface` (banner); anonymous visitors still get 404 for drafts.
- **Revision diff** UI/API (`…/revisions/{id}/diff`) via `DocumentDiff` / `PageRevisionService::diff()`.
- **Duplicate page**, JSON **export/import** (`DocumentImportExportService`, `formatVersion: 1`).
- **Page templates** library (`BuilderPageTemplate`, admin `/templates`).
- **Widget packs** (`WidgetPackInterface`, `WidgetPackRegistry`) + [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md).
- **REQ-DEMO-013:** Playwright e2e under `demo/symfony8/e2e/` (`make test-e2e`), `demo-screenshots` target, and README gallery cropped to GrapesJS canvas / public compounds (`docs/images/demo/overview.png`, `interaction.png`).

### Changed

- Spec Kit roadmap aligned with shipped Phase 3/4 and DX collector ([SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md)).

## [1.0.0] - 2026-09-27

First public release of **Page Builder Kit Bundle**.

### Added

- GrapesJS admin canvas (CDN/ESM) with preset-webpage and official plugins (forms, navbar, countdown, export, tabs, custom-code, touch, postcss, tooltip, style-bg, typed, tui-image-editor).
- Document schema **v2** (`engine: grapesjs`, `html` / `css` / `grapes` / `localeContent`) plus legacy classic schema **v1** (sections → columns → widgets).
- Classic **Sections editor** for per-locale widget props on a shared section tree.
- Widget types (classic): `heading`, `text`, `html`, `image`, `button`, `spacer`, nesting via `container`.
- `DocumentService`, CSRF-protected document JSON API, **publish** / **unpublish** (draft), public `/p/{pageKey}`.
- Configurable admin URL prefix: `web_ui.path_prefix` (default `/admin/page-builder`).
- Optional `doctrine.table_prefix` for shared databases.
- Optional **page revisions** (`revisions.*`): snapshots on save/publish, Versions admin UI, restore + JSON API.
- Public **edit pencil** when `PageBuilderKitAccessCheckerInterface` allows (`nowo_page_builder_can_edit()`).
- Sandboxed **Twig in Grapes HTML** (`{{ }}`, `{% if %}`, `{% for %}`, `{% set %}`) + host `GrapesTwigContextProviderInterface`.
- Page-level **SEO / Open Graph / robots** + admin SEO screen; GrapesJS **a11y** helpers.
- GrapesJS Asset Manager **uploads** (`grapesjs.assets_upload`: local or AWS S3).
- Twig helpers: `nowo_page_builder_render()`, layout/CSS framework, revisions-enabled flag.
- Security: access roles / custom checker / allow-unauthenticated (demo), HTML sanitize strategies.
- Symfony 8 FrankenPHP demo (port **8137**) with use-case matrix (`/showcase`).
- Docs: INSTALLATION, CONFIGURATION, USAGE (incl. i18n routing patterns), ARCHITECTURE (Mermaid), SECURITY, SPEC Kit baseline.

### Notes

- Classic Stimulus/SortableJS canvas client is not shipped; schema v1 remains readable/renderable and editable via Sections.
- Host apps own pretty multilingual URLs; `/p/{pageKey}` is a convenience renderer (see USAGE).
