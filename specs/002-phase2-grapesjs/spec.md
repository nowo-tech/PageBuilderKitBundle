# Phase 2 — GrapesJS canvas (shipped)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **Shipped (v1.0.0)**  
**Roadmap:** [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#phase-2--grapesjs-canvas-shipped)  
**Integrator docs:** [ARCHITECTURE.md](../../docs/ARCHITECTURE.md), [USAGE.md](../../docs/USAGE.md)  
**Inventory:** [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

## Overview

Phase 2 replaces the Phase 1 classic-only canvas with a **GrapesJS** admin editor while keeping schema **v1** readable/renderable. New pages use schema **v2** (`engine: grapesjs`). Classic nesting beyond a flat section → column → widget tree is delivered via the `container` widget type and Grapes free-form HTML/CSS.

## Functional requirements (`FR-P2-*`)

### Document schema (`FR-P2-DOC-*`)

| ID | Requirement |
| --- | --- |
| FR-P2-DOC-001 | Schema **v2** documents use `engine: grapesjs` with `html`, `css`, `grapes`, and optional `localeContent` |
| FR-P2-DOC-002 | `DocumentNormalizer` accepts and normalizes both schema v1 and v2 |
| FR-P2-DOC-003 | Legacy schema **v1** remains loadable, savable (via Sections), and publicly renderable |

### Admin canvas (`FR-P2-ADM-*`)

| ID | Requirement |
| --- | --- |
| FR-P2-ADM-001 | GrapesJS canvas at `{web_ui.path_prefix}/pages/{pageKey}/canvas` (CDN/ESM + preset-webpage plugins) |
| FR-P2-ADM-002 | `GrapesJsFrontendConfig` exposes plugin/CDN options from bundle config |
| FR-P2-ADM-003 | Document JSON API (GET/POST) persists Grapes structure with CSRF (`page_builder_document`) |
| FR-P2-ADM-004 | Classic **Sections** editor remains available for per-locale widget props on schema v1 trees |
| FR-P2-ADM-005 | Optional Asset Manager uploads (`grapesjs.assets_upload`) via local or S3 storage |

### Classic nesting and appearance (`FR-P2-WGT-*`)

| ID | Requirement |
| --- | --- |
| FR-P2-WGT-001 | Classic `container` widget type supports nested children within a configured max depth |
| FR-P2-WGT-002 | Element appearance / Style–Advanced helpers normalize props for classic widgets |

### Public render (`FR-P2-REN-*`)

| ID | Requirement |
| --- | --- |
| FR-P2-REN-001 | `PageRenderProvider` renders Grapes pages (HTML/CSS + locale content) |
| FR-P2-REN-002 | Optional sandboxed Twig in Grapes HTML (`grapesjs.twig`) with host context provider |
| FR-P2-REN-003 | `GrapesDocumentSanitizer` applies configured HTML sanitization on save/render |

## Success criteria (`SC-P2-*`)

| ID | Criterion |
| --- | --- |
| SC-P2-01 | Demo Grapes page (e.g. pricing) edits on canvas and saves via document API |
| SC-P2-02 | Public `/p/{pageKey}` (or showcase route) renders a published Grapes document |
| SC-P2-03 | Classic v1 seed pages still render; Sections editor opens for classic keys |
| SC-P2-04 | `make test` covers Grapes normalizer/sanitizer/render paths |

## Non-goals (Phase 2)

- Revision history UI, templates library, duplicate/import-export → Phase 3
- External classic widget packs → Phase 4 baseline ([`../004-phase4-external-widgets/spec.md`](../004-phase4-external-widgets/spec.md))
- Grapes **block packs** analogous to widget packs → shipped follow-up (`GrapesBlockPackInterface`)

## Validation

```bash
make test
make -C demo/symfony8 demo-smoke
```

## See also

- Phase 1 baseline: [`../001-baseline/spec.md`](../001-baseline/spec.md)
- Phase 3: [`../003-phase3-revisions-templates/spec.md`](../003-phase3-revisions-templates/spec.md)
