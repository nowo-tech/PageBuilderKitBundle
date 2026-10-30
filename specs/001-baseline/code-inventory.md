# Code inventory — PageBuilderKitBundle (canonical)

**Specs:** [Phase 1](spec.md) · [Phase 2](../002-phase2-grapesjs/spec.md) · [Phase 3](../003-phase3-revisions-templates/spec.md) · [Phase 4](../004-phase4-external-widgets/spec.md) · [Phase 5](../005-content-fields-i18n/spec.md) · [Phase 6](../006-phase6-content-hardening/spec.md) · [Phase 6b](../007-phase6b-content-wave2/spec.md) · [Phase 7](../008-phase7-media-tags-nesting/spec.md) · [Phase 9](../009-product-polish-compliance/spec.md)  
**Package:** `nowo-tech/page-builder-kit-bundle`  
**Last audited:** 2026-10-20 (post **v1.8.1**)

Maps production files under `src/` to functional requirements across shipped phases (`FR-*`, `FR-P2-*` … `FR-P9-*`, plus relevant `REQ-*`).

**Total production sources:** **165** files (every file tracked under `src/`; audit: `git ls-files src | wc -l`)

## Bundle entry

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `NowoPageBuilderKitBundle.php` | Bundle entrypoint, Doctrine mappings, compiler passes | FR-DI-001, FR-ORM-001, FR-WRK-001 |

## Controllers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Controller/Admin/PageListController.php` | Page list and create form | FR-ADM-001, FR-P5-ACL-004, FR-P5-UI-003, FR-P9-UX-001, FR-P9-STD-003 |
| `Controller/Admin/PageCanvasController.php` | GrapesJS / canvas shell | FR-ADM-002, FR-P2-ADM-001 |
| `Controller/Admin/PageDocumentApiController.php` | Document GET/POST, publish/unpublish, duplicate, import/export, CSRF | FR-ADM-003, FR-ADM-004, FR-ADM-005, FR-P3-IO-001, FR-P3-IO-002, REQ-SEC-005 |
| `Controller/Admin/PageSectionsController.php` | Classic Sections editor (legacy) | FR-P2-ADM-004, FR-P9-UX-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Controller/Admin/PageSeoController.php` | Page SEO form | FR-ADM-001, FR-P5-ACL-005, FR-P9-STD-003 |
| `Controller/Admin/PageContentController.php` | Content fields values + schema UI | FR-P5-UI-001, FR-P5-UI-002, FR-P5-SCH-004, FR-P5-VAL-001, FR-P9-STD-003, FR-P9-STD-004 |
| `Controller/Admin/PageContentFieldApiController.php` | Inline content-field save API | FR-P5-SCH-005, FR-P5-VAL-004, REQ-SEC-005 |
| `Controller/Admin/PageAssetUploadController.php` | Grapes Asset Manager uploads + library list | FR-P2-ADM-005, FR-P7-MED-002 |
| `Controller/Admin/PageRevisionsController.php` | Revision list, restore, diff | FR-P3-REV-002, FR-P3-REV-003, FR-P9-STD-003, FR-P9-STD-004 |
| `Controller/Admin/PageTemplatesController.php` | Templates library | FR-P3-TPL-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Controller/Admin/TranslatesFlashes.php` | Admin flashes translated when added (UiKit flashes partial prints them as is) | FR-I18N-001, REQ-UI-003 |
| `Controller/Public/PageRenderController.php` | Public `/p/{pageKey}` (+ draft preview) | FR-REN-003, FR-P3-PREV-001, FR-P3-PREV-002 |

## Debug / Web Profiler

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `DataCollector/PageBuilderKitDataCollector.php` | WDT panel (capabilities + field keys) | REQ-DEBUG-001 |
| `Debug/PageBuilderKitTraceInterface.php` | Request-scoped trace contract | REQ-DEBUG-001 |
| `Debug/PageBuilderKitTrace.php` | Trace implementation + ResetInterface | REQ-DEBUG-001, FR-WRK-001 |
| `Debug/NullPageBuilderKitTrace.php` | No-op when collector disabled | REQ-DEBUG-001 |

## Dependency injection and compiler

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Bundle configuration tree | FR-CFG-001, FR-CFG-002 |
| `DependencyInjection/NowoPageBuilderKitExtension.php` | Loads services, security, grapesjs, revisions, debug, asset package | FR-CFG-001, FR-DI-001, FR-SEC-001, REQ-ASSETS-004 |
| `DependencyInjection/TablePrefixListener.php` | Optional table prefix | FR-ORM-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace / host overrides | FR-REN-002, REQ-TWIG-001, REQ-TWIG-002 |
| `DependencyInjection/Compiler/WidgetTypePass.php` | Tagged widget types → registry | FR-WGT-003, FR-DI-001 |
| `DependencyInjection/Compiler/WidgetPackPass.php` | Tagged widget packs → registry | FR-P4-PACK-003, FR-P4-PACK-004 |
| `DependencyInjection/Compiler/GrapesBlockPackPass.php` | Tagged Grapes block packs → registry | FR-P4-PACK-003, FR-P9-DX-002 |
| `DependencyInjection/Compiler/CsrfProtectionPass.php` | Refuses to compile without form CSRF protection (fail closed) | REQ-SEC-005 |

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
| `Enum/PageBuilderCapability.php` | layout / content / publish / templates | FR-P5-ACL-001 |
| `Enum/ContentFieldType.php` | Typed CMS field types | FR-P5-SCH-003 |
| `Locale/BuilderLocales.php` | Config-backed locales | FR-I18N-001, FR-CFG-001, FR-WRK-001 |
| `Locale/BuilderLocalesLegacyBinding.php` | Legacy static bind helper | FR-WRK-001 |

## Event subscribers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/PageBuilderKitAdminAccessSubscriber.php` | Admin route access + capabilities | FR-SEC-001, FR-P5-ACL-005, REQ-UI-002 |
| `EventSubscriber/WorkerStateResetSubscriber.php` | Clears locale bind on terminate | FR-WRK-001 |

## Forms

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Form/BuilderPageCreateType.php` | Create page form | FR-ADM-001, FR-P9-STD-003 |
| `Form/BuilderPageSeoType.php` | SEO / Open Graph form | FR-ADM-001, FR-P9-STD-003 |
| `Form/CsrfPostType.php` | CSRF-only POST (publish/unpublish/delete/restore) | FR-P9-STD-003, FR-P9-STD-004, REQ-SEC-005 |
| `Form/PageDuplicateType.php` | Duplicate page POST | FR-ADM-005, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/RevisionCreateType.php` | Create revision snapshot | FR-P3-REV-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/TemplateSaveType.php` | Save template from page | FR-P3-TPL-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/TemplateApplyType.php` | Apply template to page | FR-P3-TPL-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/TemplateImportType.php` | Import template JSON | FR-P3-TPL-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/SectionsEditType.php` | Classic sections widget props editor | FR-P2-ADM-004, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/ContentSchemaAddType.php` | Add content-field schema row | FR-P5-UI-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/ContentSchemaRemoveType.php` | Remove content-field schema row | FR-P5-UI-002, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/ContentValuesType.php` | Content field values editor (per locale) | FR-P5-UI-001, FR-P9-STD-003, FR-P9-STD-004 |
| `Form/InlineFieldModalType.php` | Public inline-edit modal shell | FR-P5-SCH-005, FR-P9-STD-003, FR-P9-STD-004 |

## Grapes block packs

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Grapes/GrapesBlockPackInterface.php` | Host/Composer Grapes pack contract | FR-P9-DX-002 |
| `Grapes/GrapesBlockPackRegistry.php` | Pack registry for canvas `blockPacks` | FR-P9-DX-002 |

## Media

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Media/PageBuilderAssetStorageInterface.php` | Upload storage contract | FR-P2-ADM-005 |
| `Media/PageBuilderAssetLibraryInterface.php` | Optional library list contract | FR-P7-MED-001 |
| `Media/AssetUploadHandler.php` | Upload orchestration + library | FR-P2-ADM-005, FR-P7-MED-001, FR-P7-MED-002 |
| `Media/AssetUploadResult.php` | Upload result VO | FR-P2-ADM-005 |
| `Media/LocalFilesystemAssetStorage.php` | Local disk storage + list | FR-P2-ADM-005, FR-P7-MED-001 |
| `Media/AwsS3AssetStorage.php` | S3 storage | FR-P2-ADM-005 |
| `Media/UploadedAssetExtension.php` | Stored extension derived from detected MIME (never the client name) | FR-P2-ADM-005, FR-SEC-005 |

## Repository

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Repository/BuilderPageRepositoryInterface.php` | Page repository contract | FR-DOC-001, FR-REN-001 |
| `Repository/BuilderPageRepository.php` | Page persistence | FR-DOC-001, FR-REN-001 |
| `Repository/BuilderPageRevisionRepositoryInterface.php` | Revision repository contract | FR-P3-REV-001 |
| `Repository/BuilderPageRevisionRepository.php` | Revision persistence | FR-P3-REV-001 |
| `Repository/BuilderPageTemplateRepositoryInterface.php` | Template repository contract | FR-P3-TPL-001 |
| `Repository/BuilderPageTemplateRepository.php` | Template persistence | FR-P3-TPL-001 |
| `Repository/BuilderPageStatusQueryInterface.php` | Publication status lookup without hydrating the document | FR-REN-003, FR-P3-PREV-002 |
| `Repository/BuilderPageStatusQuery.php` | Scalar status query (404 vs render decision) | FR-REN-003, FR-P3-PREV-002 |

## Services

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Service/DocumentNormalizer.php` | Schema v1/v2 + fields/fieldValues normalization | FR-DOC-004, FR-P2-DOC-002, FR-P5-SCH-001 |
| `Service/DocumentStructureValidator.php` | Engine-aware structure validation | FR-P9-ENG-001 |
| `Service/DocumentService.php` | Create/save/publish/duplicate | FR-DOC-001, FR-ADM-004, FR-SEC-003, FR-P3-IO-001 |
| `Service/DocumentDiff.php` | Structure/props comparison | FR-P3-REV-003 |
| `Service/DocumentImportExportService.php` | JSON export/import `formatVersion: 1` | FR-P3-IO-002 |
| `Service/ClassicPageTreeBuilder.php` | Classic sections tree for render | FR-P9-ENG-002, FR-REN-001 |
| `Service/PageRenderProvider.php` | Rendered page tree (+ `fields` inject) | FR-REN-001, FR-P2-REN-001, FR-I18N-001, FR-P5-VAL-003 |
| `Service/PageRenderProviderInterface.php` | Render provider contract | FR-REN-001 |
| `Service/ContentFieldsNormalizer.php` | Field schema/values normalize + nesting | FR-P5-SCH-001, FR-P5-VAL-001, FR-P7-NEST-001 |
| `Service/ContentFieldsService.php` | Persist field schema/values | FR-P5-SCH-004, FR-P5-VAL-004 |
| `Service/ContentFieldSlotReplacer.php` | Twig-free `[[fields.*]]` slots | FR-P6B-002, FR-P6B-003 |
| `Service/InlineContentFieldRenderer.php` | Host Twig inline editable field | FR-P5-SCH-005 |
| `Service/InlineContentFieldRendererInterface.php` | Inline field renderer contract | FR-P5-SCH-005 |
| `Service/PageRevisionStore.php` | Snapshot/prune revisions | FR-P3-REV-001, FR-P3-REV-004 |
| `Service/PageRevisionService.php` | List/create/restore/diff API | FR-P3-REV-002, FR-P3-REV-003 |
| `Service/PageTemplateService.php` | Template save/apply/delete (+ SEO option) | FR-P3-TPL-001, FR-P3-TPL-002 |
| `Service/PageSeoBuilder.php` | SEO meta for public render | FR-REN-001 |
| `Service/WidgetPropsMerger.php` | Locale props merge/fallback | FR-I18N-001 |
| `Service/ElementAppearanceNormalizer.php` | Classic Style/Advanced props | FR-P2-WGT-002 |
| `Service/GrapesJsFrontendConfig.php` | Canvas CDN/plugin/blockPacks config | FR-P2-ADM-002, FR-P7-TAG-001 |
| `Service/GrapesDocumentSanitizer.php` | Sanitize Grapes HTML/CSS + locale content | FR-P2-REN-003, FR-P9-ENG-003 |
| `Service/GrapesTwigRenderer.php` | Sandboxed Twig in Grapes HTML | FR-P2-REN-002 |
| `Service/GrapesTwigContextProviderInterface.php` | Host Twig context hook | FR-P2-REN-002 |

## HTML

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Html/PublicHtmlNormalizer.php` | Optional public Grapes HTML cleanup (invalid `</source>`, skeleton `src`, host WebP pictures) | FR-P9-HTML-001, FR-P9-HTML-002, FR-P9-HTML-003, FR-P9-HTML-004 |
| `Html/PublicHtmlHardener.php` | Always-on final XSS gate before `|raw` public output | FR-P2-REN-005, FR-SEC-005 |

## Content fields and bind slots

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Content/ContentFieldDefinitionProviderInterface.php` | Host catalogue of content fields per page key | FR-P5-SCH-005 |
| `Content/NullContentFieldDefinitionProvider.php` | Default provider (no known fields) | FR-P5-SCH-005 |
| `Service/PublicBindHydratorInterface.php` | Public Grapes bind-slot hydration contract | FR-P5-SCH-005, FR-P5-ACL-001 |
| `Service/PublicBindHydrator.php` | Keeps resolved slot HTML; adds inline-edit UI for `content` capability | FR-P5-SCH-005, FR-P5-ACL-001 |

## Utilities

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Util/ContentSectionFilter.php` | Sanitizes and applies `?section=` focus for Content / canvas deep-links | FR-P5-UI-001 |

## Security

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Security/PageBuilderKitAccessCheckerInterface.php` | Access checker contract (roles or custom guard) | FR-SEC-001, FR-P5-ACL-001 |
| `Security/ConfigurablePageBuilderKitAccessChecker.php` | Role-based checker (`*_roles`) | FR-SEC-001, FR-P5-ACL-002 |
| `Security/AllowAllPageBuilderKitAccessChecker.php` | Demo unauthenticated checker | FR-SEC-002 |
| `Security/PageBuilderKitAccessGuard.php` | Controller assert/deny helper | FR-P5-ACL-004 |
| `Security/PageBuilderProtection.php` | HTML sanitizer wiring | FR-SEC-003 |
| `Security/PageBuilderProtectionConfig.php` | Sanitize strategy VO | FR-SEC-003 |
| `Security/Html/PageBuilderHtmlSanitizerInterface.php` | Sanitizer contract | FR-SEC-003 |
| `Security/Html/NullPageBuilderHtmlSanitizer.php` | No-op sanitizer | FR-SEC-003 |
| `Security/Html/StripPageBuilderHtmlSanitizer.php` | Strip-tags sanitizer | FR-SEC-003 |
| `Security/Html/AllowlistPageBuilderHtmlSanitizer.php` | DOM allowlist sanitizer | FR-SEC-003, FR-P5-VAL-004 |
| `Security/Html/SafeUrl.php` | URL scheme allowlist for href / src (`pbk_safe_url`) | FR-SEC-003, REQ-SEC-005 |

## Twig

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Twig/PageBuilderKitExtension.php` | Render helpers, `can` / `can_edit`, inline field | FR-REN-002, FR-P5-ACL-006, FR-P5-SCH-005 |
| `Twig/ElementAppearanceExtension.php` | Appearance Twig helpers | FR-P2-WGT-002 |

## UiKit page contract

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Ui/PageBuilderKitPages.php` | Layout (`web_ui.layout_template` / `nowo_ui_kit.layouts.admin`), per-page templates, 1.x layout bridge | REQ-UI-003, FR-CFG-002 |
| `Ui/PageBuilderKitPageFactory.php` | UiKit view models of the eight admin pages (headers, row actions, confirms, pagination) | REQ-UI-003, FR-ADM-001, FR-P9-STD-003 |

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
| `Resources/views/admin/layout.html.twig` | Deprecated 1.x layout (extends the UiKit admin layout) | FR-ADM-002, FR-CFG-002, REQ-UI-003 |
| `Resources/views/admin/_legacy_layout.html.twig` | Deprecated bridge around a 1.x host layout | REQ-UI-003 |
| `Resources/views/admin/pages/index.html.twig` | Page list UI (engine badges) | FR-ADM-001, FR-P5-UI-003, FR-P9-UX-001, FR-P9-STD-003, FR-P9-STD-004 |
| `Resources/views/admin/pages/canvas.html.twig` | Grapes canvas host | FR-ADM-002, FR-P2-ADM-001 |
| `Resources/views/admin/pages/sections.html.twig` | Classic Sections UI (legacy banner) | FR-P2-ADM-004, FR-P9-UX-002, FR-P9-STD-004 |
| `Resources/views/admin/pages/seo.html.twig` | SEO admin screen | FR-ADM-001, FR-P9-STD-003 |
| `Resources/views/admin/pages/content.html.twig` | Content fields admin | FR-P5-UI-001, FR-P5-UI-002, FR-P9-STD-004 |
| `Resources/views/admin/pages/revisions.html.twig` | Revision history UI | FR-P3-REV-002, FR-P9-STD-004 |
| `Resources/views/admin/pages/revision_diff.html.twig` | Diff summary UI | FR-P3-REV-003 |
| `Resources/views/admin/pages/templates.html.twig` | Templates library UI | FR-P3-TPL-002, FR-P9-STD-004 |
| `Resources/views/Collector/page_builder.html.twig` | Profiler panel Twig | REQ-DEBUG-001 |
| `Resources/views/public/page.html.twig` | Public page wrapper | FR-REN-001, FR-P3-PREV-001 |
| `Resources/views/public/_seo_meta.html.twig` | SEO meta tags | FR-REN-001 |
| `Resources/views/public/_edit_button.html.twig` | Public edit pencil | FR-SEC-001 |
| `Resources/views/public/_editable_field.html.twig` | Inline content-field modal | FR-P5-SCH-005, FR-P9-STD-004 |
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
| `Resources/views/Icon/page-builder.svg` | Web Profiler panel / toolbar icon (`source()` in Collector view) | FR-ADM-001 |

## Resources — frontend assets

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/src/grapes-types.ts` | Typed canvas config/structure helpers | FR-P9-FE-001, REQ-ASSETS-003 |
| `Resources/assets/src/page-builder-canvas.ts` | Grapes canvas ESM source (strict TS) | FR-ADM-002, FR-P2-ADM-001, FR-P7-TAG-001, FR-P9-FE-002 |
| `Resources/assets/src/page-builder-inline-edit.ts` | Inline content-field editor source | FR-P5-SCH-005 |
| `Resources/assets/src/page-builder-admin.ts` | Admin IIFE entry (content-page image upload + media library) | FR-ADM-001, FR-SEC-004 |
| `Resources/assets/src/admin-media.ts` | Upload / media library picker logic (safe URLs, text names, UiKit dialog) | FR-P7-MED-002, FR-SEC-004, REQ-UI-003 |
| `Resources/assets/src/page-builder.css` | Source stylesheet | FR-ADM-002, REQ-ASSETS-001 |
| `Resources/public/js/page-builder-canvas.js` | Built Grapes canvas script | FR-ADM-002, FR-P9-FE-003 |
| `Resources/public/js/page-builder-inline-edit.js` | Built inline-edit script | FR-P5-SCH-005 |
| `Resources/public/js/page-builder-admin.js` | Built admin helpers script | FR-ADM-001, FR-SEC-004 |
| `Resources/public/css/page-builder.css` | Published stylesheet | FR-ADM-002, REQ-ASSETS-004 |
| `Resources/public/css/grapes.min.css` | Vendored GrapesJS 0.22.9 editor styles (no CDN link) | FR-P2-ADM-001, REQ-UI-003 |
| `Resources/public/css/grapick.min.css` | Vendored grapick 0.1.13 styles (gradient picker of `style_bg`) | FR-P2-ADM-001, REQ-UI-003 |

## Resources — translations

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/translations/NowoPageBuilderKitBundle.en.yaml` | English catalogue (reference) | FR-I18N-001, REQ-I18N-002, REQ-MAKE-004 |
| `Resources/translations/NowoPageBuilderKitBundle.es.yaml` | Spanish catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.de.yaml` | German catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.fr.yaml` | French catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.it.yaml` | Italian catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.nl.yaml` | Dutch catalogue | FR-I18N-001 |
| `Resources/translations/NowoPageBuilderKitBundle.pt.yaml` | Portuguese catalogue | FR-I18N-001 |

## Out-of-`src/` Spec Kit anchors (not inventory rows)

| Path | Notes |
| --- | --- |
| `docs/COOKBOOK.md` | FR-P9-DX-001 |
| `src/Resources/assets/tests/*.test.ts` | Vitest suite of the TypeScript sources (REQ-TEST-003; excluded from the Composer archive) |
| `docs/TEMPLATES.md` | REQ-UI-003 page contract (pages, scaffolds, data blocks, unique canvas page) |
| `examples/acme-grapes-block-pack/` | FR-P9-DX-002 (Composer example; not under `src/`) |

## Coverage summary

| Category | Files | Mapped |
| --- | ---: | ---: |
| Bundle entry | 1 | 1 |
| Controllers | 12 | 12 |
| Debug / Web Profiler | 4 | 4 |
| Dependency injection and compiler | 8 | 8 |
| Entities | 6 | 6 |
| Enums and locale | 6 | 6 |
| Event subscribers | 2 | 2 |
| Forms | 13 | 13 |
| Grapes block packs | 2 | 2 |
| Media | 7 | 7 |
| Repository | 8 | 8 |
| Services | 23 | 23 |
| HTML | 2 | 2 |
| Content fields and bind slots | 4 | 4 |
| Utilities | 1 | 1 |
| Security | 11 | 11 |
| Twig | 2 | 2 |
| UiKit page contract | 2 | 2 |
| Widget system | 12 | 12 |
| Resources — config and routing | 2 | 2 |
| Resources — admin and public Twig | 27 | 27 |
| Resources — frontend assets | 12 | 12 |
| Resources — translations | 7 | 7 |
| **Total production files (`git ls-files src`, without `src/Resources/assets/tests`)** | **174** | **174** |
