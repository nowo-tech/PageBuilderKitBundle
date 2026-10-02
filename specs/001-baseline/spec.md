# Baseline specification — Page Builder Kit Bundle (Phase 1)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Namespace:** `Nowo\PageBuilderKitBundle`  
**Bundle class:** `Nowo\PageBuilderKitBundle\NowoPageBuilderKitBundle`  
**Config alias:** `nowo_page_builder_kit`  
**Status:** Phase 1 shipped (v1.0.0). Phases 2–7 and product polish (Phase 9 / v1.4.x) are documented in sibling Spec Kit dirs.

## Overview

Page Builder Kit Bundle is a reusable Symfony visual page builder backed by Doctrine. Phase 1 delivers a section/column/widget document model, admin canvas with drag-and-drop, CSRF-protected JSON document API, locale-specific widget props, publish workflow, and Twig-based public rendering for six core widget types.

Later phases (shipped / in progress):

- Phase 2 — GrapesJS canvas / schema v2: [`specs/002-phase2-grapesjs/spec.md`](../002-phase2-grapesjs/spec.md)
- Phase 3 — Revisions and templates: [`specs/003-phase3-revisions-templates/spec.md`](../003-phase3-revisions-templates/spec.md)
- Phase 4 — External classic widget packs (baseline): [`specs/004-phase4-external-widgets/spec.md`](../004-phase4-external-widgets/spec.md)
- Phase 5 — Content fields + capabilities (MVP): [`specs/005-content-fields-i18n/spec.md`](../005-content-fields-i18n/spec.md)
- Phase 6 — Content hardening: [`specs/006-phase6-content-hardening/spec.md`](../006-phase6-content-hardening/spec.md)
- Phase 6b — Content wave 2: [`specs/007-phase6b-content-wave2/spec.md`](../007-phase6b-content-wave2/spec.md)
- Phase 7 — Media library, dynamic tags, nesting: [`specs/008-phase7-media-tags-nesting/spec.md`](../008-phase7-media-tags-nesting/spec.md)
- Phase 9 — Product polish + standards compliance: [`specs/009-product-polish-compliance/spec.md`](../009-product-polish-compliance/spec.md)

Roadmap and optional follow-ups: [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#roadmap).

Canonical production inventory (all phases): [`code-inventory.md`](code-inventory.md).

## User scenarios (`US-*`)

### US-01 — Compose pages on the canvas (Priority: P1)

As an editor, I open `/admin/page-builder/pages/{pageKey}/canvas` and arrange sections, columns, and widgets.

### US-02 — Save and publish documents (Priority: P1)

As an editor, I save structure and locale-specific widget props through the document API and publish the page when ready.

### US-03 — Render published pages (Priority: P1)

As a developer, I resolve a rendered page tree for a page key and locale, then output it through Twig.

### US-04 — Configure the bundle (Priority: P2)

As an integrator, I configure locales, editor access, admin shell, CSS framework hint, HTML sanitization, and optional table prefix.

### US-05 — Validate boot behavior (Priority: P3)

As a maintainer, I boot the Symfony 8 FrankenPHP demo on port **8137** and run QA workflows.

## Functional requirements (`FR-*`)

### Document model (`FR-DOC-*`)

| ID | Requirement |
| --- | --- |
| FR-DOC-001 | A page owns one `BuilderDocument` with JSON **structure** (schema version 1) |
| FR-DOC-002 | Structure contains ordered **sections**, each with **columns**, each with **widgets** (`id`, `type`) |
| FR-DOC-003 | Translatable content lives in **widget props** per locale (`BuilderDocumentLocale`) |
| FR-DOC-004 | `DocumentNormalizer` validates and normalizes structure on save |

### Widgets (`FR-WGT-*`)

| ID | Requirement |
| --- | --- |
| FR-WGT-001 | Core types: `heading`, `text`, `html`, `image`, `button`, `spacer` |
| FR-WGT-002 | Each type exposes default props, sanitization, and a public Twig template |
| FR-WGT-003 | `WidgetTypeRegistry` collects tagged `WidgetTypeInterface` services |

### Admin UI and API (`FR-ADM-*`)

| ID | Requirement |
| --- | --- |
| FR-ADM-001 | Page list and create at `/admin/page-builder/pages` |
| FR-ADM-002 | Canvas at `/admin/page-builder/pages/{pageKey}/canvas` |
| FR-ADM-003 | GET/POST `/admin/page-builder/pages/{pageKey}/document` for load/save |
| FR-ADM-004 | POST publish endpoint sets `PageStatus::Published` |
| FR-ADM-005 | Mutating API calls require CSRF token `page_builder_document` |

### Public rendering (`FR-REN-*`)

| ID | Requirement |
| --- | --- |
| FR-REN-001 | `PageRenderProvider::getRenderedTree()` merges structure, props, and locale fallback |
| FR-REN-002 | Twig function `nowo_page_builder_render()` exposes the rendered tree |
| FR-REN-003 | Route `/p/{pageKey}` renders published pages (draft preview for editors is Phase 3 — see FR-P3-PREV-001) |
| FR-REN-004 | Public templates live under `@NowoPageBuilderKitBundle/widgets/` |

### Security (`FR-SEC-*`)

| ID | Requirement |
| --- | --- |
| FR-SEC-001 | Routes `admin_page_builder_*` require access checker (REQ-UI-002) |
| FR-SEC-002 | `allow_unauthenticated` may relax access only when explicitly configured |
| FR-SEC-003 | Configurable `html.sanitize` strategy sanitizes widget HTML on save/render |
| FR-WRK-001 | Shared services remain correct in FrankenPHP workers; `BuilderLocales` static bind cleared on terminate |

### Configuration and DI (`FR-CFG-*`, `FR-DI-*`)

| ID | Requirement |
| --- | --- |
| FR-CFG-001 | Configuration keys: `default_locale`, `locales`, `security`, `web_ui`, `doctrine`, `html` |
| FR-CFG-002 | Defaults include `es`, `[es,en]`, `ROLE_EDITOR`, `tailwind`, bundle admin layout |
| FR-DI-001 | Extension wires services, widget pass, Twig paths, and FormKit profile prepend |

### Persistence (`FR-ORM-*`, `FR-I18N-*`)

| ID | Requirement |
| --- | --- |
| FR-ORM-001 | Entities persist pages, translations, documents, locale props, revision snapshots |
| FR-ORM-002 | Optional `table_prefix` via `TablePrefixListener` |
| FR-I18N-001 | Widget props and page translations use configured locales with default fallback |

### Demo and quality (`FR-DEMO-*`, `FR-QA-*`)

| ID | Requirement |
| --- | --- |
| FR-DEMO-001 | Symfony 8 FrankenPHP demo defaults to port **8137** |
| FR-QA-001 | Tests, docs, and Spec Kit baseline validate Phase 1 behavior |

## Non-goals (this baseline)

Historical Phase 1 non-goals that are **now shipped** elsewhere:

- Nested classic widgets / Grapes free-form layout → Phase 2 (`002-phase2-grapesjs`)
- Revision history UI, templates, duplicate, import/export → Phase 3 (`003-phase3-revisions-templates`)

Still deferred (not Phase 1–5 MVP acceptance criteria):

- External widget / template **marketplace** (governed sharing across orgs)
- Full theme/site builder ownership for host applications
- Phase 6 hardening (required-field validation on publish, field-aware template apply/SEO, repeater/reference types) → [`../005-content-fields-i18n/spec.md`](../005-content-fields-i18n/spec.md#non-goals-phase-5-mvp)
- Optional follow-ups listed in [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#follow-ups-optional)

> Note: Grapes **block packs**, template JSON export/import, and visual revision diff shipped in **v1.2.0**. Content fields + capabilities MVP shipped as Phase 5 (unreleased). Classic Sections remain **legacy**.

## Success criteria (`SC-*`)

| ID | Criterion |
| --- | --- |
| SC-01 | Editors can create a page and save a document from the canvas |
| SC-02 | Published pages render at `/p/{pageKey}` or via `nowo_page_builder_render()` |
| SC-03 | [`code-inventory.md`](code-inventory.md) maps all production files under `src/` (current tree; all shipped phases) |
| SC-04 | Demo answers on `http://localhost:8137` |
| SC-05 | `make demo-smoke` returns HTTP 200 |

## Validation

```bash
make test
make phpstan
make validate-translations
make demo-smoke
make release-check
```

## Related requirements

See `docs/SPEC-DRIVEN-DEVELOPMENT.md` for `REQ-*` traceability and the full roadmap.
