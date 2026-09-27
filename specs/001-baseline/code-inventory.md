# Code inventory — PageBuilderKitBundle (canonical)

**Specs:** [Phase 1](spec.md) · [Phase 2](../002-phase2-grapesjs/spec.md) · [Phase 3](../003-phase3-revisions-templates/spec.md) · [Phase 4](../004-phase4-external-widgets/spec.md)  
**Package:** `nowo-tech/page-builder-kit-bundle`  
**Last audited:** 2026-09-27

Maps production files under `src/` to functional requirements across shipped phases (`FR-*` Phase 1, `FR-P2-*`, `FR-P3-*`, `FR-P4-*`, plus `REQ-DEBUG-001` where relevant).

**Total production sources:** 119 files (`find src -type f \( -name '*.php' -o -name '*.twig' -o -name '*.yaml' -o -name '*.js' -o -name '*.ts' -o -name '*.css' \) | wc -l`)

## Bundle entry

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `NowoPageBuilderKitBundle.php` | Bundle entrypoint, Doctrine mappings, compiler passes | FR-DI-001, FR-ORM-001, FR-WRK-001 |

## Controllers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Controller/Admin/PageListController.php` | Page list and create form | FR-ADM-001 |
| `Controller/Admin/PageCanvasController.php` | GrapesJS / canvas shell | FR-ADM-002, FR-P2-ADM-001 |
| `Controller/Admin/PageDocumentApiController.php` | Document GET/POST, publish/unpublish, duplicate, import/export, CSRF | FR-ADM-003, FR-ADM-004, FR-ADM-005, FR-P3-IO-001, FR-P3-IO-002 |
| `Controller/Admin/PageSectionsController.php` | Classic Sections editor | FR-P2-ADM-004 |
| `Controller/Admin/PageSeoController.php` | Page SEO form | FR-ADM-001 |
| `Controller/Admin/PageAssetUploadController.php` | Grapes Asset Manager uploads | FR-P2-ADM-005 |
| `Controller/Admin/PageRevisionsController.php` | Revision list, restore, diff | FR-P3-REV-002, FR-P3-REV-003 |
| `Controller/Admin/PageTemplatesController.php` | Templates library | FR-P3-TPL-002 |
| `Controller/Public/PageRenderController.php` | Public `/p/{pageKey}` (+ draft preview) | FR-REN-003, FR-P3-PREV-001, FR-P3-PREV-002 |

## Debug / Web Profiler

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `DataCollector/PageBuilderKitDataCollector.php` | WDT panel | REQ-DEBUG-001 |
| `Debug/PageBuilderKitTraceInterface.php` | Request-scoped trace contract | REQ-DEBUG-001 |
| `Debug/PageBuilderKitTrace.php` | Trace implementation + ResetInterface | REQ-DEBUG-001, FR-WRK-001 |
| `Debug/NullPageBuilderKitTrace.php` | No-op when collector disabled | REQ-DEBUG-001 |

## Dependency injection and compiler

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Bundle configuration tree | FR-CFG-001, FR-CFG-002 |
| `DependencyInjection/NowoPageBuilderKitExtension.php` | Loads services, security, grapesjs, revisions, debug | FR-CFG-001, FR-DI-001, FR-SEC-001 |
| `DependencyInjection/TablePrefixListener.php` | Optional table prefix | FR-ORM-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace / host overrides | FR-REN-002, REQ-TWIG-001 |
| `DependencyInjection/Compiler/WidgetTypePass.php` | Tagged widget types → registry | FR-WGT-003, FR-DI-001 |
| `DependencyInjection/Compiler/WidgetPackPass.php` | Tagged widget packs → registry | FR-P4-PACK-003, FR-P4-PACK-004 |

## Entities

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Entity/BuilderPage.php` | Page aggregate | FR-ORM-001, FR-DOC-001 |
| `Entity/BuilderPageTranslation.php` | Localized title/slug/SEO | FR-I18N-001, FR-ORM-001 |
| `Entity/BuilderDocument.php` | JSON structure storage | FR-DOC-001, FR-P2-DOC-001, FR-ORM-001 |
| `Entity/BuilderDocumentLocale.php` | Per-locale widget props | FR-DOC-003, FR-I18N-001 |
| `Entity/BuilderPageRevision.php` | Revision snapshots | FR-ORM-001, FR-P3-REV-001 |
| `Entity/BuilderPageTemplate.php` | Named page templates | FR-P3-TPL-001 |

## Enums and locale

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Enum/PageStatus.php` | Draft / published | FR-ADM-004, FR-REN-003 |
| `Enum/HtmlSanitizeStrategy.php` | Sanitize strategy | FR-SEC-003 |
| `Locale/BuilderLocales.php` | Config-backed locales | FR-I18N-001, FR-CFG-001, FR-WRK-001 |
| `Locale/BuilderLocalesLegacyBinding.php` | Legacy static bind helper | FR-WRK-001 |

## Event subscribers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/PageBuilderKitAdminAccessSubscriber.php` | Admin route access | FR-SEC-001, REQ-UI-002 |
| `EventSubscriber/WorkerStateResetSubscriber.php` | Clears locale bind on terminate | FR-WRK-001 |

## Forms

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Form/BuilderPageCreateType.php` | Create page form | FR-ADM-001 |
| `Form/BuilderPageSeoType.php` | SEO / Open Graph form | FR-ADM-001 |

## Media

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Media/PageBuilderAssetStorageInterface.php` | Upload storage contract | FR-P2-ADM-005 |
| `Media/AssetUploadHandler.php` | Upload orchestration | FR-P2-ADM-005 |
| `Media/AssetUploadResult.php` | Upload result VO | FR-P2-ADM-005 |
| `Media/LocalFilesystemAssetStorage.php` | Local disk storage | FR-P2-ADM-005 |
| `Media/AwsS3AssetStorage.php` | S3 storage | FR-P2-ADM-005 |

## Repository

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Repository/BuilderPageRepositoryInterface.php` | Page repository contract | FR-DOC-001, FR-REN-001 |
| `Repository/BuilderPageRepository.php` | Page persistence | FR-DOC-001, FR-REN-001 |
| `Repository/BuilderPageRevisionRepositoryInterface.php` | Revision repository contract | FR-P3-REV-001 |
| `Repository/BuilderPageRevisionRepository.php` | Revision persistence | FR-P3-REV-001 |
| `Repository/BuilderPageTemplateRepositoryInterface.php` | Template repository contract | FR-P3-TPL-001 |
| `Repository/BuilderPageTemplateRepository.php` | Template persistence | FR-P3-TPL-001 |

## Services

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Service/DocumentNormalizer.php` | Schema v1/v2 normalization | FR-DOC-004, FR-P2-DOC-002 |
| `Service/DocumentService.php` | Create/save/publish/duplicate | FR-DOC-001, FR-ADM-004, FR-SEC-003, FR-P3-IO-001 |
| `Service/DocumentDiff.php` | Structure/props comparison | FR-P3-REV-003 |
| `Service/DocumentImportExportService.php` | JSON export/import `formatVersion: 1` | FR-P3-IO-002 |
| `Service/PageRenderProvider.php` | Rendered page tree | FR-REN-001, FR-P2-REN-001, FR-I18N-001 |
| `Service/PageRenderProviderInterface.php` | Render provider contract | FR-REN-001 |
| `Service/PageRevisionStore.php` | Snapshot/prune revisions | FR-P3-REV-001, FR-P3-REV-004 |
| `Service/PageRevisionService.php` | List/create/restore/diff API | FR-P3-REV-002, FR-P3-REV-003 |
| `Service/PageTemplateService.php` | Template save/apply/delete | FR-P3-TPL-001, FR-P3-TPL-002 |
| `Service/PageSeoBuilder.php` | SEO meta for public render | FR-REN-001 |
| `Service/WidgetPropsMerger.php` | Locale props merge/fallback | FR-I18N-001 |
| `Service/ElementAppearanceNormalizer.php` | Classic Style/Advanced props | FR-P2-WGT-002 |
| `Service/GrapesJsFrontendConfig.php` | Canvas CDN/plugin config | FR-P2-ADM-002 |
| `Service/GrapesDocumentSanitizer.php` | Sanitize Grapes HTML/CSS | FR-P2-REN-003 |
| `Service/GrapesTwigRenderer.php` | Sandboxed Twig in Grapes HTML | FR-P2-REN-002 |
| `Service/GrapesTwigContextProviderInterface.php` | Host Twig context hook | FR-P2-REN-002 |

## Security

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Security/PageBuilderKitAccessCheckerInterface.php` | Access checker contract | FR-SEC-001 |
| `Security/ConfigurablePageBuilderKitAccessChecker.php` | Role-based checker | FR-SEC-001 |
| `Security/AllowAllPageBuilderKitAccessChecker.php` | Demo unauthenticated checker | FR-SEC-002 |
| `Security/PageBuilderProtection.php` | HTML sanitizer wiring | FR-SEC-003 |
| `Security/PageBuilderProtectionConfig.php` | Sanitize strategy VO | FR-SEC-003 |
| `Security/Html/PageBuilderHtmlSanitizerInterface.php` | Sanitizer contract | FR-SEC-003 |
| `Security/Html/NullPageBuilderHtmlSanitizer.php` | No-op sanitizer | FR-SEC-003 |
| `Security/Html/StripPageBuilderHtmlSanitizer.php` | Strip-tags sanitizer | FR-SEC-003 |
| `Security/Html/AllowlistPageBuilderHtmlSanitizer.php` | Allowlist sanitizer | FR-SEC-003 |

## Twig

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Twig/PageBuilderKitExtension.php` | Render helpers, layout hints | FR-REN-002 |
| `Twig/ElementAppearanceExtension.php` | Appearance Twig helpers | FR-P2-WGT-002 |

## Widget system

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Widget/WidgetTypeInterface.php` | Widget type contract | FR-WGT-002, FR-WGT-003, FR-P4-WGT-001 |
| `Widget/AbstractWidgetType.php` | Shared template/sanitize helpers | FR-WGT-002, FR-P4-WGT-002, FR-P4-WGT-003 |
| `Widget/WidgetTypeRegistry.php` | Runtime type lookup | FR-WGT-003, FR-P4-PACK-004 |
| `Widget/WidgetPackInterface.php` | External pack contract | FR-P4-PACK-001, FR-P4-PACK-002 |
| `Widget/WidgetPackRegistry.php` | Pack registry / summarize | FR-P4-PACK-003, FR-P4-PACK-005 |
| `Widget/Type/HeadingWidgetType.php` | Heading widget | FR-WGT-001 |
| `Widget/Type/TextWidgetType.php` | Text widget | FR-WGT-001 |
| `Widget/Type/HtmlWidgetType.php` | HTML widget | FR-WGT-001 |
| `Widget/Type/ImageWidgetType.php` | Image widget | FR-WGT-001 |
| `Widget/Type/ButtonWidgetType.php` | Button widget | FR-WGT-001 |
| `Widget/Type/SpacerWidgetType.php` | Spacer widget | FR-WGT-001 |
| `Widget/Type/ContainerWidgetType.php` | Nesting container | FR-P2-WGT-001 |

## Resources — config and routing

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service definitions and tags | FR-DI-001 |
| `Resources/config/routing.yaml` | Attribute route import | FR-ADM-001, FR-ADM-002, FR-REN-003 |

## Resources — admin and public Twig

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/views/admin/layout.html.twig` | Admin shell layout | FR-ADM-002, FR-CFG-002 |
| `Resources/views/admin/pages/index.html.twig` | Page list UI | FR-ADM-001 |
| `Resources/views/admin/pages/canvas.html.twig` | Grapes canvas host | FR-ADM-002, FR-P2-ADM-001 |
| `Resources/views/admin/pages/sections.html.twig` | Classic Sections UI | FR-P2-ADM-004 |
| `Resources/views/admin/pages/seo.html.twig` | SEO admin screen | FR-ADM-001 |
| `Resources/views/admin/pages/revisions.html.twig` | Revision history UI | FR-P3-REV-002 |
| `Resources/views/admin/pages/revision_diff.html.twig` | Diff summary UI | FR-P3-REV-003 |
| `Resources/views/admin/pages/templates.html.twig` | Templates library UI | FR-P3-TPL-002 |
| `Resources/views/Collector/page_builder.html.twig` | Profiler panel Twig | REQ-DEBUG-001 |
| `Resources/views/public/page.html.twig` | Public page wrapper | FR-REN-001, FR-P3-PREV-001 |
| `Resources/views/public/_seo_meta.html.twig` | SEO meta tags | FR-REN-001 |
| `Resources/views/public/_edit_button.html.twig` | Public edit pencil | FR-SEC-001 |
| `Resources/views/public/_icon_pencil.svg.twig` | Pencil icon partial | FR-SEC-001 |
| `Resources/views/widgets/_section.html.twig` | Section partial | FR-REN-004 |
| `Resources/views/widgets/_column.html.twig` | Column partial | FR-REN-004 |
| `Resources/views/widgets/_widget.html.twig` | Widget dispatch partial | FR-REN-004 |
| `Resources/views/widgets/heading.html.twig` | Heading template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/text.html.twig` | Text template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/html.html.twig` | HTML template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/image.html.twig` | Image template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/button.html.twig` | Button template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/spacer.html.twig` | Spacer template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/container.html.twig` | Container template | FR-P2-WGT-001, FR-REN-004 |

## Resources — frontend assets

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/page-builder-canvas.ts` | Canvas TS pointer/source | FR-ADM-002, FR-P2-ADM-001 |
| `Resources/public/js/page-builder-canvas.js` | Runtime Grapes canvas script | FR-ADM-002, FR-P2-ADM-001 |
| `Resources/public/css/page-builder.css` | Canvas and admin styles | FR-ADM-002 |

## Resources — translations

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPageBuilderKitBundle.en.yaml` | English catalogue (reference) | FR-I18N-001, REQ-MAKE-004 |
| `Resources/translations/NowoPageBuilderKitBundle.es.yaml` | Spanish catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.de.yaml` | German catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.fr.yaml` | French catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.it.yaml` | Italian catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.nl.yaml` | Dutch catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.pt.yaml` | Portuguese catalogue | FR-I18N-001 |

## Summary

| Category | File count |
| --- | ---: |
| PHP (excluding Resources subtree) | 84 |
| Twig views | 23 |
| YAML (config + translations) | 9 |
| TS/JS/CSS assets | 3 |
| **Total** | **119** |
