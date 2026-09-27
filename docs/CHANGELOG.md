# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[1.0.0] - 2026-09-27](#100---2026-09-27)

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
