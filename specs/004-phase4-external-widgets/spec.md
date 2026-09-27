# Phase 4 — External widgets (shipped baseline)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **Shipped baseline (v1.1.0)**  
**Roadmap:** [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#phase-4--external-widgets-shipped-baseline)  
**Author guide:** [WIDGET_AUTHORS.md](../../docs/WIDGET_AUTHORS.md)  
**Inventory:** [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

## Overview

Phase 4 baseline lets hosts and Composer packages ship **classic schema v1** widget types without forking the bundle. Packs group types for diagnostics; each type remains a tagged `WidgetTypeInterface` service. GrapesJS custom blocks stay host/CDN-owned (not part of this baseline).

## Functional requirements (`FR-P4-*`)

### Widget packs (`FR-P4-PACK-*`)

| ID | Requirement |
| --- | --- |
| FR-P4-PACK-001 | `WidgetPackInterface` exposes `getName()`, `getVersion()`, `getWidgetTypes()`, `getCapabilities()` |
| FR-P4-PACK-002 | Services implementing the pack interface are tagged `nowo_page_builder_kit.widget_pack` (autoconfigure enabled) |
| FR-P4-PACK-003 | `WidgetPackPass` injects tagged packs into `WidgetPackRegistry` |
| FR-P4-PACK-004 | Optional tag attribute `types` merges listed service ids into `WidgetTypeRegistry` when types are not separately tagged |
| FR-P4-PACK-005 | `WidgetPackRegistry::summarize()` returns name/version/capabilities/type ids for diagnostics |

### Classic extension point (`FR-P4-WGT-*`)

| ID | Requirement |
| --- | --- |
| FR-P4-WGT-001 | External classic widgets implement `WidgetTypeInterface` (or extend `AbstractWidgetType`) and tag `nowo_page_builder_kit.widget_type` |
| FR-P4-WGT-002 | Public Twig templates follow `@NowoPageBuilderKitBundle/widgets/{type}.html.twig` (host overrides via Twig paths) |
| FR-P4-WGT-003 | Widget props with HTML are sanitized through `PageBuilderProtection` on save/render |

### Documentation (`FR-P4-DOC-*`)

| ID | Requirement |
| --- | --- |
| FR-P4-DOC-001 | [WIDGET_AUTHORS.md](../../docs/WIDGET_AUTHORS.md) documents pack + type registration, capabilities, templates, sanitization, and GrapesJS boundary |

## Success criteria (`SC-P4-*`)

| ID | Criterion |
| --- | --- |
| SC-P4-01 | A host can register a custom `WidgetTypeInterface` and render it on a classic v1 page |
| SC-P4-02 | A tagged `WidgetPackInterface` appears in `WidgetPackRegistry::summarize()` |
| SC-P4-03 | Unit tests cover `WidgetPackPass` and `WidgetPackRegistry` |
| SC-P4-04 | Author guide matches DI tags and template override paths in code |

## Non-goals (Phase 4 baseline)

Deferred (optional follow-ups, not acceptance criteria of this baseline):

- Grapes **block packs** analogous to classic widget packs
- Template / widget marketplace or cross-project sharing
- Bundled third-party widget Composer packages inside this repo

See [Follow-ups](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#follow-ups-optional).

## Validation

```bash
make test
make phpstan
```

## See also

- Phase 1: [`../001-baseline/spec.md`](../001-baseline/spec.md)
- Phase 2: [`../002-phase2-grapesjs/spec.md`](../002-phase2-grapesjs/spec.md)
- Phase 3: [`../003-phase3-revisions-templates/spec.md`](../003-phase3-revisions-templates/spec.md)
