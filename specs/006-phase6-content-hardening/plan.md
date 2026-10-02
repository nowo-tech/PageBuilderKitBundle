# Phase 6 — Implementation plan

**Spec:** [`spec.md`](./spec.md)

## Technical context

- Bundle: Symfony, existing `ContentFieldsNormalizer` / `ContentFieldsService` / `DocumentService` / `PageTemplateService`
- Storage remains JSON inside `BuilderDocument.structure` (no schema migration)
- Admin Twig: `admin/pages/content.html.twig`, templates controller

## Design

1. Extend `ContentFieldType` with `Repeater`, `Group`, `Reference`.
2. Expand schema shape with `fields` (nested), `min`, `max`, `reference` (`page` only for MVP).
3. `ContentFieldsNormalizer::validateRequired($structure, $locales): list<error>`.
4. `DocumentService::publish` injects normalizer + `BuilderLocales`, throws on validation errors.
5. `PageTemplateService::createPageFromTemplate(..., array $options = [])` strips/clears fields as specified.
6. Admin content UI: repeater rows + group sub-inputs; schema parser for `a:string,b:text`.
7. Sanitize nested `html`/`richtext` inside repeater/group rows in `ContentFieldsService`.

## File touch list

- `src/Enum/ContentFieldType.php`
- `src/Service/ContentFieldsNormalizer.php`
- `src/Service/ContentFieldsService.php`
- `src/Service/DocumentService.php` (+ DI)
- `src/Service/PageTemplateService.php`
- `src/Controller/Admin/PageDocumentApiController.php`
- `src/Controller/Admin/PageContentController.php`
- `src/Controller/Admin/PageTemplatesController.php`
- `src/Resources/views/admin/pages/content.html.twig`
- templates create form twig
- tests + docs (`CHANGELOG`, `USAGE`, `SPEC-DRIVEN-DEVELOPMENT`, inventory)

## Constitution / BC

- Additive types and options; existing scalar fields unchanged
- Template option defaults: schema on, values off (new pages empty content)
- Publish becomes stricter only when `required: true` fields exist
