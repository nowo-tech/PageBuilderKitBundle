# FrankenPHP worker mode audit

| Field | Value |
|-------|-------|
| Package | `nowo-tech/page-builder-kit-bundle` (`symfony-bundle`) |
| Audited revision | Phase 1 baseline (pre-1.0.0) |
| Audit date | 2026-09-25 |
| Method | Manual review of `src/` services, controllers, subscribers, Twig extension, DI extension, and `Resources/config/services.yaml` |
| **Verdict** | ✅ **Compatible with long-lived workers** for bundle-owned correctness: shared services are readonly or stateless; per-request work uses injected dependencies. **Note:** `BuilderLocales::bind()` sets a static instance in `boot()` for legacy call sites; `WorkerStateResetSubscriber` clears it on `kernel.terminate` so workers do not retain a stale binding across requests. Hosts should inject `BuilderLocales` instead of static access. |

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the strict variant: the kernel is **not** rebooted between requests unless the host configures otherwise.

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `DocumentService`, `PageRenderProvider`, repositories: no cross-request caches in Phase 1 |
| Static properties | ⚠️ Documented | `BuilderLocales::$boundInstance` bound in `NowoPageBuilderKitBundle::boot()`, cleared on terminate |
| Request / user captured in services | ✅ | Access checker asks `AuthorizationChecker` per call; no user stored in services |
| Twig extension | ✅ | Functions only (no per-user globals); config strings are immutable |
| Doctrine / EntityManager | ✅ | Standard request-scoped EM; hosts may clear identity map for memory hygiene |
| CSRF / document API | ✅ | Validated per request in `PageDocumentApiController` |

## Services reviewed

| Service | Shared | Mutable state | Worker-safe |
|---------|--------|---------------|-------------|
| `Service\DocumentService` | yes | none (`readonly` deps) | ✅ |
| `Service\PageRenderProvider` | yes | none (`readonly` deps) | ✅ |
| `Service\DocumentNormalizer` | yes | none | ✅ |
| `Twig\PageBuilderKitExtension` | yes | none; functions delegate per call | ✅ |
| `Widget\WidgetTypeRegistry` | yes | immutable type list after compile | ✅ |
| `Security\ConfigurablePageBuilderKitAccessChecker` | yes | none (`readonly`) | ✅ |
| `Security\PageBuilderProtection` | yes | none; sanitizer created per strategy | ✅ |
| `EventSubscriber\PageBuilderKitAdminAccessSubscriber` | yes | none (`readonly`) | ✅ |
| `EventSubscriber\WorkerStateResetSubscriber` | yes | none | ✅ |
| `Locale\BuilderLocales` | yes | readonly config; static bind optional | ✅ with terminate reset |
| `Repository\BuilderPageRepository` | yes | none | ✅ |
| Admin / public controllers | yes | none (`readonly` deps) | ✅ |

## BuilderLocales static bind

- **Where:** `NowoPageBuilderKitBundle::boot()` calls `BuilderLocales::bind($locales)`.
- **Why:** Legacy-friendly static access for code that cannot receive DI (discouraged for new code).
- **Worker impact:** Without reset, the static could outlive config changes mid-worker lifetime (rare). `WorkerStateResetSubscriber` calls `BuilderLocales::clearBoundInstance()` after each main request.
- **Recommendation:** Inject `BuilderLocales` in application and bundle code; treat static bind as compatibility only.

## Usage recommendations in worker mode

- Prefer injected `BuilderLocales` over static accessors.
- Keep `services_resetter` enabled (Symfony default) for framework and Doctrine hygiene.
- Custom `PageBuilderKitAccessCheckerInterface` or HTML sanitizer services must be stateless or implement `ResetInterface`.
- Optional: `EntityManager::clear()` or `FRANKENPHP_LOOP_MAX` for memory bounds on busy workers.

## Re-audit triggers

Re-run this audit when adding: request-scoped caches on `PageRenderProvider` or `DocumentService`, new Twig globals, document memoization, or static mutable state outside `BuilderLocales`.

See also [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).
