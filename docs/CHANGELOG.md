# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.0.0] - TBD](#100---tbd)

## [Unreleased]

### Changed

- Admin canvas migrated to **GrapesJS** (CDN/ESM) with **preset-webpage** and official plugins (forms, navbar, countdown, export, tabs, custom-code, touch, postcss, tooltip, style-bg, typed, tui-image-editor).
- Document schema **v2**: `engine: grapesjs`, `html`/`css`/`grapes`/`localeContent`.
- Configurable `grapesjs.plugins.*`, `canvas_styles`, Asset Manager stock assets, devices, compound examples.
- Demo **use-case matrix** (`/showcase`, seed v9): marketing, content, commerce, forms, i18n, classic sections, Grapes sections≠locale, Twig, SEO/a11y, unpublished draft.
- Classic **Sections editor** (`/admin/page-builder/pages/{pageKey}/sections`) for per-locale widget props on a shared section tree.
- GrapesJS HTML can embed sandboxed **Twig variables** (`{{ title }}`, host context providers).
- Page-level **SEO / Open Graph / robots** on `BuilderPageTranslation` → `page_tree.seo` + admin SEO screen.
- GrapesJS **A11y** helpers (landmarks, skip link, alt/aria traits).
- GrapesJS Asset Manager **uploads** via `grapesjs.assets_upload` (local filesystem or AWS S3 through `core/aws-s3-bundle`).

### Deprecated

- Classic Stimulus/SortableJS canvas client (replaced by GrapesJS). Schema v1 Appearance helpers remain for legacy documents.

## [1.0.0] - TBD

Initial release — Phase 1 page builder.

### Added

- Visual admin canvas with section/column/widget document model (schema v1).
- Widget types: `heading`, `text`, `html`, `image`, `button`, `spacer`.
- Locale-aware widget props with default-locale fallback.
- `DocumentService`, document JSON API, publish flow, and public `/p/{pageKey}` route.
- Twig helpers: `nowo_page_builder_render()`, layout/CSS framework functions.
- Configurable editor access, HTML sanitization strategies, optional table prefix.
- Symfony 8 FrankenPHP demo on port **8137**.
- Spec Kit baseline under `specs/001-baseline/` and REQ-DOCS documentation set.
