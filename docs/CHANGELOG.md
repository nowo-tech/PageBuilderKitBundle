# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## Table of contents

- [[Unreleased]](#unreleased)
- [[1.11.0] - 2026-10-29](#1110---2026-10-29)
- [[1.10.2] - 2026-10-28](#1102---2026-10-28)
- [[1.10.1] - 2026-10-28](#1101---2026-10-28)
- [[1.10.0] - 2026-10-28](#1100---2026-10-28)
- [[1.9.1] - 2026-10-24](#191---2026-10-24)
- [[1.9.0] - 2026-10-22](#190---2026-10-22)
- [[1.8.1] - 2026-10-18](#181---2026-10-18)
- [[1.8.0] - 2026-10-13](#180---2026-10-13)
- [[1.7.0] - 2026-10-13](#170---2026-10-13)
- [[1.6.1] - 2026-10-13](#161---2026-10-13)
- [[1.6.0] - 2026-10-09](#160---2026-10-09)
- [[1.5.1] - 2026-10-09](#151---2026-10-09)
- [[1.5.0] - 2026-10-07](#150---2026-10-07)
- [[1.4.4] - 2026-10-06](#144---2026-10-06)
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

### Security

- The GrapesJS project JSON (`grapes`, top level and per locale) is now sanitised on save and when the canvas loads it: `on*`, `srcdoc`, `formaction` attributes, script-scheme / non-image `data:` URLs, `script` / `iframe` / `object` / `embed` / `form` / `link` / `meta` / `base` components and `script*` props (unless `grapesjs.allow_scripts`) are removed. `loadProjectData()` renders components without HTML parsing, so a stored handler ran in the next editor's admin session (REQ-SEC-004 re-audit L1, CWE-79).

## [1.11.0] - 2026-10-29

PageBuilderKit: import requires layout/publish; fail closed without form CSRF See [UPGRADING](UPGRADING.md#to-1110).

### Security

- Page import (`POST …/pages/import`) now also requires the `layout` capability, plus `publish` when the payload sets `publish: true`; a templates-only role could overwrite and publish any page (REQ-SEC-004 re-audit L3, CWE-863).
- New `CsrfProtectionPass`: the container refuses to compile when the admin controllers are registered without a CSRF token manager or with `framework.form.csrf_protection` disabled; the create-page, SEO and sections forms relied on it and failed open (REQ-SEC-004 re-audit L4, CWE-352).

## [1.10.2] - 2026-10-28

PageBuilderKit: url / image content fields refuse javascript:, vbscript: and non-image data: URLs (stored XSS). See [UPGRADING](UPGRADING.md#to-1102).

### Security

- **Stored XSS through `url` / `image` content fields (Medium, [GHSA-pgpr-xrgq-c8xp](https://github.com/nowo-tech/PageBuilderKitBundle/security/advisories/GHSA-pgpr-xrgq-c8xp)).** Content field values were stored without a scheme
  check and printed in `href` / `src`: a user with the `content` capability (default `ROLE_EDITOR`) could save
  `javascript:…` through the inline save API and run script for visitors or admins who clicked the link (since 1.4.0).
  `ContentFieldsNormalizer` now drops `javascript:`, `vbscript:` and non-image `data:` URLs, and the templates
  (`public/_editable_field`, the button / image widgets, the admin image preview) print them through the new
  `pbk_safe_url` filter, which also neutralises rows saved before this release. The policy is `Security\Html\SafeUrl`,
  shared with the HTML sanitizer.

## [1.10.1] - 2026-10-28

Performance: admin page lists fetch-join the page document (no 1 + N queries). See [UPGRADING](UPGRADING.md#to-1101).

### Performance

- **Admin page lists without 1 + N queries.** `BuilderPage::$document` is the inverse side of a one-to-one, which
  Doctrine cannot lazy-load: `findAllOrdered()` / `findOrderedSlice()` issued one extra query per listed page. Both
  now fetch-join the document (one query); an integration test pins the query count.

## [1.10.0] - 2026-10-28

Security: empty role lists deny instead of granting everyone (every list empty fails compilation). See [UPGRADING](UPGRADING.md#to-1100).

### Security

- An empty `security.layout_roles` / `content_roles` / `publish_roles` / `templates_roles` (or `access_roles`) list now
  **grants nothing**. Before, `ConfigurablePageBuilderKitAccessChecker` treated an empty capability list as "allow"
  (`emptyAllows`), so e.g. `layout_roles: []` gave layout — and through it content, publish and templates — to every
  user reaching the admin, even with a non-empty `access_roles`. The `Configuration` help and the class docblock that
  described this as "no extra gate beyond access_roles" were wrong and are fixed.
- The container refuses to compile (`LogicException`) when `access_roles` and every `*_roles` list are empty while
  `security.allow_unauthenticated` is `false` and no `security.access_checker` is set. See [UPGRADING](UPGRADING.md#unreleased).

### Fixed

- Upload size check in `LocalFilesystemAssetStorage` / `AwsS3AssetStorage` compares only an integer `UploadedFile::getSize()` (it returns `int|false` in current symfony/http-foundation; PHPStan flagged the `!== null` guard as always true).

## [1.9.1] - 2026-10-24

Bundle views render form bodies through the child loop (REQ-TWIG-003). See [UPGRADING](UPGRADING.md#to-191).

### Changed

- `admin/pages/sections.html.twig` renders each widget's props through a child loop over its sub-form (`form.widgets[widget.id]`) instead of one `form_row(form.widgets[id][field])` per entry of `widget.fields`; the field order is unchanged (`SectionsEditType` adds them in `widget.fields` order) (REQ-TWIG-003).

## [1.9.0] - 2026-10-22

Wave-2 REQ compliance: structured logging, paginated admin list, injectable clock, autoconfigured services, split test suites. See [UPGRADING](UPGRADING.md#to-190).

### Added

- Structured logging (`Psr\Log\LoggerInterface`, autowired) for asset uploads, page import (start / finish / failure) and publishing, without content; policy in `docs/SECURITY.md#logging` (REQ-OBS-001).
- Admin page list is paginated (50 per page, `?page=`), with `BuilderPageRepository::findOrderedSlice()` / `countAll()` and translated pagination labels (REQ-PERF-001).

### Changed

- `DocumentService::publish()` dates `publishedAt` with the optional injected `Psr\Clock\ClockInterface` (REQ-DI-001); runtime dependencies `psr/clock`, `psr/log`.
- Services rely on autoconfiguration: manual `controller.service_arguments`, `form.type` and the trace `kernel.reset` tags removed (REQ-SF-003).
- PHPUnit suites split into `unit` and `integration`; the controller coverage exclusion is documented (covered end to end by the demo Playwright suite) (REQ-TEST-002/003).
- Rector uses the PHP 8.4 sets (package floor) with at most 2 workers (REQ-CS-003); `release-check` runs `check-no-raw-html-form` (REQ-MAKE-002).
- `composer.json`: `support.docs` added, keyword stuffing removed (`elementor`, `nowo`) (REQ-PKG-004).
- Demo: the database bootstrap retries until MySQL accepts connections (clean `make up` failed with "Connection refused"); the Playwright image follows `@playwright/test` 1.64.1 from the lock. README coverage 99.58% as measured.
- `.scripts/check-no-cursor-coauthor.sh` accepts git worktrees (REQ-GIT-001).
- Dependencies refreshed (Composer and pnpm/npm lock files of the bundle and its demos, campaign 2026-10-19).
- `.php-cs-fixer.dist.php`: `new ParallelConfig(2)` (REQ-CS-001).
- Documentation: wave-1 REQ compliance — versioned `.cursor/` (Engram `mcp.json`, rules incl. `01-git-commits.mdc`, Spec Kit skills; only `sandbox.json` stays ignored), README documentation list order (duplicate Builder manual removed) and Roadmap moved after License, CODEOWNERS header, CONTRIBUTING Cursor Agent settings, TOCs for long docs, full GitHub Spec Kit manual, inventory refresh (165/165).

## [1.8.1] - 2026-10-18

Allows nowo-tech/ui-kit-bundle 2.x (^1.4 || ^2.2) so hosts can move to UiKit 2.2. See [UPGRADING](UPGRADING.md#to-181).

### Changed

- Allows nowo-tech/ui-kit-bundle 2.x (`^1.4 || ^2.2`); the dev lock and the demo lock use 2.2.0. Templates are unchanged (compatibility release). With UiKit 2.x `nowo-ui.css` ships in `@layer nowo-ui`, so unlayered host/framework CSS wins equal-specificity conflicts with kit rules in the admin pages; to keep the 1.x cascade, override the admin layout `stylesheets` block and link `css/nowo-ui.unlayered.css` (UiKit 2.x only).

## [1.8.0] - 2026-10-13

### Deprecated

- `doctrine.connection`: the option was only stored as the `nowo_page_builder_kit.doctrine.connection` parameter and never wired to Doctrine. It is now deprecated (setting it triggers a deprecation notice) and documented as ignored; map the bundle entities on the entity manager you need in `doctrine.orm.entity_managers` instead.

## [1.7.0] - 2026-10-13

### Security

- Asset uploads: the stored extension is now always derived from the detected MIME type (local and S3 storage). Uploads whose file-name extension does not match the detected type are rejected, so a GIF/PHP polyglot named `x.php` can no longer be written as an executable `.php` file under `public/uploads/page-builder/`.
- Asset uploads: `image/svg+xml` was removed from the default `assets_upload.allowed_mime` (scriptable SVG served same-origin is stored XSS). Add it back explicitly if you need SVG uploads.
- Document API: the `_redirect` form field rejects protocol-relative (`//evil.com`), backslash (`/\evil.com`) and control-character targets (open redirect).
- Admin access: the media library route `admin_page_builder_assets_list` now requires the **layout** capability, like `admin_page_builder_asset_upload` (previously any editor could list uploaded assets).

## [1.6.1] - 2026-10-13

### Changed

- Development: `composer.json` pins `config.platform.php` to 8.4.1 so the committed lock stays installable on the minimum PHP; CI overrides the platform per matrix cell.
- Repository: `.twig-cs-fixer.cache` is now git-ignored.

### Dependencies

- `nowo-tech/form-kit-bundle` 2.7.0 → 2.7.1 (development lock; #3).
- `nowo-tech/ui-kit-bundle` 1.10.0 → 1.11.0 (development lock; #4).
- `igor-php/igor-php` 0.9.7 → 0.10.1 (require-dev constraint + development lock; #5).
- `doctrine/orm` 3.7.4 → 3.7.5 (development lock; #6).

## [1.6.0] - 2026-10-09

### Security

- **Twig error fallback XSS (GrapesJS pages).** `GrapesDocumentSanitizer` HTML-decodes everything inside `{{ }}`, `{% %}` and `{# #}` so Twig can compile it. When the editor HTML had a Twig syntax/sandbox error (or Twig was disabled), `GrapesTwigRenderer` returned that decoded *source*, so `{{ &lt;script&gt;… }}` reached the page as a live `<script>`. The fallback now returns the sanitized markup with Twig tokens left encoded (inert text).
- **Twig output re-decoding.** The post-render sanitize pass no longer restores Twig delimiters, so an autoescaped context/field value such as `{{ <img onerror=…> }}` can no longer be decoded back into markup.
- **HTML5 final gate.** Public editor HTML (Grapes pages, `/p/{pageKey}` preview, classic `text` / `html` widgets, inline HTML fields) now always passes `PublicHtmlHardener` (PHP 8.4 `Dom\HTMLDocument`, the browser parsing algorithm) right before `|raw`-style output: drops `script`, `style`, `iframe`, `object`, `embed`, `form`, `math`, `template`, `noscript`, `xmp`, `noembed`, SVG `animate`/`set`/`foreignObject`, … plus `on*`, `srcdoc`, `formaction`, `action` and `javascript:` / `vbscript:` / non-image `data:` URLs (including `srcset` candidates and HTML5 entity obfuscation such as `&colon;` / `&Tab;`). Grapes CSS escapes `<` (`\3C `) so nested `</sty</stylele>` cannot close the `<style>` element.
- **CSP.** Inline `<script>` / `<style>` in kit templates carry `nonce` from the request attribute `csp_nonce` (nowo-tech kit convention). Inline `onsubmit` handlers were removed (inline-edit modal form, template delete and revision restore confirmations).

### Added

- `Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener` service (`harden()`, `hardenCss()`; honours `grapesjs.allow_scripts`).
- Twig filters `|pbk_harden_html` and `|pbk_harden_css` (registered by `PageBuilderKitExtension`).
- `GrapesDocumentSanitizer::sanitizeHtml(string $html, bool $restoreTwigDelimiters = true)` — pass `false` for markup that will be printed.
- `js/page-builder-admin.js` (`form[data-pbk-confirm]` confirmation listener) and a `submit` listener for the inline-edit modal form.
- `PageRenderProvider` optional constructor argument `?PublicHtmlHardener $publicHtmlHardener`.

## [1.5.1] - 2026-10-09

### Security

- `AllowlistPageBuilderHtmlSanitizer`: the internal wrapper is identified by reference. Any input element with `id="pbk-root"` used to skip sanitizing (`<script id="pbk-root">`, `<img id="pbk-root" onerror>` and `<meta id="pbk-root" http-equiv=refresh>` survived).
- URL attributes (`href`, `src`) ignore ASCII whitespace / control characters before checking the scheme (`java\tscript:` and `jav&#x09;ascript:` were accepted) and also reject `vbscript:` and non-image `data:` URLs.

### Dependencies

- Bundle lockfile: `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.1; dev `phpstan/phpstan` 2.3.1, `nowo-tech/phpstan-frankenphp` 1.2.3.
- Demo (`demo/symfony8`): `nowo-tech/form-kit-bundle` 2.6.1, `nowo-tech/ui-kit-bundle` 1.9.1, `aws/aws-sdk-php` 3.400.0; regenerated `config/reference.php`.

### Notes

- Backward compatible for hosts on `^1.5`. **No** Doctrine schema changes. See [UPGRADING.md](UPGRADING.md#151).

## [1.5.0] - 2026-10-07

### Added

- `Nowo\PageBuilderKitBundle\Service\PublicBindHydrator` (+ `PublicBindHydratorInterface`) — wraps public Grapes `<span data-pbk-bind="key">` slots with the inline-edit pencil/modal (`public/_editable_field.html.twig`, `InlineFieldModalType`) for users with the `content` capability; visitors get the HTML back untouched. No page SELECT.
- `Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface` — host catalogue of field `key` / `type` / `label` per page key. Default `NullContentFieldDefinitionProvider` (empty); alias the interface to a host service to override.
- `Nowo\PageBuilderKitBundle\Util\ContentSectionFilter` — static `sanitize()` and `filterSchema()` for `?section=` deep-links.
- `Nowo\PageBuilderKitBundle\Repository\BuilderPageStatusQueryInterface` / `BuilderPageStatusQuery` — `findStatusByPageKey()` returns the `PageStatus` scalar without hydrating the document.
- Twig functions `nowo_page_builder_hydrate_binds(html, pageKey)`, `nowo_page_builder_sanitize_section(raw)` and `nowo_page_builder_filter_schema_by_section(schema, section)`.

### Notes

- Backward compatible for hosts on `^1.4`. **No** Doctrine schema changes. `BuilderPageRepositoryInterface` is unchanged. See [UPGRADING.md](UPGRADING.md#150).

## [1.4.4] - 2026-10-06

### Added

- `Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer` — strips invalid `</source>` around `<picture>` images, adds a 1×1 GIF `src` on skeleton lazy images, and optional host WebP `<picture>` upgrades (host asset paths stay out of the kit).

### Notes

- Backward compatible for hosts on `^1.4`. **No** Doctrine schema changes. Optional: inject the autowired service after Grapes public HTML render. See [UPGRADING.md](UPGRADING.md#144).

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
