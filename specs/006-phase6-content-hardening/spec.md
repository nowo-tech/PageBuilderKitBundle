# Phase 6 — Content hardening (repeaters, publish validation, field-aware templates)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **MVP shipped (unreleased → next minor)**  
**Depends on:** [Phase 5](../005-content-fields-i18n/spec.md)  
**Roadmap:** [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#phase-6--content-hardening)

## Overview

Hardens the Phase 5 content-fields layer so the bundle behaves more like a structured CMS: **composite fields** (repeater / group / reference), **required validation on publish**, and **template apply** that can keep layout while choosing whether to copy field schema and/or values.

```text
fields[]: { key, type, label, labels?, required, options?, default?, fields[]?, min?, max?, reference? }
  type += repeater | group | reference

fieldValues[locale][key]:
  scalar | { group map } | [ { repeater row }, … ] | pageKey string

publish → reject if required empty per configured locale
createPageFromTemplate(options: include_field_schema, include_field_values)
```

## Functional requirements (`FR-P6-*`)

### Composite field types (`FR-P6-SCH-*`)

| ID | Requirement |
| --- | --- |
| FR-P6-SCH-001 | Types `repeater`, `group`, `reference` are first-class in `ContentFieldType` |
| FR-P6-SCH-002 | `repeater` / `group` may declare nested `fields[]` (one nesting level; nested types are scalar/reference only) |
| FR-P6-SCH-003 | `repeater` may declare `min` / `max` row counts (normalized integers ≥ 0) |
| FR-P6-SCH-004 | `reference` stores a page key string (`^[a-z0-9_-]+$` or empty) |
| FR-P6-SCH-005 | Values normalize: repeater → list of row maps; group → map; reference → string |

### Publish validation (`FR-P6-PUB-*`)

| ID | Requirement |
| --- | --- |
| FR-P6-PUB-001 | Publishing validates required content fields for every configured builder locale |
| FR-P6-PUB-002 | Empty string / empty repeater / empty group fails required; `bool` always counts as present |
| FR-P6-PUB-003 | Repeater with `min` fails when row count &lt; min |
| FR-P6-PUB-004 | Failed publish does not change page status; API returns a clear error |

### Field-aware templates (`FR-P6-TPL-*`)

| ID | Requirement |
| --- | --- |
| FR-P6-TPL-001 | `createPageFromTemplate` accepts options `include_field_schema` (default true) and `include_field_values` (default false) |
| FR-P6-TPL-002 | When schema is excluded, new page structure has empty `fields` / `fieldValues` |
| FR-P6-TPL-003 | When schema included and values excluded, `fields` copy and `fieldValues` start empty |
| FR-P6-TPL-004 | Admin “create from template” exposes these options (checkboxes) |

### Admin / render (`FR-P6-UI-*`)

| ID | Requirement |
| --- | --- |
| FR-P6-UI-001 | Content admin can edit repeater rows and group maps per locale |
| FR-P6-UI-002 | Schema add supports repeater/group subfields via compact `key:type,…` syntax |
| FR-P6-UI-003 | Twig `fields.faqs` resolves to a list/map usable in Grapes HTML loops |

## Success criteria (`SC-P6-*`)

| ID | Criterion |
| --- | --- |
| SC-P6-01 | Editor defines `faqs` repeater with `question`/`answer`; content fills ES/EN rows; public Twig iterates `fields.faqs` |
| SC-P6-02 | Required `hero_title` empty for `en` → publish rejected; after fill → publish succeeds |
| SC-P6-03 | Template apply with schema-only yields layout + empty field values |
| SC-P6-04 | Unit tests cover normalizer composites, publish validation, template options |

## Non-goals (deferred)

- Nested repeaters / unlimited nesting
- Media library UI (reference stays page-key string)
- Grapes dynamic-tag picker UI
- Pure string slot tokens without Twig
- SEO payload inside templates

## Assumptions

- One nesting level is enough for FAQ/pricing/feature lists
- Required checks are per configured locale without cross-locale fallback
- Template defaults favor empty values so editors fill content fresh

## Validation

```bash
make test
make phpstan
make validate-translations
```

## See also

- [USAGE.md](../../docs/USAGE.md#content-fields-phase-5)
- Phase 5: [`../005-content-fields-i18n/spec.md`](../005-content-fields-i18n/spec.md)
