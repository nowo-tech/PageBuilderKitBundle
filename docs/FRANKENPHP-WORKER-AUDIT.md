# FrankenPHP worker mode audit

| Field | Value |
|-------|-------|
| Package | `nowo-tech/page-builder-kit-bundle` (`symfony-bundle`) |
| Audited revision | **v1.3.0 + Phase 5 MVP (unreleased)** |
| Audit date | 2026-10-02 |
| Method | Manual review of `src/` services, controllers, subscribers, Twig extension, DI extension, DataCollector/trace, content fields, capabilities |
| **Verdict** | ✅ **Compatible with long-lived workers** for bundle-owned correctness: shared services are readonly or assign-once; request-scoped debug state implements `ResetInterface`; per-request access/capability checks ask AuthorizationChecker without storing the user. |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the strict variant: the kernel is **not** rebooted between requests unless the host configures otherwise.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `DocumentService`, `PageRenderProvider`, `ContentFieldsService`, registries: no cross-request caches |
| Static properties | ⚠️ Documented | `BuilderLocales::$boundInstance` bound in `boot()`, cleared on `kernel.terminate` |
| Request / user captured in services | ✅ | Access checker + capabilities evaluate per call |
| Twig extension | ✅ | Functions only (`can_edit`, `can`, render); no per-user globals |
| Debug / DataCollector | ✅ | `PageBuilderKitTrace` + `ResetInterface` / `kernel.reset` between requests |
| Revisions / templates | ✅ | Stateless stores; Doctrine EM request-scoped |
| Content fields | ✅ | Values live in document JSON; normalizer/service readonly |
| CSRF / document API | ✅ | Validated per request in `PageDocumentApiController` / content forms |
| Doctrine / EntityManager | ✅ | Standard request-scoped EM; hosts may clear identity map for memory hygiene |

## Services reviewed (post–Phase 3–5)

| Service | Shared | Mutable state | Worker-safe |
|---------|--------|---------------|-------------|
| `Service\DocumentService` | yes | none (`readonly` deps) | ✅ |
| `Service\PageRenderProvider` | yes | assign-once deps | ✅ |
| `Service\ContentFieldsNormalizer` / `ContentFieldsService` | yes | none | ✅ |
| `Service\PageTemplateService` / `PageRevisionStore` | yes | none | ✅ |
| `Service\DocumentNormalizer` | yes | none | ✅ |
| `Twig\PageBuilderKitExtension` | yes | none; functions delegate per call | ✅ |
| `Widget\WidgetTypeRegistry` / `Grapes\GrapesBlockPackRegistry` | yes | immutable after compile | ✅ |
| `Security\ConfigurablePageBuilderKitAccessChecker` | yes | none (`readonly`) | ✅ |
| `Security\PageBuilderProtection` | yes | none | ✅ |
| `EventSubscriber\PageBuilderKitAdminAccessSubscriber` | yes | none | ✅ |
| `EventSubscriber\WorkerStateResetSubscriber` | yes | none | ✅ |
| `Debug\PageBuilderKitTrace` | yes | request buffer | ✅ with reset |
| `DataCollector\PageBuilderKitDataCollector` | profiler | request | ✅ |
| `Locale\BuilderLocales` | yes | readonly config; static bind optional | ✅ with terminate reset |
| Admin / public controllers | yes | none | ✅ |

## BuilderLocales static bind

- **Where:** `NowoPageBuilderKitBundle::boot()` calls `BuilderLocales::bind($locales)`.
- **Why:** Legacy-friendly static access (discouraged for new code).
- **Worker impact:** `WorkerStateResetSubscriber` clears the bind after each main request.
- **Recommendation:** Inject `BuilderLocales`; treat static bind as compatibility only.

## Usage recommendations in worker mode

- Prefer injected `BuilderLocales` over static accessors.
- Keep `services_resetter` enabled (Symfony default).
- Custom `PageBuilderKitAccessCheckerInterface` or HTML sanitizer services must be stateless or implement `ResetInterface`.
- Optional: `EntityManager::clear()` or `FRANKENPHP_LOOP_MAX` for memory bounds on busy workers.
- Do not put user/capability flags in Twig globals — use `nowo_page_builder_can()` / `nowo_page_builder_can_edit()` per render.

## Re-audit triggers

Re-run when adding: request-scoped caches on render/document services, new Twig globals, document memoization, static mutable state outside `BuilderLocales`, or long-lived buffers in the debug trace without reset.

See also [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).
