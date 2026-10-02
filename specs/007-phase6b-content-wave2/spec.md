# Phase 6b — Content fields wave 2

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **MVP shipped (unreleased → next minor)**  
**Depends on:** [Phase 6](../006-phase6-content-hardening/spec.md)

## Overview

Second hardening wave: deeper composite fields, **Twig-free field slots**, **Grapes block bindings** for page fields, and better **image / page-reference** editing in the content admin.

## Functional requirements (`FR-P6B-*`)

| ID | Requirement |
| --- | --- |
| FR-P6B-001 | Nested `repeater`/`group` allowed up to **2** nesting levels |
| FR-P6B-002 | HTML may use slot tokens `[[fields.path]]` (optional `@`); replaced without Twig |
| FR-P6B-003 | Slots resolve dotted paths (`hero.title`, `faqs.0.question`) with HTML escaping except html/raw leaves |
| FR-P6B-004 | Grapes canvas exposes a **Content fields** block category from the page schema |
| FR-P6B-005 | Content admin: `reference` fields offer a page-key select; `image` fields offer upload when assets upload is enabled |

## Success criteria

| ID | Criterion |
| --- | --- |
| SC-P6B-01 | Nested FAQ groups inside a repeater normalize and render |
| SC-P6B-02 | `[[fields.hero_title]]` renders the locale value with Twig disabled |
| SC-P6B-03 | Canvas config includes `contentFields` for the open page |
| SC-P6B-04 | Unit tests cover slot replacer + nesting depth |

## Non-goals

- Full media library browser UI
- Visual “dynamic tag” property panel beyond Block Manager inserts
- Unlimited nesting
