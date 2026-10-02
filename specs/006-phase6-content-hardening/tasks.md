# Phase 6 — Tasks

## Phase A — Spec artifacts

- [x] T001 Create `specs/006-phase6-content-hardening/spec.md`
- [x] T002 Create plan + tasks + requirements checklist
- [x] T003 Update roadmap / CHANGELOG / USAGE / inventory

## Phase B — Schema & normalizer

- [x] T010 Add `repeater`, `group`, `reference` to `ContentFieldType`
- [x] T011 Normalize nested `fields`, `min`, `max`, `reference` in `ContentFieldsNormalizer`
- [x] T012 Normalize / resolve repeater list, group map, reference string
- [x] T013 Implement `validateRequired(structure, locales)`
- [x] T014 Sanitize nested html/richtext in `ContentFieldsService`
- [x] T015 Unit tests for composites + required validation

## Phase C — Publish + templates

- [x] T020 Inject normalizer into `DocumentService::publish` and block invalid publish
- [x] T021 Surface publish validation errors in document API
- [x] T022 `createPageFromTemplate` options `include_field_schema` / `include_field_values`
- [x] T023 Admin template create form checkboxes
- [x] T024 Tests for publish rejection + template options

## Phase D — Admin content UI

- [x] T030 Schema add: parse subfields `key:type,…` for repeater/group
- [x] T031 Values form: edit group maps + repeater rows (add/remove)
- [x] T032 Translations for new UI strings

## Done when

- SC-P6-01…04 covered by code + unit tests
- Focused unit tests green
