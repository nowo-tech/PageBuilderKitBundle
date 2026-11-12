# FrankenPHP worker mode audit

| Field | Value |
|-------|-------|
| Package | `nowo-tech/page-builder-kit-bundle` (`symfony-bundle`) |
| Audited revision | **1.15.1: outermost unit-of-work boundaries of the bundle's own entity manager (REQ-RUNTIME-002 rule 4)**; 1.14.1 debug trace boundaries; earlier full review v1.3.0 + Phase 5 MVP |
| Audit date | 2026-11-12 (own manager boundaries); 2026-11-11 (trace boundaries); 2026-10-02 (full review) |
| Method | Manual review of `src/` services, controllers, subscribers, Twig extension, DI extension, DataCollector/trace, content fields, capabilities |
| **Verdict** | ✅ **Compatible with long-lived workers** for bundle-owned correctness: shared services are readonly or assign-once; request-scoped debug state is cleared at the main request / Messenger / console boundaries by the bundle itself and also implements `ResetInterface`; per-request access/capability checks ask AuthorizationChecker without storing the user. |

## Table of contents

- [Execution model assumed](#execution-model-assumed)
- [Summary](#summary)
- [Services reviewed (post–Phase 3–5)](#services-reviewed-postphase-35)
- [Own entity manager boundaries](#own-entity-manager-boundaries)
- [Debug trace boundaries](#debug-trace-boundaries)
- [BuilderLocales static bind](#builderlocales-static-bind)
- [Usage recommendations in worker mode](#usage-recommendations-in-worker-mode)
- [Re-audit triggers](#re-audit-triggers)

## Execution model assumed

FrankenPHP worker mode boots the Symfony kernel once per worker and serves many requests with the same container. This audit assumes the strict variant: the kernel is **not** rebooted between requests (`FRANKENPHP_RESET_KERNEL=false`).

With the stock Symfony kernel, `FRANKENPHP_RESET_KERNEL=false` still runs `services_resetter` (`kernel.reset`) before every request after the first. The bundle does **not** rely on it: it must also be correct where the resetter does not run, i.e. a host that removes, replaces or bypasses `services_resetter` (or drives `http_kernel` directly), Messenger consumers started with `--no-reset`, and long-running console commands (REQ-RUNTIME-002).

## Summary

| Area | Status | Notes |
|------|--------|-------|
| Mutable state in shared services | ✅ | `DocumentService`, `PageRenderProvider`, `ContentFieldsService`, registries: no cross-request caches |
| Static properties | ⚠️ Documented | `BuilderLocales::$boundInstance` bound in `boot()`, cleared on `kernel.terminate` |
| Static `WeakMap` (1.15) | ✅ | `Media\AssetUploadData` keeps each `AssetUpload` VO's path / client name in a `WeakMap` keyed by the per-request VO: entries are collected with the VO, nothing crosses requests |
| Request / user captured in services | ✅ | Access checker + capabilities evaluate per call |
| Twig extension | ✅ | Functions only (`can_edit`, `can`, render); no per-user globals |
| Debug / DataCollector | ✅ | `PageBuilderKitTrace` cleared by `PageBuilderKitTraceBoundarySubscriber` (main `kernel.request`, Messenger received / handled / failed, `console.terminate`); `ResetInterface` kept |
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
| `Persistence\PageBuilderTransaction` (1.14) | yes | request-scoped event outbox | ✅ dispatched or discarded at `kernel.terminate` / `kernel.exception` / console / Messenger boundaries, plus `ResetInterface` |
| `Persistence\OwnEntityManagerSubscriber` (1.14, only with `doctrine.entity_manager`; outermost boundaries since 1.15.1) | yes | unit-of-work nesting state (console depth, message / request in flight) | ✅ recreates the bundle's own manager when closed and clears it at the OUTERMOST request / message / command boundary only; never touches the host managers (not registered when `doctrine.entity_manager` is the host's default manager) |
| `Debug\PageBuilderKitTrace` | yes (debug + collector only) | request buffer | ✅ cleared at every boundary by `PageBuilderKitTraceBoundarySubscriber`, plus `ResetInterface` |
| `Debug\PageBuilderKitTraceBoundarySubscriber` (1.14.1, debug + collector only) | yes | none (lazy iterator of an `IGNORE_ON_UNINITIALIZED_REFERENCE` to the trace) | ✅ |
| `DataCollector\PageBuilderKitDataCollector` | profiler | request | ✅ |
| `Locale\BuilderLocales` | yes | readonly config; static bind optional | ✅ with terminate reset |
| Admin / public controllers | yes | none | ✅ |

## Own entity manager boundaries

- **State:** with `doctrine.entity_manager` the bundle owns that manager (it maps the bundle entities only) and keeps
  it usable in a kept-alive kernel: `OwnEntityManagerSubscriber` holds a console depth counter and two flags (a
  Messenger message in flight, a main request in flight). No request, user or tenant data.
- **Cleared by the bundle (1.15.1):** the manager is cleared (or recreated when a failed flush closed it) at the
  OUTERMOST boundary only: the main `kernel.request` / `kernel.terminate` outside a command and a message; Messenger
  received / handled / failed; the outermost `console.command` / `console.terminate` outside a message and a main
  request. `WorkerStoppedEvent` ends the in-flight message (a skipped message). The manager and the registry are
  injected as iterators of `IGNORE_ON_UNINITIALIZED_REFERENCE` references, so a boundary never builds the manager.
- **Before 1.15.1 (1.14.0 – 1.15.0):** every `console.terminate` and `kernel.terminate` cleared the manager. A command
  run in-process from another command or from a Messenger handler, or a `kernel.terminate` inside a command, cleared
  it in the middle of the outer unit; with `transaction: host` the host's pending (unflushed) bundle writes were
  discarded.
- **Tests:** `tests/Unit/Persistence/OwnEntityManagerSubscriberTest.php` (nested command, command inside a handler,
  handler inside a command, request inside a command or a message, command inside a request, closed manager);
  `PageBuilderPersistenceKernelTest::testNestedBoundariesNeverDiscardTheHostUnitOfWork` (real dispatcher,
  `transaction: host`); `PageBuilderPersistenceKernelTest::testKeptAliveKernelAlternatingTenantsSeeOnlyFreshOwnData`
  boots **one** kernel, never calls `services_resetter`, and drives `http_kernel` handle / terminate for **1,000**
  requests alternating two tenants (`tenant-a`, `tenant-b`); each tenant's row is changed through DBAL before its
  request, each response holds only that tenant's fresh status, the unit of work is empty after every request, and
  memory stays within **4 MiB** after a 50-request warm-up.

## Debug trace boundaries

- **State:** `PageBuilderKitTrace` buffers the renders, public outcomes and admin actions of the current unit of work for
  the Web Profiler panel. It exists only with `kernel.debug: true` and `debug.collector: true`.
- **Cleared by the bundle (1.14.1):** `PageBuilderKitTraceBoundarySubscriber` at the main `kernel.request` (priority
  4096, start of the request, so the profiler still collects the finished request; sub-requests keep the trace),
  Messenger `WorkerMessageReceivedEvent` (before the message) and `WorkerMessageHandledEvent` /
  `WorkerMessageFailedEvent` (after it), and `console.terminate`. `ResetInterface` / `kernel.reset` stays as a second
  path. The subscriber receives the trace as a lazy iterator of an `IGNORE_ON_UNINITIALIZED_REFERENCE` reference, so a
  boundary never builds the trace.
- **Before 1.14.1 (1.1.0 – 1.14.0):** only `kernel.reset` cleared it; where the resetter does not run (see the
  execution model above) the trace grew and the profiler panel showed earlier units of work. Debug mode only.
- **Worker test:** `tests/Integration/Persistence/PageBuilderKitTraceWorkerTest.php` boots **one** kernel in debug
  with the collector on and never calls `services_resetter`: 1,000 requests through `http_kernel` handle / terminate
  alternating two users (each response lists only its own entry), 1,000 Messenger received / handled / failed cycles
  alternating two users, and one `console.terminate`; memory margin 4 MiB after a 50-iteration warm-up.
  `tests/Unit/Debug/PageBuilderKitTraceBoundarySubscriberTest.php` covers sub-requests and the uninitialized reference;
  `tests/Unit/Debug/PageBuilderKitTraceTest.php` calls `reset()` directly.

## BuilderLocales static bind

- **Where:** `NowoPageBuilderKitBundle::boot()` calls `BuilderLocales::bind($locales)`.
- **Why:** Legacy-friendly static access (discouraged for new code).
- **Worker impact:** `WorkerStateResetSubscriber` clears the bind after each main request.
- **Recommendation:** Inject `BuilderLocales`; treat static bind as compatibility only.

## Usage recommendations in worker mode

- Prefer injected `BuilderLocales` over static accessors.
- The bundle does not need `services_resetter`: it clears its own request state at the boundaries above. A host that
  disables or bypasses the resetter must still clear **its own** entity managers at every boundary (main request,
  Messenger message, console command; REQ-RUNTIME-002 rule 7), or the identity map serves stale rows across requests.
- Custom `PageBuilderKitAccessCheckerInterface` or HTML sanitizer services must be stateless or implement `ResetInterface`.
- Optional: `EntityManager::clear()` or `FRANKENPHP_LOOP_MAX` for memory bounds on busy workers.
- With `doctrine.entity_manager` (1.14) the bundle recovers and clears its own manager itself, without the services
  resetter: `tests/Integration/Persistence/PageBuilderPersistenceKernelTest.php` drives `http_kernel`
  handle/terminate directly (a failed write, then 300 requests with flat memory and an empty unit of work; since
  1.15.1 also 1,000 requests alternating two tenants, see [Own entity manager boundaries](#own-entity-manager-boundaries)).
- Do not put user/capability flags in Twig globals — use `nowo_page_builder_can()` / `nowo_page_builder_can_edit()` per render.

## Re-audit triggers

Re-run when adding: request-scoped caches on render/document services, new Twig globals, document memoization, static mutable state outside `BuilderLocales`, or long-lived buffers in the debug trace without reset.

See also [DEMO-FRANKENPHP.md](DEMO-FRANKENPHP.md).
