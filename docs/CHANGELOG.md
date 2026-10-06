# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.4.3] - 2026-10-02](#143---2026-10-02)
- [[1.4.2] - 2026-10-02](#142---2026-10-02)
- [[1.4.1] - 2026-10-02](#141---2026-10-02)
- [[1.4.0] - 2026-10-02](#140---2026-10-02)
- [[1.3.0] - 2026-09-28](#130---2026-09-28)

- [[1.2.0] - 2026-09-27](#120---2026-09-27)

- [[1.1.1] - 2026-09-27](#111---2026-09-27)

- [[1.1.0] - 2026-09-27](#110---2026-09-27)

- [[1.0.0] - 2026-09-27](#100---2026-09-27)

## [Unreleased]

### Added

- `Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer` — strips invalid `</source>` around `<picture>` images, adds a 1×1 GIF `src` on skeleton lazy images, and optional host WebP `<picture>` upgrades (clinic asset paths stay in the host).

## [1.4.3] - 2026-10-02

### Added

- **Form types:** admin mutations (templates, revisions, sections, content schema/values, CSRF-only actions) + demo `DemoLoginType`; public inline modal shell `InlineFieldModalType`.

### Changed

- **Twig (REQ-TWIG-003/005):** all bundle/demo form-submitting templates use `form_start` + children loop + `form_end` (no raw `<form`/`<input`).
- **Coverage:** PHP Clover **elements ≥99%** (local ~99.73%); `coverage-check-100.php` gate aligned to ≥99% (REQ-TEST-003 / CI).
- **README:** Tests section reports element coverage; Phase 9 marked done.

### Notes

- Backward compatible for hosts on `^1.4`. **No** Doctrine schema changes. Controllers that still read `Request` for CSRF remain compatible with empty form block prefixes.

## [1.4.2] - 2026-10-02

### Added

- **Example Grapes pack:** [`examples/acme-grapes-block-pack/`](../examples/acme-grapes-block-pack/) — copy-ready Composer package + cookbook link.
- **Tests:** unit coverage for `DocumentStructureValidator`, `ClassicPageTreeBuilder`, `ContentFieldsService`, content-field / capability enums (PHP lines ≥90%).

### Changed

- **Canvas TypeScript:** removed `@ts-nocheck` from `page-builder-canvas.ts`; typed boot helpers via `grapes-types.ts` (`ContentFieldCanvasConfig`, `TwigCanvasVariable`, …). Rebuilt public canvas JS.

### Notes

- Backward compatible for hosts on `^1.4`. **No** Doctrine schema changes. Optional: rebuild canvas assets if vendoring bundle JS. See [UPGRADING.md](UPGRADING.md#142).

## [1.4.1] - 2026-10-02

### Added

- **Cookbook:** [docs/COOKBOOK.md](COOKBOOK.md) — Grapes block packs + template sharing recipes (linked from README / USAGE / WIDGET_AUTHORS).
- **Admin UX:** list badges Grapes vs legacy; Sections editor warning banner (`admin.sections.legacy_*`).
- **Engine extraction:** `DocumentStructureValidator`, `ClassicPageTreeBuilder`; Grapes locale sanitize via `GrapesDocumentSanitizer::sanitizeLocaleContent()`.
- **Frontend types:** `grapes-types.ts` for canvas config/structure helpers (canvas editor surface still `@ts-nocheck`).

### Notes

- Backward compatible for hosts on `^1.4`. **No** Doctrine schema changes. Optional: rebuild canvas assets if vendoring bundle JS. See [UPGRADING.md](UPGRADING.md#141).
## [1.4.0] - 2026-10-02

### Added

- **Template apply SEO (optional):** `include_seo` copies `BuilderPageTranslation` meta/OG/canonical/robots from template snapshot `structure.templateSeoByLocale` (saved with the template; stripped from live pages). Default **off** (BC).
- **Web Profiler:** effective capabilities (`access`/`layout`/`content`/`publish`/`templates`) + render `fieldKeys`.
- **Demo CSRF WebTestCase:** HTTP kernel tests for document + content-field save (403 missing token / 200 valid).
- **Frontend DX:** bundle assets via **pnpm + Vite + TypeScript**; demo uses **Pentatrion Vite** (`vite_entry_*`).
- **Phase 7 media + tags + nesting:** media library list (`GET …/assets`) + content picker; Grapes Asset Manager seed; Dynamic tag trait on text/link/image; composite nesting safety cap **32**. Spec: [`specs/008-phase7-media-tags-nesting/spec.md`](../specs/008-phase7-media-tags-nesting/spec.md).
- **Demo S3 mock:** Compose service `s3` (Adobe S3Mock); `assets_upload.storage: s3` via `App\Demo\DemoS3Helper` (host port 9190).
- **Phase 6b content wave 2:** nested composites (depth 2), Twig-free slots `[[fields.path]]`, Grapes **Content fields** blocks, image upload + page reference select in content admin. Spec: [`specs/007-phase6b-content-wave2/spec.md`](../specs/007-phase6b-content-wave2/spec.md).
- **Demo `/fields` use case** + expanded Playwright e2e/screenshots for [BUILDER-MANUAL.md](BUILDER-MANUAL.md).
- **Phase 6 content hardening:** field types `repeater`, `group`, `reference`; nested subfields (one level); required validation on publish; template apply options `include_field_schema` / `include_field_values`. Spec: [`specs/006-phase6-content-hardening/spec.md`](../specs/006-phase6-content-hardening/spec.md).
- **Content fields (Phase 5 MVP):** `structure.fields` schema + `structure.fieldValues[locale]`; Twig context `fields.*`; admin UI `/pages/{pageKey}/content` (+ schema for layout editors). Spec: [`specs/005-content-fields-i18n/spec.md`](../specs/005-content-fields-i18n/spec.md).
- **Inline editable field component:** `nowo_page_builder_field(pageKey, key, { type, label, labels, … })` — public value for everyone; pencil + modal when `canContent()`; types include `html`, `raw`, `number`, `icon`, ….
- **Translatable field labels:** schema `labels[locale]` with fallback (`locale` → `default_locale` → singular `label` → key); admin content UI edits label per locale tab.
- **Capabilities:** `canLayout()` / `canContent()` / `canPublish()` / `canTemplates()` (+ Twig `nowo_page_builder_can()`); roles `layout_roles`… **or** custom `access_checker`; `PageBuilderKitAccessGuard` for controllers.
- CSRF coverage for document save (403/200) and XSS sanitizer matrix tests.
- FrankenPHP worker audit refreshed for v1.3 + Phase 5 surface.
- Spec Kit: `specs/005-content-fields-i18n/spec.md` + inventory updates.

### Changed

- Classic schema v1 / Sections editor documented as **legacy**; Grapes + content fields are the primary path.
- Demo `s3-init`: treat HTTP **409** (bucket exists) as success; retry loop for S3Mock race on first boot.

### Fixed

- Packagist/dist installs: ensure `demo/` is never shipped (`composer.json` `archive.exclude` + `.gitattributes` `export-ignore` + `make check-composer-archive`).

### Security

- Fine-grained admin route gating by capability (roles `*_roles` or custom `access_checker` / `PageBuilderKitAccessGuard`).

### Notes

- Backward compatible for hosts on `^1.3`. **No** Doctrine schema changes. Optional: content fields, capability roles, media library, template `include_seo`. See [UPGRADING.md](UPGRADING.md#140).

## [1.3.0] - 2026-09-28

### Security

- Default `html.sanitize.strategy` is **`allowlist`** (was `none`). Set `none` only for fully trusted staff editors. See [SECURITY.md](SECURITY.md) and [UPGRADING.md](UPGRADING.md#130).

## [1.2.0] - 2026-09-27

### Added

- Visual side-by-side HTML/CSS (and classic JSON) panels on revision diff (`DocumentDiff` panels + admin UI).
- Template JSON **export/import** for sharing templates across projects (`PageTemplateService`, admin `/templates/export`, `/templates/import`).
- GrapesJS **block packs** (`GrapesBlockPackInterface`, `GrapesBlockPackRegistry`, canvas `blockPacks`) — analogous to classic widget packs; see [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md#grapesjs-block-packs).
- Demo: `DemoGrapesBlockPack`, composite route `/multi-render` (collector shows ≥3 renders), labeled demo revisions for Versions/Diff UI.
- Web Profiler toolbar/menu: layout SVG icon instead of the `PBK` text label.

### Changed

- Spec Kit follow-ups marked shipped ([SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md#follow-ups-optional)).
- Demo use-case matrix documents composite (non-seeded) routes such as `multi-render`.

### Notes

- Backward compatible for hosts on `^1.1`. **No** Doctrine schema changes. Optional: register Grapes block packs; use template export/import for cross-project sharing. See [UPGRADING.md](UPGRADING.md#120).

## [1.1.1] - 2026-09-27

### Changed

- Spec Kit: formal closure of Phase 2–4 documentation — `specs/002-phase2-grapesjs`, `specs/003-phase3-revisions-templates`, `specs/004-phase4-external-widgets`, refreshed canonical `code-inventory.md`.
- Index updates in [SPEC-KIT.md](SPEC-KIT.md), [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md), and [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md).

### Notes

- Documentation-only release; **no runtime or schema changes**. Hosts on `^1.1` need no upgrade steps. See [UPGRADING.md](UPGRADING.md#111).

## [1.1.0] - 2026-09-27

### Added

- **Web Profiler DataCollector** (`debug.collector`, REQ-DEBUG-001): toolbar panel with renders, public outcomes, admin actions, Twig/timing diagnostics (dev only, FrankenPHP-safe reset).
- **Draft preview** on `/p/{pageKey}` for users who pass `PageBuilderKitAccessCheckerInterface` (banner); anonymous visitors still get 404 for drafts.
- **Revision diff** UI/API (`…/revisions/{id}/diff`) via `DocumentDiff` / `PageRevisionService::diff()`.
- **Duplicate page**, JSON **export/import** (`DocumentImportExportService`, `formatVersion: 1`).
- **Page templates** library (`BuilderPageTemplate`, admin `/templates`).
- **Widget packs** (`WidgetPackInterface`, `WidgetPackRegistry`) + [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md).
- Admin SEO / create forms: Bootstrap 5 form theme + card sections for clearer layout.
- **REQ-DEMO-013:** Playwright e2e; demo screenshots capture full viewport context including the Symfony Web Profiler toolbar (`docs/images/demo/overview.png`, `interaction.png`).

### Changed

- **Doctrine ORM SortDirection:** replace string `'ASC'`/`'DESC'` in `#[ORM\OrderBy]` and QueryBuilder ordering with `SortDirection` instances; require `doctrine/orm` `^3.7`.
- Spec Kit roadmap aligned with shipped Phase 3/4 and DX collector ([SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md)).

### Notes

- Hosts upgrading from 1.0.0 need a Doctrine schema update for `pb_page_template`. See [UPGRADING.md](UPGRADING.md#110).

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
