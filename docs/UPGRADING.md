# Upgrading

This document describes how to upgrade **Page Builder Kit Bundle** between released versions.

## Table of contents

- [Unreleased](#unreleased)
- [To 1.19.0](#to-1190)
  - [The private entity manager notice](#the-private-entity-manager-notice)
  - [Reading the host-flush probe](#reading-the-host-flush-probe)
  - [Opting in: what changes](#opting-in-what-changes)
  - [The Twig context provider notice](#the-twig-context-provider-notice)
  - [Preparing for 2.0](#preparing-for-20)
- [To 1.18.0](#to-1180)
- [To 1.17.0](#to-1170)
  - [Concurrency (REQ-RUNTIME-003)](#concurrency-req-runtime-003)
- [To 1.16.1](#to-1161)
- [To 1.16.0](#to-1160)
- [To 1.15.2](#to-1152)
- [To 1.15.1](#to-1151)
- [To 1.15.0](#to-1150)
- [To 1.14.1](#to-1141)
- [To 1.14.0](#to-1140)
- [To 1.13.1](#to-1131)
- [To 1.13.0](#to-1130)
- [To 1.12.1](#to-1121)
- [To 1.12.0](#to-1120)
  - [Security (Appendix BO)](#security-appendix-bo)
  - [Layout](#layout)
  - [Template contract](#template-contract)
  - [Configuration](#configuration)
  - [Front-end](#front-end)
- [To 1.11.1](#to-1111)
- [To 1.11.0](#to-1110)
- [To 1.10.2](#to-1102)
- [To 1.10.1](#to-1101)
- [To 1.10.0](#to-1100)
- [To 1.9.1](#to-191)
- [To 1.9.0](#to-190)
- [To 1.8.1](#to-181)
- [1.8.0](#180)
- [1.7.0](#170)
- [1.6.1](#161)
- [1.6.0](#160)
- [1.5.1](#151)
- [1.5.0](#150)
- [1.4.4](#144)
- [1.4.3](#143)
- [1.4.2](#142)
- [1.4.1](#141)
- [1.4.0](#140)
- [1.3.0](#130)
- [1.2.0](#120)
- [1.1.1](#111)
- [1.1.0](#110)
- [1.0.0 — first release](#100--first-release)
  - [Recommended first config](#recommended-first-config)
  - [Public pages and i18n routes](#public-pages-and-i18n-routes)
  - [Optional demo](#optional-demo)

## Unreleased

## To 1.19.0

From **1.18.x**:

```bash
composer update nowo-tech/page-builder-kit-bundle nowo-tech/transaction-doctrine
```

1.19.0 is the last 1.x minor before 2.0. Without configuration it behaves as 1.18. It adds the
`doctrine.private_entity_manager` opt-in, deprecation notices for what 2.0 changes, and a host-flush probe that lists
what the 2.0 change would mean for your application. It requires `nowo-tech/transaction-doctrine` ^1.9.1. No schema
change.

### The private entity manager notice

With `doctrine.transaction: bundle` (the default) and neither `doctrine.private_entity_manager` nor
`doctrine.entity_manager` set, building the container now logs:

> Not setting "nowo_page_builder_kit.doctrine.private_entity_manager" is deprecated: page builder writes
> (doctrine.transaction: bundle) flush the application's default entity manager, including its own pending changes.
> 2.0 defaults to true: a private entity manager on the same connection and tables that never flushes them. Opt in now
> with "nowo_page_builder_kit: { doctrine: { private_entity_manager: true } }" (see UPGRADING.md "To 1.19.0"; "false"
> is removed in 2.0).

With `private_entity_manager: false` it logs instead:

> Setting "nowo_page_builder_kit.doctrine.private_entity_manager" to false is deprecated and removed in 2.0: page
> builder writes (doctrine.transaction: bundle) flush the application's default entity manager, including its own
> pending changes; 2.0 always writes through a private entity manager on the same connection and tables. Set it to true
> now, or set "nowo_page_builder_kit.doctrine.entity_manager" to a dedicated entity manager (see UPGRADING.md "To
> 1.19.0").

The same notices are triggered when `doctrine.entity_manager` names your default entity manager (for example
`entity_manager: default`): the bundle then flushes that manager exactly as when the option is unset, and the
host-flush probe runs too.

What it means: today every page builder write flushes your default entity manager (the admin, `PagePublisherInterface`,
`DocumentService`, revisions, templates, content fields). Anything your code had left pending there is written by that
flush too. In 2.0 the bundle writes its rows through a private manager and never flushes your pending changes.

To opt in now (the tables, the connection and the mapping do not change; no migration):

```yaml
nowo_page_builder_kit:
    doctrine:
        private_entity_manager: true
```

`false` keeps the 1.x behaviour for now, with its own notice: it is removed in 2.0. Two settings stay in 2.0 and get
neither notice nor probe:

- `doctrine.entity_manager: <name>` naming a dedicated manager other than the default one (the DDD option): the
  bundle already never flushes your default manager.
- `doctrine.transaction: host` (DDD hosts): your use case owns the flush.

`private_entity_manager: true` cannot be combined with either of them; the build fails with a message saying so.

### Reading the host-flush probe

While `private_entity_manager` is unset or `false` (with `transaction: bundle` and no `entity_manager`, or one naming
the default manager), the bundle calls
the host-flush probe of nowo-tech/transaction-doctrine right before each of its flushes of your default manager. Every
entity class outside `Nowo\PageBuilderKitBundle\Entity` with pending changes in that manager (a scheduled insert or
delete, a changed field, association or collection) triggers one deprecation per request, message or command:

> Your pending changes to App\Entity\Order were flushed by NowoPageBuilderKitBundle; 2.0 no longer does.

The bundle never changes your entities itself, so every class listed is your application's own work that relies on a
page builder write to reach the database. Read the collected messages as your migration list:

1. Run your usual traffic and test suite on 1.19 with deprecations logged (the Symfony profiler, the `deprecation` log
   channel, or your test runner's deprecation report).
2. For each class listed, find the code that changes it without flushing: typically a listener of a page builder event
   (`PageCreatedEvent`, `PagePublishedEvent`, ...), a Twig context provider, an asset storage of yours, or code that calls
   `flushPage()` or the revision and template services in the middle of its own changes. Make it flush its own changes
   (or run it in your own transaction).
3. When no message is left, set `private_entity_manager: true`. The probe is silent in that mode.

Write units (since 1.17) already refuse to start while your manager holds unflushed changes (`LogicException`, see
[To 1.17.0](#to-1170)); the probe also covers the single flushes (`DocumentService::flushPage()`, the revision store,
templates) and the changes made during a unit by code the bundle calls. It is read-only: it flushes, clears, closes or
resets nothing, and a failure of the probe is logged and never breaks the write. It is NowoTransactionDoctrineBundle's
shared `nowo_transaction_doctrine.host_flush_probe` when you register that bundle, otherwise one of the bundle's own;
both are reset at every request, message and command boundary (FrankenPHP worker mode with the kernel reset off
included). The shared probe is reset by NowoTransactionDoctrineBundle's own boundary listener; the bundle resets it
itself only when you turn that listener off (`unit_of_work_boundaries: false`).

### Opting in: what changes

With `private_entity_manager: true`:

- The bundle's repositories, `nowo_page_builder_kit.entity_manager` (which its controllers and services get) and its
  write units use the private manager. It is created on the connection, configuration (mapping, metadata cache) and
  event manager (your Doctrine listeners) of your default entity manager. The SQL filters enabled on that manager are
  mirrored.
- A bundle flush writes only the bundle's entities; your pending changes stay pending. A failed bundle write (a
  deadlock, a lock wait timeout, a unique violation) closes only the private manager, which is replaced on the next use;
  your manager stays open and untouched. The lock-conflict answer is unchanged (409 + `CONFLICT_001` + `Retry-After`
  when nothing was committed, `CONFLICT_002` without it when part was).
- The private manager is dropped at every request, message and command boundary (and at `kernel.reset`): the bundle's
  entities never stay managed from one unit to the next. Changes the bundle left unflushed then are discarded and logged
  as a warning (classes and counts only).
- `getRepository(BuilderPage::class)` on your own manager (or the `BuilderPageRepository` service) returns the
  bundle's repository, which reads through the private manager: pages you load that way belong to the private manager.
  Do not `persist()` or `flush()` them, or bundle entities linked to them (a `BuilderPageRevision`), through your
  manager; change them through the bundle's services, or persist and flush them through
  `nowo_page_builder_kit.entity_manager` (the demo's seeder does so).
- An entity of yours that points at a bundle entity (a many-to-one or one-to-one to `BuilderPage`, for example) must
  get it from your own manager with `$yourManager->getReference(BuilderPage::class, $id)`, not from the bundle's
  repository: a page loaded through the private manager is unknown to your manager, and your flush would refuse it as
  a new entity found through a relationship.
- The connection is shared: a transaction you open around a bundle call includes the bundle's rows (the private flush
  then uses a savepoint), and your rollback undoes them. The write events are still dispatched after the real commit.
- DBAL 3 with `use_savepoints` off: a failed private flush inside your transaction marks your transaction rollback-only.
  Turn savepoints on (always on in DBAL 4).

### The Twig context provider notice

A service tagged `nowo_page_builder_kit.grapes_twig_context` that implements only the deprecated
`GrapesTwigContextProviderInterface` (`getContext(BuilderPage $page, string $locale)`) now triggers, once per provider
class when the render service is built:

> The Twig context provider "App\Twig\MyProvider" implements only
> "Nowo\PageBuilderKitBundle\Service\GrapesTwigContextProviderInterface", which 2.0 removes. Implement
> "Nowo\PageBuilderKitBundle\Service\GrapesTwigPageContextProviderInterface" (getPageContext(PageContext $page,
> string $locale)) instead; see UPGRADING.md "To 1.19.0".

Implement `getPageContext(PageContext $page, string $locale): array` instead (see [To 1.15.0](#to-1150)); `PageContext`
carries the page id, uuid, key, status, publication date, and the title and slug of the rendered locale. A provider
that implements both interfaces gets no notice (the new one is used). The deprecated asset storage port
(`PageBuilderAssetStorageInterface::store(UploadedFile)`) keeps the notice it has had since 1.15.

### Preparing for 2.0

2.0 contains only the deprecations announced in 1.x:

- The bundle always writes through the private entity manager with `transaction: bundle` and no `entity_manager`:
  `doctrine.private_entity_manager` is removed (`true` is the only behaviour; `false` is gone). `doctrine.entity_manager`
  (a dedicated manager) and `transaction: host` stay.
- `GrapesTwigContextProviderInterface`, `PageBuilderAssetStorageInterface` (with its service alias) and the built-in
  storages' `store(UploadedFile)` are removed (see [To 1.15.0](#to-1150)).
- The other deprecated items of 1.x are removed with them: the legacy admin layout bridge (see To 1.12.0) and the
  ignored `doctrine.connection` and `web_ui.css_framework` keys.

After 2.0.0 is tagged, the 1.x line gets security fixes for 6 months: Moderate and higher are backported to the last
1.x minor; Low ones are fixed in 2.x only, with the advice to upgrade.

## To 1.18.0

From **1.17.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

No migration and no new requirement. The page builder assets are rebuilt (`page-builder-canvas.js`): if you publish them
yourself, run `assets:install` again.

- **Canvas and classic sections editor** now refuse a save from a document another editor saved since it was loaded
  (409 `PAGE_BUILDER_STALE_EDIT` in JSON; flash and redirect in the classic editor). Nothing to change in your code. If
  you call `POST /pages/{pageKey}/document` from your own client, send `baseToken` (the `token` of
  `GET /pages/{pageKey}/document`) to get the same protection; without it the save replaces the document as before.
  A client that treats every non-200 answer of the save as failure needs no change.
- **Classic sections editor template override:** the stale check needs the hidden `base_token` field of the form. If you
  override `sections.html.twig` and do not render it (`form_rest`, or `{{ form_widget(form.base_token) }}`), the check
  fails open: the save has no token and replaces the document as before. Render it to get the protection.
- `DocumentService::saveDocument()` has a new optional fourth argument `?string $baseToken = null`. If you decorate or
  mock the service, add it. `DocumentService::documentToken()` and `saveTranslation()` are new.
- The SEO form now saves field by field. If you override `seo.html.twig`, keep rendering the unrendered rest of the form
  (`form_rest` / the parent block) so the hidden `originals` field is posted; without it every field counts as changed
  (the earlier behaviour).
- `PageCanvasController` takes `DocumentService` as a new last constructor argument (autowired; only a decorator that
  builds it by hand is affected).

## To 1.17.0

From **1.16.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

### Concurrency (REQ-RUNTIME-003)

```bash
composer update nowo-tech/page-builder-kit-bundle
```

This also installs `nowo-tech/transaction-doctrine` `^1.6` and `nowo-tech/transaction-contract` `^1.0`. Registering
transaction-doctrine's Symfony bundle is optional; when it is registered, the bundle follows its `conflict_response`
and its request commit mark. No schema change.

- **Lock conflicts answer 409.** A deadlock, lock wait timeout or serialization failure on the admin routes answered a
  500. It now answers 409 with `X-Conflict-Code: CONFLICT_001` and `Retry-After` (nothing committed, retry is safe),
  or 409 with `CONFLICT_002` and no `Retry-After` (part of the request may be committed: reload first). JSON calls keep
  `{"error": …}` with `"error": "conflict"` and the new `code` and `message` keys. Keep 503 if your proxy or monitor
  expects it:

  ```yaml
  nowo_page_builder_kit:
      conflict_response:
          status: 503
  ```

- **Duplicate translation.** Two editors adding the same missing page translation at once answered a 500. The second
  one now gets 409 with `X-Error-Code: PAGE_BUILDER_CONCURRENT_EDIT` (no `X-Conflict-Code`).
- **Duplicate page or template key.** Two editors creating the same page key, or saving the same new template key, at
  once: the second gets the domain error ("already exists", 400 / form error) instead of a 500.
- **Unflushed changes of yours (host entity manager).** Without `doctrine.entity_manager`, the bundle's writes run
  through `DoctrineTransactionRunner::forHostEntityManager()` on your manager. A write unit now throws a
  `LogicException` before it opens its transaction when your manager holds unflushed changes (it would otherwise carry
  them into a transaction that may roll back). The admin routes start clean. If you call `PagePublisherInterface`,
  `DocumentService`, `ContentFieldsService`, `PageRevisionService` or `PageTemplateService` from your own code with
  pending changes, flush them first, or give the bundle its own manager (`doctrine.entity_manager`).
  `DocumentService::flushPage()` (your form changed the entities) is unchanged: one flush.
- **`postLoad` listeners on your manager (for example nowo-tech/doctrine-encrypt-bundle).** A listener that changes
  entities after loading them (decryption) makes them look changed, so the host runner sees pending changes and every
  bundle write fails with `LogicException`. Set `nowo_page_builder_kit.doctrine.entity_manager` to a manager of the
  bundle's own (mapping only the bundle entities, without that listener).
- **Content form and schema.** The admin content form now sends only the editor's intent. It posts
  `fieldOriginals[<locale>][<key>]` (a fingerprint of each value shown) and `labelOriginals[<locale>][<key>]` (each
  label shown); `ContentFieldsService::mergeValues()` writes under the page lock only the values and labels that
  differ from what was shown. `saveSchemaWithField()` / `saveSchemaWithoutField()` apply one schema change to what is
  stored, and adding a key that exists is refused. If you override the content template, keep rendering the hidden
  `fieldOriginals` and `labelOriginals` children (without them every posted non-boolean value is still written, as before, but an unchecked boolean checkbox cannot be told from an untouched field, so the form can no longer clear (uncheck) a boolean). `saveValues()` and `saveSchema()` are unchanged
  for your code (whole replacement, now one unit under the lock).
- **Conflict listener priority.** `PageBuilderConflictListener` runs at `kernel.exception` priority 64, before the
  framework's error listener logs the exception; it still acts on the bundle's routes only.
- **A closed manager is replaced.** After a write unit whose flush closed your manager, the runner replaces it through
  the registry (an open manager is never cleared or reset). The SEO save (`DocumentService::flushPage()`, one flush)
  does the same before it rethrows the failure. Before, it stayed closed until your code or the kernel reset
  it.
- **Events after the unit.** The write events of a unit are dispatched once, right after its commit (before, after each
  of its flushes). A unit inside your own transaction still waits for your outermost commit.
- **doctrine/dbal 3.** The bundle now loads on doctrine/dbal 3 (DoctrineBundle 2): since 1.14 its after-commit
  middleware declared `exec(): int|string`, which DBAL 3 refuses with a fatal error. Two middlewares of the bundle
  (the after-commit outbox and transaction-doctrine's commit tracking) wrap the connection of its entities (usually
  your default connection). On doctrine/dbal 3, `Connection::commit()` then returns `null` instead of `true`. Do not
  rely on that return value. `conflict_response.commit_tracking: off` removes the commit-tracking middleware;
  `CONFLICT_002` then relies on transaction-doctrine's request mark only. doctrine/dbal 4's `commit()` returns `void`,
  so nothing changes there.
- See [CONCURRENCY.md](CONCURRENCY.md).

## To 1.16.1

From **1.16.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.16.0

From **1.15.x** (next minor, unreleased): no migration, no configuration change, nothing to do. The entities keep
their attribute mapping unless you opt in.

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- **XML mapping (opt-in, REQ-ARCH-001 rule 2).** Hosts whose entity managers load XML mapping only set
  `nowo_page_builder_kit.doctrine.mapping_type: xml`: the bundle registers
  `src/Resources/config/doctrine-xml/*.orm.xml` on `doctrine.entity_manager` (or the default manager) instead of the
  attributes. Same tables, columns, indexes and constraints, so `doctrine:migrations:diff` finds nothing.
- **Deferred to 2.0** (needs a major, see [ARCHITECTURE.md](ARCHITECTURE.md#deferred-to-20)): without
  `doctrine.entity_manager`, `doctrine.transaction: bundle` keeps flushing the manager that maps the entities (your
  default one, REQ-ARCH-001 rule 6). Set `doctrine.entity_manager` (and `transaction: host` for DDD hosts) now if the
  bundle must never commit your pending changes. The deprecated `PageBuilderAssetStorageInterface::store(UploadedFile)`
  and `GrapesTwigContextProviderInterface` are removed in 2.0.

## To 1.15.2

From **1.15.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.15.1

From **1.15.0**: no migration, no configuration change.

```bash
composer require nowo-tech/page-builder-kit-bundle:^1.15.1
```

- Only with `doctrine.entity_manager`: the bundle clears its own manager at the outermost request / message /
  command boundary only. A command you run in-process from another command, a message handler or a controller no
  longer clears it, so with `transaction: host` your pending bundle writes survive until your use case flushes.
- With `transaction: host`, write events of a use case that a nested command, a command run from a handler or a
  controller, or a request handled inside a command did not flush yet are no longer discarded by that nested
  boundary: they are dispatched after your flush. Only the outermost boundary of the request, message or command
  discards events of writes that were never flushed (with the same warning as before).
- If `doctrine.entity_manager` names your default manager, the bundle no longer clears it: clear your own managers at
  every boundary when you run without the services resetter (REQ-RUNTIME-002 rule 7).
- `Persistence\OwnEntityManagerSubscriber`, `Persistence\PageBuilderTransaction` and the new
  `Persistence\UnitOfWorkBoundaries` are `@internal`: the subscriber's constructor and the transaction's boundary
  methods changed. Do not construct or call them yourself.
- `kernel.exception` no longer discards unflushed write events: the end of the main request (`kernel.terminate`, also
  after a failure) does. A host that never calls `$kernel->terminate()` (FrankenPHP and the Symfony runtime do) keeps
  them until the next outermost main request, which discards them before it starts.

## To 1.15.0

From **1.14.x**: no migration, no configuration change. Additive (REQ-ARCH-001 rules 1 and 3); the deprecated ports
keep working until 2.0.

```bash
composer require nowo-tech/page-builder-kit-bundle:^1.15
```

**Asset storage.** `Media\PageBuilderAssetStorageInterface::store(UploadedFile)` is deprecated. Implement
`Media\PageBuilderAssetUploadStorageInterface::storeUpload(AssetUpload)`:

| Before (`UploadedFile`) | After (`AssetUpload`) |
|---|---|
| `getClientOriginalName()` | `getClientName()` (same value) |
| `getClientOriginalExtension()` | `getClientExtension()` |
| `getSize()` | `getSize()` (`null` instead of `false` when unknown) |
| finfo on `getPathname()` (+ client type / guesser fallback) | `getMimeType()` (the same resolution, done once by the bundle) |
| `getPathname()` / `fopen()` | `openStream()` (you close it) |
| `move($dir, $name)` | `moveTo($dir, $name)` (same move for an HTTP upload; `RuntimeException` instead of `FileException`) |
| `isValid()` | checked by the bundle before your storage is called (`Invalid uploaded file.`) |

- `storage: service` accepts either kind of service; the handler uses the new port when your service implements it
  (also when it implements both), otherwise the old `store()` with a runtime deprecation. The deprecated alias exists
  only when the storage implements the deprecated interface, so `lint:container` stays green.
- If you aliased `PageBuilderAssetStorageInterface` in your services, alias (or decorate)
  `PageBuilderAssetUploadStorageInterface` instead.
- The built-in local and S3 storages implement both ports and run the same checks on both (size, detected MIME
  allowlist, MIME/extension match, `..` in the name for local); their `store()` is deprecated. They take the size and
  MIME type from the file on disk, not from the VO you pass to `storeUpload()`.

**Twig context providers.** `Service\GrapesTwigContextProviderInterface::getContext(BuilderPage, string)` is
deprecated. Implement `Service\GrapesTwigPageContextProviderInterface::getPageContext(PageContext, string)` (same
tag, autoconfigured); `PageContext` carries `id`, `uuid`, `pageKey`, `status`, `publishedAt`, `title`, `slug`. A
service implementing both gets only `getPageContext()` called.

**Removed in 2.0:** `PageBuilderAssetStorageInterface` (and its alias), the storages' `store()`,
`GrapesTwigContextProviderInterface`.

## To 1.14.1

From **1.14.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.14.0

Standard hosts: **no configuration change**. The bundle keeps flushing its own admin writes on the entity manager
that maps its entities, which is your default manager when you use `auto_mapping` or the bundle mapping on it.

New and optional:

- `doctrine.entity_manager: <name>` maps the bundle entities on that manager only and makes every bundle read/write use
  it. Declare the manager under `doctrine.orm.entity_managers` (it needs no `mappings`: the bundle adds its own) and
  keep `default_entity_manager` pointing at your own default. Create or migrate the `pb_*` tables on that manager's
  connection. An unknown name now fails the container build.
- `doctrine.transaction: host` (DDD hosts): the bundle no longer flushes when your code calls its services; commit
  through your own use case / unit of work (e.g. the Messenger `doctrine_transaction` middleware). Events are
  dispatched after your commit. The bundle admin UI keeps flushing its own writes.
- Write events (`Nowo\PageBuilderKitBundle\Event\*`) carry page keys / UUIDs and scalars only. They are new:
  nothing to migrate.
- `PagePublisherInterface` is the key-based alternative to the entity-based `DocumentService` methods, which stay.

If you construct the bundle services yourself (outside the container), they accept a new optional last constructor
argument `?PageBuilderTransaction $transaction`; without it they flush as before and dispatch no events.
`PageSeoController` now receives `DocumentService` instead of `EntityManagerInterface` (internal controller).

## To 1.13.1

From **1.13.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.13.0

From **1.12.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- **FormKit is required and must be registered.** `nowo-tech/form-kit-bundle` (`^2.16`) is a `require` dependency;
  the page form types are now FormKit Types and need `Nowo\FormKitBundle\NowoFormKitBundle` in `config/bundles.php`
  (FormKit injects its option merger into them). Without it, building a page form fails.
- **Form theming goes through the FormKit profile.** Field classes / translation domain of the bundle forms come from
  `nowo_form_kit.profiles.page_builder_kit`. A host that customised them (e.g. through `twig.form_themes` blocks that
  targeted these forms) should define that profile instead — see
  [CONFIGURATION](CONFIGURATION.md#web_ui). Field names, CSRF field names and token ids did not change, so template
  overrides and controllers keep working.

## To 1.12.1

From **1.12.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

No action needed when you import `@NowoPageBuilderKitBundle/Resources/config/routing.yaml` (the documented setup). If on Symfony >= 7.3 the page builder routes appeared without that import (through the default `controllers: { resource: routing.controllers }` of `config/routes.yaml`), they are gone now: import the bundle routes explicitly (see [INSTALLATION](INSTALLATION.md)).

## To 1.12.0

From **1.11.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

From **1.11.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle nowo-tech/ui-kit-bundle
php bin/console assets:install
```

Requires `nowo-tech/ui-kit-bundle` **^2.17.3** (and `twig/twig` ^3.19). No database change.

### Security (Appendix BO)

- **Upgrade if you print `nowo_page_builder_hydrate_binds()`** (M1, stored XSS against content editors in
  `>= 1.5.0`). Drop any `|raw` after it: the output is safe HTML. The value handed to the inline editor is now the slot
  text for non-HTML field types (entities decoded once) and the slot markup for `html` / `richtext` / `raw` fields.
- The public output no longer contains HTML comments, `form=` / `xml:base` attributes or Alpine / htmx / Vue /
  AngularJS / Turbo attributes (`x-*`, `:*`, `@*`, `hx-*`, `v-*`, `ng-*`, `data-turbo*`) authored in the editor. If a
  page relied on such attributes in GrapesJS HTML, move that behaviour into the host template.
- Saved GrapesJS HTML loses comments, `<style>`, `<math>`, `<template>`, SVG animation elements, `srcdoc`,
  `xml:base` and `vbscript:` / non-image `data:` links on the next save (they were already removed on output).
- `html.sanitize.strategy: service` now requires `html.sanitize.service` (compile error otherwise).
- Asset uploads must send the CSRF token in the `X-CSRF-TOKEN` header or the POST body (`?_token=` is ignored); the
  bundle's canvas and content page already do.
- Optional: set `grapesjs.module_base_url` to a self-hosted ESM mirror to remove the esm.sh dependency.

### Layout

- `web_ui.layout_template` now defaults to `null`: the admin pages render inside `nowo_ui_kit.layouts.admin`. Point
  that UiKit option at your admin layout (or a one-file bridge using `@NowoUiKit/layout/_bridge.html.twig`) once for
  every Nowo bundle. The 1.x default value `@NowoPageBuilderKitBundle/admin/layout.html.twig` is treated as `null`.
- A custom `web_ui.layout_template` that renders the UiKit canonical blocks (`nowo_ui_page_header`,
  `nowo_ui_content`, …) is used as is. A 1.x layout rendering `{% block admin %}` still works through the deprecated
  bridge (a deprecation is triggered); migrate it before 2.0.
- The bundle no longer loads Bootstrap from a CDN: your admin layout loads the CSS framework (UiKit `nowo-ui.css`
  and its theme slots style the pages without one).

### Template contract

- The admin pages extend UiKit scaffolds; see [TEMPLATES](TEMPLATES.md) for each page, its view model and data blocks.
  Theme them through `nowo_ui_kit.theme` (classes, attributes, tokens; `theme.bundles.NowoPageBuilderKitBundle`) instead
  of copying templates.
- Full-file copies of `admin/pages/*.html.twig` (or `admin/layout.html.twig`) under
  `templates/bundles/NowoPageBuilderKitBundle/` are frozen forks: the variable `page` is now the UiKit view model and
  the `BuilderPage` entity is `builder_page`; the blocks `page_title`, `header_actions` and `admin` only exist in the
  deprecated layout. Prefer `web_ui.templates.<page>` with a thin `{% extends '@!NowoPageBuilderKitBundle/admin/pages/<page>.html.twig' %}`
  overriding one data block. Copies that still extend `nowo_page_builder_layout_template()` keep rendering inside the
  UiKit admin layout until 2.0.
- Confirmations use the UiKit `<dialog>` (loaded by the UiKit `scripts` partial of your layout); forms marked
  `data-pbk-confirm` in a copied template are no longer intercepted.
- Hosts relying on the Bootstrap classes the form types used to add (`form-control`, `form-select`, `mb-0`, …) get them
  from their form theme / FormKit profile now.

### Configuration

- `web_ui.css_framework` is deprecated: remove it. If you relied on it for the GrapesJS canvas iframe styles, list
  them in `grapesjs.canvas_styles`.
- New `web_ui.templates.<page>` (optional).

### Front-end

- `page-builder-admin.js` now powers the image upload and media library of the content page (no inline script); make
  sure your CSP allows the bundle asset package. The canvas loads `css/grapes.min.css` / `css/grapick.min.css` from the
  bundle (run `assets:install`); the GrapesJS scripts are still fetched at run time from `grapesjs.cdn_version` on esm.sh.

## To 1.11.1

From **1.11.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- Pages saved earlier are cleaned when the canvas opens them; saving rewrites the stored `grapes` JSON. Event handlers or `script` components in the canvas project are dropped unless `grapesjs.allow_scripts: true`.
- `PageCanvasController` takes `GrapesDocumentSanitizer` as a new constructor argument (autowired).

## To 1.11.0

From **1.10.2**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- Hosts that split roles: importing a page now needs `layout_roles` (and `publish_roles` when `publish: true`) in addition to `templates_roles`. With the defaults (every list `ROLE_EDITOR`) nothing changes.
- `framework.csrf_protection` and `framework.form.csrf_protection` must stay enabled while the admin routes are registered; otherwise the container build fails with a `LogicException`.
- `PageDocumentApiController` takes `PageBuilderKitAccessGuard` as a new constructor argument (autowired; only matters if you construct it by hand).

## To 1.10.2

From **1.10.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.10.1

From **1.10.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.10.0

From **1.9.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

**Behaviour change (security):** an empty role list under `nowo_page_builder_kit.security` now **denies**.

- `layout_roles: []`, `content_roles: []`, `publish_roles: []`, `templates_roles: []` no longer open that capability to
  everybody. If you relied on it, list the roles explicitly (e.g. `[ROLE_USER]`) or move them to `access_roles`.
- `access_roles: []` together with every `*_roles: []` (no `access_checker`, `allow_unauthenticated: false`) now fails
  at container compile time with a `LogicException`. Configure at least one role, set `security.access_checker`, or —
  only for a trusted demo — `security.allow_unauthenticated: true`.
- Default configuration (`[ROLE_EDITOR]` everywhere) is unaffected.

## To 1.9.1

From **1.9.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## To 1.9.0

From **1.8.1**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- The admin page list now shows 50 pages per screen (`?page=N`). If you override `admin/pages/index.html.twig`, render
  the new `current_page` / `total_pages` variables (see the bundle template) or pages beyond the first 50 are hidden.
- New runtime dependencies `psr/clock` and `psr/log` (interfaces only).

## To 1.8.1

From **1.8.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

## 1.8.0

From **1.7.0** — unused doctrine.connection option deprecated.

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- `doctrine.connection` is deprecated and ignored (it never selected a connection). Remove it from your config; to put the bundle tables on another connection, add the bundle mapping to that entity manager in `doctrine.orm.entity_managers`.

## 1.7.0

From **1.6.1** — security: hardened asset uploads, safe redirects, asset library capability.

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- SVG uploads are no longer allowed by default; add `image/svg+xml` to `assets_upload.allowed_mime` to re-enable them (stored-XSS risk, see SECURITY.md).
- Uploads whose extension does not match the detected type (e.g. a PNG named `.jpg`) are rejected; files are stored with the canonical extension (`.jpeg` → `.jpg`).
- Users without the Layout capability can no longer list the media library; `_redirect` values starting with `//` or `/\` are ignored.

## 1.6.1

From **1.6.0** — dependency refresh and repository maintenance.

```bash
composer update nowo-tech/page-builder-kit-bundle
```

- No breaking changes. No application upgrade steps.

## 1.6.0

From **1.5.1** (suggested **1.6.0**):

```bash
composer update nowo-tech/page-builder-kit-bundle
php bin/console assets:install   # new js/page-builder-admin.js + rebuilt page-builder-inline-edit.js
```

1. **No breaking API changes**, but public output is stricter. The new always-on `PublicHtmlHardener` (requires PHP 8.4 `Dom\HTMLDocument` — already the bundle minimum, no fallback needed) removes from Grapes HTML, `/p/{pageKey}` and classic `text` / `html` widgets: `<style>`, `<form>`, `<math>`, `<template>`, `<noscript>`, SVG `<animate>` / `<set>` / `<foreignObject>`, `action` / `formaction` / `srcdoc` attributes and non-image `data:` URLs — even with `html.sanitize.strategy: none`. Put page CSS in the Grapes style manager (`page_tree.css`), not in `<style>` blocks inside HTML. `grapesjs.allow_scripts: true` still keeps `<script>` elements.
2. **Twig fallback**: when Grapes Twig fails (`twigError` is set) or `grapesjs.twig.enabled` is false, tokens are now printed as encoded text (`{{ &quot;x&quot; }}` stays escaped) instead of the decoded source. Fix the template error rather than relying on the fallback.
3. **Host template overrides** of `public/page.html.twig`, `widgets/text.html.twig`, `widgets/html.html.twig` or `public/_editable_field.html.twig` that print editor HTML with `|raw` should switch to `|pbk_harden_html` (and Grapes CSS to `|pbk_harden_css`). If your app registered its own `pbk_harden_html` / `pbk_harden_css` Twig filters, remove them (the kit now provides them; duplicate names would shadow one another).
4. **CSP**: set the request attribute `csp_nonce` (e.g. in a `kernel.request` listener that also emits the `Content-Security-Policy` header) and the kit's `<script>` / `<style>` tags will carry it. Overrides of `admin/pages/templates.html.twig` / `revisions.html.twig` should use `attr: {'data-pbk-confirm': '...'|trans}` and load `js/page-builder-admin.js` instead of inline `onsubmit`.
5. Callers of `GrapesDocumentSanitizer::sanitizeHtml()` that print the result should pass `false` as the second argument.

See [CHANGELOG.md](CHANGELOG.md#unreleased).

## 1.5.1

From **1.5.0**:

```bash
composer update nowo-tech/page-builder-kit-bundle
```

1. No breaking changes. **No application upgrade steps.**
2. The default `allowlist` sanitizer is stricter: elements carrying `id="pbk-root"` are now sanitized like any other, and `href` / `src` values with `javascript:` / `vbscript:` schemes (also when split by tabs, newlines or control characters) or non-image `data:` URLs are dropped. `data:image/{png,jpeg,gif,webp,avif}` remains allowed on `img[src]`.

See [CHANGELOG.md](CHANGELOG.md#151---2026-10-09).

## 1.5.0

From **1.4.4**:

1. Optional — replace host copies of the bind hydrator / section filter / status query:
   - `PbkBindHydrator` → inject `Nowo\PageBuilderKitBundle\Service\PublicBindHydratorInterface` and call `hydrate($html, $pageKey)` (the Twig `Environment` is now injected; it is no longer a method argument), or use the Twig function `nowo_page_builder_hydrate_binds(html, pageKey)`.
   - `PbkSectionFilter` → `Nowo\PageBuilderKitBundle\Util\ContentSectionFilter::sanitize()` / `filterSchema()`, or Twig `nowo_page_builder_sanitize_section` / `nowo_page_builder_filter_schema_by_section`.
   - `BuilderPageStatusQuery::find()` → `Nowo\PageBuilderKitBundle\Repository\BuilderPageStatusQueryInterface::findStatusByPageKey()`.
2. Required for bind pencils to show the right type/label — implement `Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface::definitions(string $pageKey)` (return `list<array{key, type, label}>`; `label` is a translation id) and alias it in the host:

   ```yaml
   Nowo\PageBuilderKitBundle\Content\ContentFieldDefinitionProviderInterface:
       alias: App\Site\PageBuilder\ClinicContentFieldDefinitionProvider
   ```

   Without it, binds fall back to type `string` and the field key as label.
3. If you construct `PageBuilderKitExtension` manually, note the new optional last argument `?PublicBindHydratorInterface`.
4. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#150---2026-10-07) and [USAGE.md](USAGE.md#public-bind-hydration-section-filter-and-status-query).

## 1.4.4

From **1.4.3**:

1. Optional — after rendering Grapes HTML, inject `Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer` and call `normalize()` so Nu Html Checker does not fail on DOMDocument `</source>` wrappers or skeleton images without `src`.
2. Optional — bind constructor `$webpPictureUpgrades` (`png` / `webp` / `classContains`) only for host-owned PNG→WebP pairs. Do not put clinic-specific paths in the kit.
3. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#144---2026-10-06) and [USAGE.md](USAGE.md#public-html-cleanup).

## 1.4.3

From **1.4.2**:

1. No host action required for Form Twig remediation (admin/demo templates only).
2. Optional — rebuild inline-edit assets (`pnpm run build:inline-edit`) if you vendor `src/Resources/public/js/page-builder-inline-edit.js`.
3. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#143---2026-10-02).

## 1.4.2

From **1.4.1**:

1. Optional — copy [`examples/acme-grapes-block-pack/`](../examples/acme-grapes-block-pack/) as a starting Composer Grapes block pack (see [COOKBOOK.md](COOKBOOK.md#ship-a-grapes-block-pack)).
2. Optional — rebuild canvas assets (`pnpm install && pnpm run build` at the bundle root) if you vendor `src/Resources/public/js/page-builder-canvas.js` (canvas is now fully typechecked without `@ts-nocheck`).
3. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#142---2026-10-02).

## 1.4.1

From **1.4.0**:

1. Optional — read [COOKBOOK.md](COOKBOOK.md) for Grapes block packs and template sharing.
2. Optional — rebuild canvas assets (`pnpm install && pnpm run build` at the bundle root) if you vendor `src/Resources/public/js/page-builder-canvas.js`.
3. Clear Symfony cache after deploy (admin legacy banners / translations).

See [CHANGELOG.md](CHANGELOG.md#141---2026-10-02).

## 1.4.0

From **1.3.0**:

1. Optional — split editor roles with `layout_roles` / `content_roles` / `publish_roles` / `templates_roles` (BlogKit-style). `access_roles` remains a shortcut that grants every capability. Alternatively set `security.access_checker` to a custom `PageBuilderKitAccessCheckerInterface` (guard). Controllers can inject `PageBuilderKitAccessGuard`.
2. Optional — define typed content fields on a page (`/admin/page-builder/pages/{pageKey}/content`) and reference them in Grapes HTML as `{{ fields.your_key }}` or slots `[[fields.your_key]]`.
3. Custom `PageBuilderKitAccessCheckerInterface` implementations must expose `canLayout()` / `canContent()` / `canPublish()` / `canTemplates()` (+ `canAccess()` / `can()`).
4. Optional — template apply checkbox / JSON `include_seo: true` to copy SEO meta from the template snapshot (default remains **false**).
5. Optional — media library `GET {path_prefix}/assets` (route `admin_page_builder_assets_list`) when uploads are enabled.
6. Frontend hosts that vendor bundle JS: rebuild with `pnpm install && pnpm run build` at the bundle root (Vite). Demo uses Pentatrion Vite.
7. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#140---2026-10-02).

## 1.3.0

From **1.2.0**:

1. Default `html.sanitize.strategy` is **`allowlist`**. Existing installs that relied on unsanitized Grapes/widget HTML must set `strategy: none` explicitly (trusted editors only).
2. Clear Symfony cache after deploy.

## 1.2.0

From **1.1.x**:

1. Bump to `^1.2` (or `^1.1` if you stay on the previous minor) and run `composer update nowo-tech/page-builder-kit-bundle`.
2. **No** Doctrine schema changes or breaking config/route renames.
3. Clear Symfony cache after deploy (canvas JS / CSS assets and new admin template routes).
4. Optional — **template sharing:** use admin Export / Import on `/templates`, or `PageTemplateService::export` / `import` (`formatVersion: 1`, kinds `page_builder_template` / `page_builder_templates`).
5. Optional — **Grapes block packs:** implement `GrapesBlockPackInterface` (tag `nowo_page_builder_kit.grapes_block_pack`). Blocks appear in the canvas BlockManager via `blockPacks`. See [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md#grapesjs-block-packs).
6. Revision **diff** UI now includes side-by-side HTML/CSS (or classic JSON) panels — no host action required.
7. Web Profiler panel icon is a layout SVG (label remains “Page Builder”).

See [CHANGELOG.md](CHANGELOG.md#120---2026-09-27).

## 1.1.1

From **1.1.0**:

1. Optional: bump to `^1.1.1` (or stay on `^1.1`) and run `composer update nowo-tech/page-builder-kit-bundle`.
2. **No** Doctrine schema changes, config keys, or route renames.
3. Spec Kit artifacts under `specs/002`–`004` document Phases 2–4 already shipped in 1.0/1.1 — useful for contributors only.

See [CHANGELOG.md](CHANGELOG.md#111---2026-09-27).

## 1.1.0

From **1.0.0**:

1. Bump the package constraint to `^1.1` and run `composer update nowo-tech/page-builder-kit-bundle`.
2. Ensure `doctrine/orm` is `^3.7` (required for `SortDirection` in association `OrderBy` / QueryBuilder).
3. Run Doctrine schema update / migration for new table `pb_page_template` (`BuilderPageTemplate`).
4. `/p/{pageKey}` now renders **drafts** for users who pass the access checker (sticky banner). Anonymous visitors still receive 404 for drafts — review if you relied on “always 404 while draft” for logged-in editors.
5. Optional: keep `debug.collector: true` (default) in `dev` for the Web Profiler **Page Builder** panel; set `false` to disable.
6. New admin routes: templates, duplicate, export/import, revision diff — no breaking route renames.
7. Clear Symfony cache after deploy.

See [CHANGELOG.md](CHANGELOG.md#110---2026-09-27) and [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md).

## 1.0.0 — first release

There is no prior published version. To adopt the bundle:

```bash
composer require nowo-tech/page-builder-kit-bundle:^1.0
composer require twig/extra-bundle twig/string-extra
```

1. Register the bundle and import `@NowoPageBuilderKitBundle/Resources/config/routing.yaml`.
2. Add `config/packages/nowo_page_builder_kit.yaml` (see [CONFIGURATION.md](CONFIGURATION.md)).
3. Run Doctrine migrations (or `doctrine:schema:update` in development) for `pb_*` entities.
4. Configure Symfony Security so editors can reach `web_ui.path_prefix` (default `/admin/page-builder`).
5. Open admin at `{path_prefix}/pages` → create a page → GrapesJS canvas.

### Recommended first config

```yaml
nowo_page_builder_kit:
    default_locale: es
    locales: [es, en]
    security:
        access_roles: [ROLE_EDITOR]
        allow_unauthenticated: false
    web_ui:
        path_prefix: /admin/page-builder
        css_framework: bootstrap5
    doctrine:
        table_prefix: ''          # e.g. 'app_' in shared DBs
    revisions:
        enabled: false            # set true to keep document history
    debug:
        collector: true           # Web Profiler Page Builder panel when kernel.debug
    grapesjs:
        enabled: true
        allow_scripts: false
        twig:
            enabled: true
        assets_upload:
            enabled: false
```

### Public pages and i18n routes

- Bundle convenience route: `/p/{pageKey}` (published for everyone; draft preview for editors since 1.1.0 — see above).
- Production sites should map host routes → `pageKey` + locale (same path + locale **or** different paths per language). See [USAGE.md](USAGE.md#associating-pages-with-public-routes-i18n).

### Optional demo

```bash
make -C demo/symfony8 up
# http://localhost:8137  — login admin/admin
```

Future upgrades will be documented in this file with version-specific steps. See [CHANGELOG.md](CHANGELOG.md).
