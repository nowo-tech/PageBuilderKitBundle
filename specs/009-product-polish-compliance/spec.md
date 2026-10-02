# Phase 9 — Product polish, DX, and standards compliance (v1.4.1–v1.4.3)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **Shipped** (product polish **v1.4.1** / **v1.4.2**; org-standards compliance **v1.4.3**)  
**Depends on:** [Phase 7](../008-phase7-media-tags-nesting/spec.md)  
**Inventory:** [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)  
**Changelog:** [CHANGELOG.md](../../docs/CHANGELOG.md#143---2026-10-02) · [UPGRADING.md](../../docs/UPGRADING.md#143)

## Overview

After content-field phases (5–7), this phase captures **product/DX polish** and **Nowo bundle-standards compliance** for the 1.4.x line.

Shipped:

1. Legacy admin UX (Grapes vs classic badges + Sections warning).
2. Integrator cookbook + copy-ready Grapes block-pack example.
3. Engine extraction (`DocumentStructureValidator`, `ClassicPageTreeBuilder`).
4. Typed Grapes canvas TypeScript (no `@ts-nocheck`) + `grapes-types.ts`.
5. PHP Clover **element** coverage ≥99% (`REQ-TEST-003`) + Twig forms (`REQ-TWIG-003` / `REQ-TWIG-005`) + README Tests (`REQ-TEST-007`).

## User scenarios (`US-P9-*`)

### US-P9-01 — Distinguish Grapes vs legacy pages (Priority: P1) — shipped

As an editor, I see at a glance which pages use GrapesJS vs classic Sections, and the Sections editor warns that the path is legacy.

### US-P9-02 — Ship a Grapes block pack without forking (Priority: P1) — shipped

As an integrator, I follow the cookbook and/or copy `examples/acme-grapes-block-pack/` to register host blocks.

### US-P9-03 — Maintain typed canvas sources (Priority: P2) — shipped

As a maintainer, I typecheck canvas TS with `pnpm typecheck` without `@ts-nocheck`.

### US-P9-04 — Meet org coverage and Twig form standards (Priority: P0) — shipped

As a maintainer, CI stays green (≥99% PHP coverage) and admin mutations use Symfony forms (`form_start` / children loop), not raw HTML forms.

## Functional requirements (`FR-P9-*`)

### Product polish (`FR-P9-UX-*` / `FR-P9-DX-*`) — shipped in v1.4.1–v1.4.2

| ID | Requirement | Status |
| --- | --- | --- |
| FR-P9-UX-001 | Admin page list badges distinguish Grapes vs classic/legacy engines | Done |
| FR-P9-UX-002 | Classic Sections editor shows a legacy warning banner (`admin.sections.legacy_*`) | Done |
| FR-P9-DX-001 | `docs/COOKBOOK.md` documents Grapes block packs + template sharing | Done |
| FR-P9-DX-002 | `examples/acme-grapes-block-pack/` is a copy-ready Composer pack implementing `GrapesBlockPackInterface` | Done |
| FR-P9-ENG-001 | `DocumentStructureValidator` validates Grapes v2 and classic v1 structures | Done |
| FR-P9-ENG-002 | `ClassicPageTreeBuilder` builds the classic public/admin sections tree | Done |
| FR-P9-ENG-003 | Grapes locale HTML/CSS sanitize goes through `GrapesDocumentSanitizer::sanitizeLocaleContent()` | Done |
| FR-P9-FE-001 | `grapes-types.ts` exports typed canvas config/structure helpers | Done |
| FR-P9-FE-002 | `page-builder-canvas.ts` typechecks under `strict` without `@ts-nocheck` | Done |
| FR-P9-FE-003 | Public canvas JS is rebuilt from Vite sources (`src/Resources/public/js/page-builder-canvas.js`) | Done |
| FR-P9-TEST-001 | Unit tests cover `DocumentStructureValidator`, `ClassicPageTreeBuilder`, `ContentFieldsService`, content-field / capability enums | Done |
| FR-P9-TEST-002 | PHP line coverage reported by local `make test-coverage` is ≥90% | Done (superseded by STD-001) |

### Org standards compliance (`FR-P9-STD-*`) — shipped in v1.4.3

| ID | Requirement | Maps to | Status |
| --- | --- | --- | --- |
| FR-P9-STD-001 | PHP Clover/element coverage on CI ≥ **99%** of includable `src/` | REQ-TEST-003 | Done (~99.73% elements) |
| FR-P9-STD-002 | Default-branch `ci.yml` green (coverage gate included) | REQ-CI-003 | Done (gate ≥99%) |
| FR-P9-STD-003 | Admin Twig that renders Symfony `FormView` uses `{% for child in form %}` + `form_row(child)` | REQ-TWIG-003 | Done |
| FR-P9-STD-004 | No raw `<form` / `<input` in bundle/demo Twig submitting data; use `form_start` / `form_row` / `form_end` | REQ-TWIG-005 | Done |
| FR-P9-STD-005 | README `## Tests and coverage` lists numeric PHP % and TS/JS (or N/A) | REQ-TEST-007 | Done |

## Non-goals

- Rewriting GrapesJS itself or vendoring official Grapes types
- Changing Doctrine schema
- Removing classic schema v1 support

## Success criteria

| ID | Criterion | Status |
| --- | --- | --- |
| SC-P9-01 | Cookbook + example pack discoverable from docs | Met |
| SC-P9-02 | `pnpm typecheck` exits 0 on canvas sources | Met |
| SC-P9-03 | Engine extract classes covered by unit tests | Met |
| SC-P9-04 | CI coverage job passes ≥99% on `main` | Met (local ≥99.73%; CI on release) |
| SC-P9-05 | Admin mutation Twig complies with TWIG-003/005 | Met |
| SC-P9-06 | README Tests section satisfies TEST-007 | Met |

## Implementation notes

- Releases: **v1.4.1** (UX/cookbook/engine extract/grapes-types), **v1.4.2** (typed canvas, example pack), **v1.4.3** (forms Twig + ≥99% elements).
- Example pack lives under `examples/` (repo root; kept in Packagist dist unless archive.exclude says otherwise).
- Compliance audit artifact: Cursor canvas `pagebuilder-kit-specs-compliance.canvas.tsx` (IDE-only; not shipped in package).
