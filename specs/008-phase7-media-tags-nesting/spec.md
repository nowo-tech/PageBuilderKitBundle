# Phase 7 — Media library, dynamic tags, unlimited nesting

**Package:** `nowo-tech/page-builder-kit-bundle`  
**Status:** **Done**  
**Depends on:** [Phase 6b](../007-phase6b-content-wave2/spec.md)

## Overview

Three CMS-builder upgrades:

1. **Unlimited nesting** for `repeater` / `group` (safety cap).
2. **Media library** — list uploaded assets + pick from Content admin / Grapes Asset Manager.
3. **Dynamic tags in traits** — bind component text/src to `fields.*` from the Traits panel.

## Functional requirements (`FR-P7-*`)

| ID | Requirement |
| --- | --- |
| FR-P7-NEST-001 | Composites may nest recursively up to a safety cap (default **32**) |
| FR-P7-NEST-002 | Beyond the cap, nested composites coerce to `string` (DoS guard) |
| FR-P7-MED-001 | Local asset storage can list stored images (newest first, limit) |
| FR-P7-MED-002 | `GET …/assets` returns Grapes-compatible asset list when upload enabled |
| FR-P7-MED-003 | Content admin image fields open a media picker (library + upload) |
| FR-P7-MED-004 | Grapes Asset Manager is seeded from the library list when available |
| FR-P7-TAG-001 | Text / link / image components gain a **Dynamic tag** trait listing page content fields |
| FR-P7-TAG-002 | Selecting a tag writes `[[fields.key]]` into content (text) or `src` (image) |

## Non-goals

- Cross-page shared media CDN UI
- Video/document library
- Trait binding to arbitrary Twig expressions

## Success criteria

| ID | Criterion |
| --- | --- |
| SC-P7-01 | Nested repeater→group→repeater (3 levels) normalizes |
| SC-P7-02 | Library list returns previously uploaded local assets |
| SC-P7-03 | Canvas config includes `assetsLibraryUrl` + `contentFields` for traits |
| SC-P7-04 | Unit tests cover nesting cap + library list |

## Implementation notes

- `ContentFieldsNormalizer::MAX_NESTING_DEPTH = 32`
- `PageBuilderAssetLibraryInterface` (optional); `LocalFilesystemAssetStorage::list()`
- `AssetUploadHandler::listLibrary()` / `supportsLibrary()`
- Route `admin_page_builder_assets_list` → `GET {prefix}/assets`
- Content UI: `#pbk-media-library-modal` + `data-pbk-media-pick`
- Canvas JS: seed `AssetManager` from `assetsLibraryUrl`; trait `pbk-dynamic-tag`
