# Phase 3 — Revisions and templates (shipped)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **Shipped (v1.1.0)**  
**Roadmap:** [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#phase-3--revisions-and-templates-shipped)  
**Integrator docs:** [USAGE.md](../../docs/USAGE.md), [UPGRADING.md](../../docs/UPGRADING.md#110), [CONFIGURATION.md](../../docs/CONFIGURATION.md#revisions)  
**Inventory:** [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

## Overview

Phase 3 adds operational CMS workflows on top of the Phase 1–2 document model: revision history with restore and summary diff, page duplicate, portable JSON import/export, a named page templates library, and draft preview for authorized editors on `/p/{pageKey}`.

## Functional requirements (`FR-P3-*`)

### Revisions (`FR-P3-REV-*`)

| ID | Requirement |
| --- | --- |
| FR-P3-REV-001 | When `revisions.enabled`, snapshots persist as `BuilderPageRevision` (structure + locale props) |
| FR-P3-REV-002 | Admin UI lists revisions and restores a selected revision to the live document |
| FR-P3-REV-003 | `PageRevisionService::diff()` / admin `…/revisions/{id}/diff` compare live vs revision (summary + changed paths) |
| FR-P3-REV-004 | Optional auto-snapshot on save/publish via `revisions.on_save` / `revisions.on_publish` |

### Duplicate and portability (`FR-P3-IO-*`)

| ID | Requirement |
| --- | --- |
| FR-P3-IO-001 | Duplicate page clones structure, locale props, and translations into a new draft `pageKey` |
| FR-P3-IO-002 | JSON export/import uses `DocumentImportExportService` with `formatVersion: 1` |

### Templates (`FR-P3-TPL-*`)

| ID | Requirement |
| --- | --- |
| FR-P3-TPL-001 | `BuilderPageTemplate` stores named structure + widget props by locale |
| FR-P3-TPL-002 | Admin templates library: save from page, list, apply (create draft page from template), delete |

### Draft preview (`FR-P3-PREV-*`)

| ID | Requirement |
| --- | --- |
| FR-P3-PREV-001 | `/p/{pageKey}` renders drafts for users who pass `PageBuilderKitAccessCheckerInterface` (banner) |
| FR-P3-PREV-002 | Anonymous visitors still receive 404 for unpublished pages |

## Success criteria (`SC-P3-*`)

| ID | Criterion |
| --- | --- |
| SC-P3-01 | With revisions enabled, save/publish can create snapshots; restore returns prior structure |
| SC-P3-02 | Diff page shows summary when live and revision differ; identical when equal |
| SC-P3-03 | Duplicate and import/export round-trip create a usable draft page |
| SC-P3-04 | Template save → apply creates a new draft with template structure |
| SC-P3-05 | Logged-in editor previews a draft at `/p/{pageKey}`; anonymous gets 404 |

## Non-goals (Phase 3)

Explicitly **deferred** (optional follow-ups, not acceptance criteria):

- Richer visual HTML/CSS side-by-side revision diff
- Template marketplace / sharing across projects
- Grapes block packs

See [Follow-ups](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#follow-ups-optional).

## Validation

```bash
make test
make phpstan
```

Upgrade hosts from 1.0.0: [UPGRADING.md § 1.1.0](../../docs/UPGRADING.md#110) (`pb_page_template`, draft preview behavior).

## See also

- Phase 1: [`../001-baseline/spec.md`](../001-baseline/spec.md)
- Phase 2: [`../002-phase2-grapesjs/spec.md`](../002-phase2-grapesjs/spec.md)
- Phase 4: [`../004-phase4-external-widgets/spec.md`](../004-phase4-external-widgets/spec.md)
