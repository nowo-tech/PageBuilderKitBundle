# Concurrency

How the bundle stays correct when several editors, or an editor and your own code, write the same page at the same
time (nowo-tech REQ-RUNTIME-003). This page lists the write paths, the conflict answers and codes, the host options and
the residual risks. Worker mode (FrankenPHP, kernel reset off) is covered in
[FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Table of contents

- [Rules the bundle follows](#rules-the-bundle-follows)
- [Write paths](#write-paths)
- [Conflict answers and codes](#conflict-answers-and-codes)
- [What is not a conflict](#what-is-not-a-conflict)
- [Host options](#host-options)
- [Retries](#retries)
- [Tests and CI](#tests-and-ci)
- [Residual risks](#residual-risks)

## Rules the bundle follows

- **One transaction per write unit.** Every bundle write that reads before it writes, or writes several rows, runs
  through `PageBuilderTransaction::run()`: one transaction of nowo-tech/transaction-doctrine's runner on the bundle's
  manager. The flushes of the unit (a revision snapshot, its pruning, the document) happen inside it, so a failure
  stores nothing of the unit. A unit started inside another one (a duplicate calls create, save and the SEO flush)
  joins it.
- **The page row lock.** A unit that changes a page first calls `PageBuilderTransaction::lockPage()`: it takes the
  page's document row with `SELECT … FOR UPDATE` and reads the document and its locale rows again under the lock. The
  page row is locked instead when the page has no document yet. Two editors who change the same page therefore run one
  after the other, and the second one starts from what the first one committed.
- **Your entity manager is never cleared or reset while it is open.** Without `doctrine.entity_manager`, the bundle
  writes on the manager that maps its entities (usually your default one) with
  `DoctrineTransactionRunner::forHostEntityManager()`:
  - an open manager is never closed, cleared or reset;
  - a manager that a failed flush closed is replaced through the registry;
  - the runner refuses to start (`LogicException`) while your manager holds unflushed changes.

  With `doctrine.entity_manager` set to a manager of its own, the bundle uses `forEntityManager()`, which may close and
  reset that manager after a failed unit. `OwnEntityManagerSubscriber` clears it at the request, message and command
  boundaries.
- **`doctrine.transaction: host` (DDD hosts).** When your code calls the bundle, your use case owns the transaction:
  `run()` runs the operation as is and the bundle never flushes. `lockPage()` takes the lock only when your
  transaction is open. The bundle's own admin controllers are a use case of their own and still run their units.
- **Side effects after the real commit.** The write events (`PageCreatedEvent`, `PageDocumentSavedEvent`,
  `PagePublishedEvent` and the others) wait in the `PageBuilderOutbox` for the outermost commit of the bundle's
  connection. A rollback drops them. They are dispatched right after the unit commits.
- **No I/O under the lock.** The structure is normalised, sanitised and validated before the unit starts. Asset uploads
  are not part of any unit.
- **FrankenPHP worker mode with kernel reset off.** The services hold no request state between requests. The commit
  ledger of the conflict answer is a monotonic counter of the process, read by difference only. The run depth of
  `PageBuilderTransaction` returns to 0 when a unit ends and is zeroed by `reset()`.

## Write paths

| Write path | Transaction | Control | Conflict answer |
| --- | --- | --- | --- |
| Save the editor document (`POST /pages/{pageKey}/document`, `DocumentService::saveDocument()`, also `saveDocumentByKey()`) | One unit: revision snapshot, pruning and document | Page row lock, document read again (the snapshot records what this save replaces) | JSON 409 `CONFLICT_001` / `CONFLICT_002` |
| Content field from the canvas (`POST /pages/{pageKey}/fields/{fieldKey}`, `ContentFieldsService::saveFieldValue()`) | One unit | Page row lock, structure read again: two editors saving different fields keep both values | JSON 409 `CONFLICT_001` / `CONFLICT_002` |
| Content form (`PageContentController`, `ContentFieldsService::mergeValues()`) | One unit | Page row lock; only the editor's intent (the posted locales and keys, unchecked bools of those locales, label edits of the active locale) is merged onto the values and schema read under the lock, so another editor's other locale or field survives | 409 `CONFLICT_001` / `CONFLICT_002` (your error page) |
| Content schema (`PageContentController`, `ContentFieldsService::saveSchemaWithField()` / `saveSchemaWithoutField()`) | One unit | Page row lock; one add or remove applied to the schema read under the lock (a field another editor added meanwhile stays) | 409 `CONFLICT_001` / `CONFLICT_002` (your error page) |
| Your code: `saveValues()` / `saveSchema()` | One unit | Page row lock; they replace the values / schema with what you pass (whole replacement: build it from a fresh read, or use the intent methods above) | `TransientStorageFailure` |
| Classic sections editor (legacy, `PageSectionsController`) | One unit (`saveDocument()`) | As the document save, but the editor posts the whole document built from its earlier read (see residual risks) | 409 (your error page) |
| Publish / unpublish (`POST /pages/{pageKey}/publish`, `/unpublish`, `publishByKey()`, `unpublishByKey()`) | One unit | Page row lock: the required-field check and the publish revision read the content being published | 409 (JSON when asked, else your error page) |
| Create a page (`PageListController`, `createPage()`, `createPageByKey()`) | One unit | Unique `page_key`: two editors creating the same key get one page; the second gets "Page key … already exists" | Domain error (form error / flash, 400 in JSON) |
| Duplicate a page (`POST /pages/{pageKey}/duplicate`) | One unit: create, copy, translations | Unique `page_key` as above | 409 `CONFLICT_*`, or the domain error |
| Import a page (`POST /pages/import`, `DocumentImportExportService::import()`) | One unit: create or load, document, translations, publish | Page row lock through the save; unique `page_key` | 409 `CONFLICT_*`, or the domain error |
| SEO and translations (`PageSeoController`, `DocumentService::flushPage()`) | One ORM flush (the form changed the entities before the call). A failed flush closes the manager; `flushPage()` replaces a closed one through the registry before rethrowing, so a kept-alive worker keeps writing | Doctrine writes the changed columns only; unique `(page, locale)` translation key | 409 `CONFLICT_*`; a duplicate translation answers 409 `PAGE_BUILDER_CONCURRENT_EDIT` |
| Revisions: create, restore (`PageRevisionsController`, `PageRevisionService`) | One unit: the "Before restore" snapshot and the restore together | Page row lock | 409 (your error page) |
| Templates: save from page, create a page from a template, delete, import (`PageTemplatesController`, `PageTemplateService`) | One unit each; an import of several templates is one unit | Unique `template_key`: the second editor saving the same new key gets "Template … was created by another editor at the same time" | Domain error, or 409 `CONFLICT_*` |

`flush()` in `src/`: `PageBuilderTransaction::commit()` (inside a unit, or the single flush of `flushPage()`), the
runner's own flush before the commit, and the services' fallback when they are built without
`PageBuilderTransaction` (unit tests only; the container always injects it).

## Conflict answers and codes

On the routes of the bundle (`PageBuilderConflictListener`, controllers of the bundle only; your own routes keep your
error handling):

| Code | When | Status | `Retry-After` | Client should |
| --- | --- | --- | --- | --- |
| `CONFLICT_001` | Deadlock, lock wait timeout or serialization failure (MySQL 1213 / 1205, MariaDB 1020, PostgreSQL 40001 / 40P01 / 55P03; a `TransientStorageFailure` of the runner or a DBAL error anywhere in the chain), and nothing committed since the request started | 409 (`conflict_response.status`) | Yes: `conflict_response.retry_after`, else 1 s | Retry |
| `CONFLICT_002` | The same after a commit earlier in the request: transaction-doctrine's request mark (`ConflictCode::forRequest()`, when its bundle is registered with commit detection), or the bundle's own mark on a `CommitLedger` of its connection. Also a `ConcurrencyConflict` that committed, or a lost savepoint with no deadlock behind it (outcome unknown) | 409 (`conflict_response.status`) | No | Reload, do not repeat blindly |
| `PAGE_BUILDER_CONCURRENT_EDIT` (`X-Error-Code`) | Two editors added the same missing translation of a page (or locale row of its document) at once; the unique key refused the second | 409 | No | Reload the page and apply the change again |

- The `CONFLICT_*` code is in the `X-Conflict-Code` header, which only ever holds `CONFLICT_001` or `CONFLICT_002`.
  The domain answer has no `X-Conflict-Code`.
- **JSON requests** (the editor's calls: a JSON body, or `Accept: application/json`) keep the bundle's JSON error
  shape, with the code added:

  ```json
  {"error": "conflict", "code": "CONFLICT_001", "message": "Another change to this page is being saved. Retry in a moment."}
  ```

  The domain conflict answers `{"error": "concurrent_edit", "code": "PAGE_BUILDER_CONCURRENT_EDIT", "message": "…"}`.
- **Admin pages** are rendered by your error page: the answer is an `HttpException` with a fixed, client-safe message.
  The storage error stays the previous exception for your logs; no SQL, parameter or value reaches the client.
- The listener runs at `kernel.exception` priority 64, before the framework's error listener logs the exception, so
  an expected conflict is not logged as a critical error.
- If your admin is served cross-origin, list `X-Conflict-Code` and `Retry-After` in `Access-Control-Expose-Headers`.
- The codes are `Nowo\TransactionDoctrine\ConflictCode` values.

## What is not a conflict

- **Unique constraint violations** are not lock conflicts:
  - page key and template key: the "only one" races are caught where they happen and answered with the domain error
    of the check that ran before;
  - page translation `(page, locale)` and document locale `(document, locale)`: the domain 409 above.

  Anywhere else (your code) a violation stays a real error.
- **Lost connections** are never mapped: the outcome of the write is unknown and nothing says it was a lock.
- Validation errors stay form errors (422) or `400 {"error": "…"}` in JSON.

## Host options

```yaml
nowo_page_builder_kit:
    conflict_response:
        status: null        # 409 (default) or 503
        retry_after: null   # seconds for CONFLICT_001
        commit_tracking: bundle   # bundle | off
```

- When both are unset (null), the bundle follows `nowo_transaction_doctrine.conflict_response` if
  nowo-tech/transaction-doctrine's Symfony bundle is registered, so the whole application answers the same. Otherwise
  it answers 409 with `Retry-After: 1`.
- `commit_tracking: off` removes the bundle's commit-tracking middleware. `CONFLICT_002` then relies on
  transaction-doctrine's request mark only. On doctrine/dbal 3 a wrapped `Connection::commit()` returns `null`
  instead of `true` (see [UPGRADING](UPGRADING.md)).
- `status: 503` is for proxies or monitors that treat 503 as retryable. The codes and the `Retry-After` rule stay the
  same.
- `doctrine.entity_manager`: give the bundle its own manager (see [CONFIGURATION](CONFIGURATION.md#doctrine)) if your
  requests may hold unflushed changes when they call the bundle (the host runner refuses them). **Required** when a
  listener of your manager changes entities after loading them (a `postLoad` decryption such as
  nowo-tech/doctrine-encrypt-bundle, or any `postLoad` mutation): the loaded entities then look changed, and every
  bundle write on your manager fails with `LogicException` before it starts.
- `doctrine.transaction: host`: your use case owns the transaction. Open it before you call the bundle if you want the
  page lock.
- doctrine/dbal 3 (DoctrineBundle 2): set `doctrine.dbal.use_savepoints: true`, so a unit that runs inside your
  own transaction is a savepoint (DBAL 4 always nests with savepoints). Without it, a failed nested unit marks your
  whole transaction rollback-only.
- Database settings: READ COMMITTED and a short `innodb_lock_wait_timeout` / `lock_timeout` (a few seconds) for HTTP.
  The wait is bounded by the database, not by the bundle.

## Retries

- HTTP writes make **one attempt**: the editor (or your client) retries after a `CONFLICT_001`.
- The runner makes one attempt too (no replay). A retried unit loads and locks the page again, so it never applies a
  stale read.
- The bundle has no Messenger handlers and no console command that writes. Host code that calls `PagePublisherInterface`
  from a handler gets a `TransientStorageFailure` it can retry: every key-based call is one unit, and a retried
  `createPageByKey()` gets "already exists" when the first attempt committed.

## Tests and CI

- `tests/Integration/Database/PageBuilderConcurrencyDatabaseTest.php` runs against real servers. Each session bounds its
  lock waits with `innodb_lock_wait_timeout` / `lock_timeout`. The second session is a plain connection, or a child
  process holding its transaction open:
  - a document save that meets another session's row lock answers 409 + `Retry-After` + `CONFLICT_001` in the JSON
    shape, stores nothing, and the retry on the same connection saves;
  - a publish that meets the lock answers the configured status (503) with the code and the configured `Retry-After`,
    and the page stays a draft;
  - two editors saving different content fields at once (two processes): the second waits, reads again and both values
    are stored;
  - a field save from a stale read keeps the value another editor committed;
  - the content form of one locale posted while another editor's save of another locale holds the lock (two
    processes): both values are kept, the unchecked bool is false and the label edit applied;
  - a schema add posted while another editor's save adds a field inline (two processes): both fields are kept, and a
    remove leaves the others;
  - two creates of one page key at once (two processes): one page, the second gets the domain error, the manager is
    usable afterwards;
  - a lock conflict after a committed unit of the same request answers `CONFLICT_002` without `Retry-After`. The commit
    is counted by the real commit-tracking middleware.
- `tests/Integration/Persistence/PageBuilderWriteUnitTest.php` covers the unit itself on SQLite: one commit, events after
  it, a nested unit joining the outer one, a failed unit storing nothing, the host runner refusing unflushed changes,
  the lock re-read, the page-key and template-key races, and a failed SEO flush leaving an open manager.
- `tests/Unit/EventListener/PageBuilderConflictListenerTest.php` maps every case:
  - `CONFLICT_001` for each vendor code;
  - `CONFLICT_002` (own ledger, transaction-doctrine's mark, a committed `ConcurrencyConflict`, a lost savepoint);
  - 503, the runner's hint, the JSON shape and the duplicate translation;
  - lost connections and other errors left alone, and host routes untouched.
- The CI job **Real databases (MySQL 8.4, MariaDB 11.4, PostgreSQL 16)** runs the `database` group on DBAL 3 and 4
  with `--fail-on-skipped`. **Dependency floor** runs PHPStan and the suites with the lowest
  `nowo-tech/transaction-*` versions allowed and checks the demo lock. Both are required checks of `main`.

```bash
make test-with-db   # starts the "databases" compose profile and runs the group
make down-db
```

## Residual risks

- **A stale editor overwrites the document whole.** The canvas sends the whole document. When editor B saves a
  document they loaded before editor A's save, B's document replaces A's, without a warning (no version token in the
  editor). A's content is not lost: the revision snapshot of B's save (when `revisions.on_save` is on) records it.
  This is an admin-only path. A version column needs a schema change and is left for a later minor.
- **The classic sections editor (legacy) posts the whole document.** It builds the props of every locale from the
  page it read when the form was shown; a save of another locale by another editor in the meantime is overwritten
  (the revision snapshot of the save keeps it when `revisions.on_save` is on). Use the canvas and the content form,
  which merge under the lock.
- **SEO and translation forms: last write wins per field.** Doctrine writes only the changed columns, so two editors
  changing different fields both keep them. The same field: the later save wins.
- **Commit detection covers the bundle's connection** (and every connection when transaction-doctrine's
  `commit_detection` is on). A write of yours on another connection before the bundle's controller fails is not
  counted without transaction-doctrine's bundle.
- **A manager resolved by class only.** Without `doctrine.entity_manager`, a closed manager is replaced through the
  registry by its default name. If you map the bundle on a manager other than your default one, set
  `doctrine.entity_manager` so that the right one is replaced.
- **Locking reads under MySQL / MariaDB REPEATABLE READ** lock by the primary key of `pb_document` (or `pb_page`), one
  row per page. No gap lock on a hot table.
