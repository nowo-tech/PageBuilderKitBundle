# Phase 5 — Content fields + capabilities (MVP shipped)

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **MVP shipped (unreleased → next minor)**  
**Roadmap:** [SPEC-DRIVEN-DEVELOPMENT.md](../../docs/SPEC-DRIVEN-DEVELOPMENT.md#phase-5--content-fields--capabilities-mvp-shipped)  
**Security:** [SECURITY.md](../../docs/SECURITY.md#admin-access-guard-req-ui-002) · [CONFIGURATION.md](../../docs/CONFIGURATION.md#security)  
**Inventory:** [`../001-baseline/code-inventory.md`](../001-baseline/code-inventory.md)

## Overview

Phase 5 adds a **typed content-fields layer** on documents so hosts can translate **values** without duplicating Grapes HTML per locale, plus **fine-grained admin access** (roles or custom guard) so layout editors and content editors can be split.

Primary engine for this MVP: **GrapesJS schema v2**. Classic schema v1 remains supported as **legacy** (shared tree + `widgetPropsByLocale`).

```text
Document structure
  layout: html/css/grapes (+ optional localeContent legacy)
  fields[]: { key, type, label, labels[locale]?, required, options?, default? }
  fieldValues[locale][key]: typed payload

Render Twig context
  fields.{key}  ← locale + default_locale fallback

ACL
  roles: access_roles | layout_roles | content_roles | publish_roles | templates_roles
  or custom: security.access_checker → PageBuilderKitAccessCheckerInterface
  controllers: PageBuilderKitAccessGuard
```

## Functional requirements (`FR-P5-*`)

### Content field schema (`FR-P5-SCH-*`) — 5A

| ID | Requirement |
| --- | --- |
| FR-P5-SCH-001 | Documents may store `structure.fields` (list of field defs) normalized by `ContentFieldsNormalizer` |
| FR-P5-SCH-002 | Field keys match `^[a-z][a-z0-9_]*$`; duplicates are dropped |
| FR-P5-SCH-003 | MVP types: `string`, `text`, `richtext`, `html`, `raw`, `number`, `url`, `image`, `icon`, `bool`, `select` (`ContentFieldType`) |
| FR-P5-SCH-004 | Layout editors may add/remove field defs via `POST …/content/schema` (`ContentFieldsService::saveSchema`) |
| FR-P5-SCH-005 | Host Twig `nowo_page_builder_field()` may create a field def on first inline save |
| FR-P5-SCH-006 | Field labels are multilingual via `labels[locale]` (fallback: locale → default_locale → singular `label` → key) |

### Locale values + render (`FR-P5-VAL-*`) — 5B

| ID | Requirement |
| --- | --- |
| FR-P5-VAL-001 | Values live in `structure.fieldValues[locale][key]` |
| FR-P5-VAL-002 | Render resolves values with locale → `default_locale` → field `default` / empty |
| FR-P5-VAL-003 | `PageRenderProvider` injects resolved bag as Twig `fields` (Grapes HTML may use `{{ fields.hero_title }}`) |
| FR-P5-VAL-004 | `richtext` values are sanitized through `PageBuilderProtection` on save |
| FR-P5-VAL-005 | `localeContent` remains supported (legacy); fields are additive |

### Capabilities / ACL (`FR-P5-ACL-*`) — 5C

| ID | Requirement |
| --- | --- |
| FR-P5-ACL-001 | `PageBuilderKitAccessCheckerInterface` exposes `canAccess`, `canLayout`, `canContent`, `canPublish`, `canTemplates`, and convenience `can()` |
| FR-P5-ACL-002 | Default checker is role-based (`access_roles` shortcut + `*_roles`); empty capability role lists use BlogKit emptyAllows semantics |
| FR-P5-ACL-003 | Hosts may set `security.access_checker` to a custom guard service |
| FR-P5-ACL-004 | `PageBuilderKitAccessGuard` provides `assertLayout()` / `assertContent()` / … for controllers |
| FR-P5-ACL-005 | `PageBuilderKitAdminAccessSubscriber` maps admin routes to capabilities after `canAccess()` |
| FR-P5-ACL-006 | Twig: `nowo_page_builder_can('layout'|…)` and `nowo_page_builder_can_edit()` |

### Content UI (`FR-P5-UI-*`) — 5D

| ID | Requirement |
| --- | --- |
| FR-P5-UI-001 | Admin route `/pages/{pageKey}/content` edits values per locale (CSRF `page_builder_content`) |
| FR-P5-UI-002 | Schema editor on the same page is layout-only |
| FR-P5-UI-003 | Page list shows Content / Canvas / Sections links gated by capability; classic Sections labeled legacy |
| FR-P5-UI-004 | Content-only users reach content forms without opening the Grapes canvas |
| FR-P5-UI-005 | `nowo_page_builder_field()` shows value for anonymous users and pencil+modal when `canContent()` |

## Success criteria (`SC-P5-*`)

| ID | Criterion |
| --- | --- |
| SC-P5-01 | Layout editor defines `hero_title` (string); content editor sets ES/EN values; public Grapes HTML with `{{ fields.hero_title }}` renders the active locale |
| SC-P5-02 | User with only `content_roles` cannot open canvas (`403` from admin subscriber) |
| SC-P5-03 | Custom `access_checker` can replace role lists entirely |
| SC-P5-04 | Unit tests cover normalizer, capabilities, access guard, CSRF save, XSS allowlist matrix |
| SC-P5-05 | Spec Kit folder + code inventory list Phase 5 sources |

## Non-goals (Phase 5 MVP)

Deferred to **Phase 6** (hardening):

- Strict required-field validation on publish
- Revisions / import-export that treat field schema+values as first-class (beyond full structure snapshots)
- Template apply that separates layout vs field defs / SEO
- Repeater / reference field types
- Slot-replace without Twig (pure string tokens)

## Validation

```bash
make test
make phpstan
make validate-translations
```

## See also

- Phase 1–4: [`../001-baseline/spec.md`](../001-baseline/spec.md) … [`../004-phase4-external-widgets/spec.md`](../004-phase4-external-widgets/spec.md)
- [USAGE.md](../../docs/USAGE.md#content-fields-phase-5) — integrator notes
- [UPGRADING.md](../../docs/UPGRADING.md#unreleased) — host upgrade steps
