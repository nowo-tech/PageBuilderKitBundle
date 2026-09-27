# Code inventory — PageBuilderKitBundle baseline

**Baseline spec:** [`spec.md`](spec.md)  
**Package:** `nowo-tech/page-builder-kit-bundle`  
**Last audited:** 2026-09-25

Maps production files under `src/` to Phase 1 functional requirements.

**Total production sources:** 68 files (`find src -type f ! -path '*/assets/dist/*' ! -name '*.test.ts' | wc -l`)

## Bundle entry

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `NowoPageBuilderKitBundle.php` | Bundle entrypoint, Doctrine mappings, `BuilderLocales::bind()` | FR-DI-001, FR-ORM-001, FR-WRK-001 |

## Controllers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Controller/Admin/PageListController.php` | Page list and create form | FR-ADM-001 |
| `Controller/Admin/PageCanvasController.php` | Visual canvas shell | FR-ADM-002 |
| `Controller/Admin/PageDocumentApiController.php` | Document GET/POST, publish, CSRF | FR-ADM-003, FR-ADM-004, FR-ADM-005 |
| `Controller/Public/PageRenderController.php` | Public `/p/{pageKey}` render | FR-REN-003 |

## Dependency injection and compiler

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `DependencyInjection/Configuration.php` | Bundle configuration tree | FR-CFG-001, FR-CFG-002 |
| `DependencyInjection/NowoPageBuilderKitExtension.php` | Loads services, security, protection, FormKit prepend | FR-CFG-001, FR-DI-001, FR-SEC-001 |
| `DependencyInjection/TablePrefixListener.php` | Applies configured table prefix | FR-ORM-002 |
| `DependencyInjection/Compiler/TwigPathsPass.php` | Twig namespace and host override precedence | FR-REN-002 |
| `DependencyInjection/Compiler/WidgetTypePass.php` | Collects tagged widget types into registry | FR-WGT-003, FR-DI-001 |

## Entities

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Entity/BuilderPage.php` | Page aggregate (key, status, uuid) | FR-ORM-001, FR-DOC-001 |
| `Entity/BuilderPageTranslation.php` | Localized title/slug | FR-I18N-001, FR-ORM-001 |
| `Entity/BuilderDocument.php` | JSON structure storage | FR-DOC-001, FR-ORM-001 |
| `Entity/BuilderDocumentLocale.php` | Per-locale widget props | FR-DOC-003, FR-I18N-001 |
| `Entity/BuilderPageRevision.php` | Revision snapshot (Phase 3 hook) | FR-ORM-001 |

## Enums and locale

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Enum/PageStatus.php` | Draft / published status | FR-ADM-004, FR-REN-003 |
| `Enum/HtmlSanitizeStrategy.php` | Sanitize strategy enum | FR-SEC-003 |
| `Locale/BuilderLocales.php` | Config-backed locale catalog | FR-I18N-001, FR-CFG-001, FR-WRK-001 |

## Event subscribers

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `EventSubscriber/PageBuilderKitAdminAccessSubscriber.php` | Enforces access on `admin_page_builder_*` | FR-SEC-001, REQ-UI-002 |
| `EventSubscriber/WorkerStateResetSubscriber.php` | Clears `BuilderLocales` static bind on terminate | FR-WRK-001 |

## Forms

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Form/BuilderPageCreateType.php` | Create page form on admin list | FR-ADM-001 |

## Repository

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Repository/BuilderPageRepository.php` | Load pages by key | FR-DOC-001, FR-REN-001 |

## Services

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Service/DocumentNormalizer.php` | Structure schema v1 normalization | FR-DOC-004 |
| `Service/DocumentService.php` | Create/save/publish documents, sanitize props | FR-DOC-001, FR-DOC-003, FR-ADM-004, FR-SEC-003 |
| `Service/PageRenderProvider.php` | Build rendered page tree for Twig/public | FR-REN-001, FR-I18N-001 |

## Security

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Security/PageBuilderKitAccessCheckerInterface.php` | Access checker contract | FR-SEC-001 |
| `Security/ConfigurablePageBuilderKitAccessChecker.php` | Role-based checker | FR-SEC-001 |
| `Security/AllowAllPageBuilderKitAccessChecker.php` | Demo unauthenticated checker | FR-SEC-002 |
| `Security/PageBuilderProtection.php` | HTML sanitizer wiring for widgets | FR-SEC-003 |
| `Security/PageBuilderProtectionConfig.php` | Sanitize strategy value object | FR-SEC-003 |
| `Security/Html/PageBuilderHtmlSanitizerInterface.php` | Sanitizer contract | FR-SEC-003 |
| `Security/Html/NullPageBuilderHtmlSanitizer.php` | No-op sanitizer | FR-SEC-003 |
| `Security/Html/StripPageBuilderHtmlSanitizer.php` | Strip-tags sanitizer | FR-SEC-003 |
| `Security/Html/AllowlistPageBuilderHtmlSanitizer.php` | Allowlist sanitizer | FR-SEC-003 |

## Twig

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Twig/PageBuilderKitExtension.php` | Render function, widget types, layout hints | FR-REN-002 |

## Widget system

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Widget/WidgetTypeInterface.php` | Widget type contract | FR-WGT-002, FR-WGT-003 |
| `Widget/AbstractWidgetType.php` | Shared template path and sanitization helpers | FR-WGT-002 |
| `Widget/WidgetTypeRegistry.php` | Runtime type lookup | FR-WGT-003 |
| `Widget/Type/HeadingWidgetType.php` | Heading widget | FR-WGT-001 |
| `Widget/Type/TextWidgetType.php` | Text widget | FR-WGT-001 |
| `Widget/Type/HtmlWidgetType.php` | HTML widget | FR-WGT-001 |
| `Widget/Type/ImageWidgetType.php` | Image widget | FR-WGT-001 |
| `Widget/Type/ButtonWidgetType.php` | Button widget | FR-WGT-001 |
| `Widget/Type/SpacerWidgetType.php` | Spacer widget | FR-WGT-001 |

## Resources — config and routing

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/config/services.yaml` | Service definitions and widget tags | FR-DI-001 |
| `Resources/config/routing.yaml` | Attribute route import | FR-ADM-001, FR-ADM-002, FR-REN-003 |

## Resources — admin and public Twig

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/views/admin/layout.html.twig` | Admin shell layout | FR-ADM-002, FR-CFG-002 |
| `Resources/views/admin/pages/index.html.twig` | Page list UI | FR-ADM-001 |
| `Resources/views/admin/pages/canvas.html.twig` | Canvas host template | FR-ADM-002 |
| `Resources/views/public/page.html.twig` | Full page public wrapper | FR-REN-001 |
| `Resources/views/widgets/_section.html.twig` | Section partial | FR-REN-004 |
| `Resources/views/widgets/_column.html.twig` | Column partial | FR-REN-004 |
| `Resources/views/widgets/heading.html.twig` | Heading widget template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/text.html.twig` | Text widget template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/html.html.twig` | HTML widget template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/image.html.twig` | Image widget template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/button.html.twig` | Button widget template | FR-WGT-001, FR-REN-004 |
| `Resources/views/widgets/spacer.html.twig` | Spacer widget template | FR-WGT-001, FR-REN-004 |

## Resources — frontend assets

| Source file | Purpose | Requirement IDs |
| --- | --- | --- |
| `Resources/assets/page-builder-canvas.ts` | Canvas TypeScript source | FR-ADM-002, FR-ADM-003 |
| `Resources/public/js/page-builder-canvas.js` | Compiled/bundled canvas script | FR-ADM-002 |
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
| PHP (excluding Resources subtree) | 47 |
| Twig views | 12 |
| YAML (config + translations) | 9 |
| TS/JS/CSS assets | 3 |
| **Total** | **68** |
