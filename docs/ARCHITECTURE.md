# Architecture

Mermaid diagrams for **Page Builder Kit Bundle** (rendered by GitHub, many IDEs, and Spec Kit docs viewers), plus the ports, SPI and events hosts use and the standard Symfony and DDD host paths (REQ-ARCH-001).

## Table of contents

- [System overview](#system-overview)
- [Doctrine model](#doctrine-model)
- [Document schemas](#document-schemas)
  - [Classic schema v1 (sections → columns → widgets)](#classic-schema-v1-sections--columns--widgets)
  - [GrapesJS schema v2](#grapesjs-schema-v2)
- [Admin edit → public render](#admin-edit--public-render)
- [Draft / publish lifecycle](#draft--publish-lifecycle)
- [Revisions](#revisions)
- [GrapesJS Twig pipeline](#grapesjs-twig-pipeline)
- [Security](#security)
- [Asset upload](#asset-upload)
- [Public routing & i18n](#public-routing--i18n)
- [Ports, SPI and events](#ports-spi-and-events)
- [Standard Symfony and DDD hosts](#standard-symfony-and-ddd-hosts)
  - [Standard Symfony host](#standard-symfony-host)
  - [DDD / hexagonal host](#ddd--hexagonal-host)
- [REQ-ARCH-001 status](#req-arch-001-status)
- [Deferred to 2.0](#deferred-to-20)
- [Related docs](#related-docs)

---

## System overview

```mermaid
flowchart TB
    subgraph Host["Host Symfony app"]
        Routes["routing.yaml"]
        Config["nowo_page_builder_kit.yaml"]
        TwigHost["Twig templates / pencil include"]
        Ctx["GrapesTwigContextProviderInterface"]
        Access["PageBuilderKitAccessCheckerInterface"]
    end

    subgraph Bundle["PageBuilderKitBundle"]
        AdminUI["Admin UI under web_ui.path_prefix<br/>list · canvas · sections · SEO · versions"]
        API["Document API under same prefix<br/>GET/POST document · publish · unpublish"]
        DocSvc["DocumentService"]
        RevStore["PageRevisionStore"]
        Render["PageRenderProvider"]
        TwigExt["nowo_page_builder_render()<br/>nowo_page_builder_can_edit()"]
        GrapesSan["GrapesDocumentSanitizer"]
        GrapesTwig["GrapesTwigRenderer"]
        SEO["PageSeoBuilder"]
    end

    subgraph Data["Doctrine"]
        Page["BuilderPage"]
        Doc["BuilderDocument"]
        Loc["BuilderDocumentLocale"]
        Tr["BuilderPageTranslation"]
        Rev["BuilderPageRevision"]
    end

    Public["Public /p/{pageKey}<br/>or host Twig"]

    Routes --> AdminUI
    Routes --> API
    Routes --> Public
    Config --> Bundle
    AdminUI --> API
    API --> DocSvc
    DocSvc --> RevStore
    DocSvc --> Page
    DocSvc --> Doc
    DocSvc --> Loc
    Page --> Tr
    Page --> Rev
    Public --> Render
    TwigHost --> TwigExt
    TwigExt --> Render
    Render --> GrapesSan
    Render --> GrapesTwig
    Render --> SEO
    Ctx --> GrapesTwig
    Access --> TwigExt
    Access --> AdminUI
```

---

## Doctrine model

```mermaid
erDiagram
    BuilderPage ||--o| BuilderDocument : "1:1 document"
    BuilderPage ||--o{ BuilderPageTranslation : "1:N locales"
    BuilderPage ||--o{ BuilderPageRevision : "1:N snapshots"
    BuilderDocument ||--o{ BuilderDocumentLocale : "1:N widgetProps"

    BuilderPage {
        int id PK
        string uuid UK
        string pageKey UK
        enum status "draft|published"
        datetime publishedAt
        datetime createdAt
        datetime updatedAt
    }

    BuilderDocument {
        int id PK
        json structure "v1 classic or v2 grapesjs"
    }

    BuilderDocumentLocale {
        int id PK
        string locale
        json widgetProps "classic v1 only"
    }

    BuilderPageTranslation {
        int id PK
        string locale
        string title
        string slug
        string metaTitle
        string metaDescription
        string ogTitle
        string ogDescription
        string ogImage
        string canonicalUrl
        string robots
    }

    BuilderPageRevision {
        int id PK
        json structure
        json widgetPropsByLocale
        string label
        datetime createdAt
    }
```

Tables default to `pb_*` (optional `doctrine.table_prefix`).

---

## Document schemas

### Classic schema v1 (sections → columns → widgets)

```mermaid
flowchart TB
    S["structure.version = 1"]
    S --> Sec1["section"]
    S --> SecN["section …"]
    Sec1 --> ColA["column width"]
    Sec1 --> ColB["column …"]
    ColA --> W1["widget id + type"]
    ColA --> W2["widget …"]
    W1 --> Child["optional children<br/>container nesting"]
    Props["BuilderDocumentLocale.widgetProps<br/>keyed by widget id per locale"] -.-> W1
    Props -.-> W2
```

### GrapesJS schema v2

```mermaid
flowchart TB
    G["structure.version = 2<br/>engine = grapesjs"]
    G --> HTML["html"]
    G --> CSS["css"]
    G --> Project["grapes project JSON"]
    G --> LC["localeContent"]
    LC --> LEs["es: html / css / grapes"]
    LC --> LEn["en: html / css / grapes"]
    Note["BuilderDocumentLocale.widgetProps<br/>empty for Grapes pages"] -.-> G
```

---

## Admin edit → public render

```mermaid
sequenceDiagram
    actor Editor
    participant Canvas as Admin canvas / sections
    participant API as Document API
    participant Doc as DocumentService
    participant Rev as PageRevisionStore
    participant DB as Doctrine
    participant Pub as /p/{pageKey}
    participant PRP as PageRenderProvider

    Editor->>Canvas: Edit layout
    Editor->>API: POST document + CSRF
    API->>Doc: saveDocument()
    opt revisions.on_save
        Doc->>Rev: snapshot previous live doc
        Rev->>DB: INSERT pb_page_revision
    end
    Doc->>DB: UPDATE structure + locales
    Editor->>API: POST publish
    API->>Doc: publish()
    opt revisions.on_publish
        Doc->>Rev: snapshot labeled "Published …"
    end
    Doc->>DB: status=published

    Note over Pub: Anonymous visitor
    Pub->>PRP: getRenderedTree(pageKey, locale)
    PRP->>DB: load published page
    alt engine grapesjs
        PRP-->>Pub: html/css + seo + twigApplied
    else classic v1
        PRP-->>Pub: sections tree + merged props
    end
```

---

## Draft / publish lifecycle

```mermaid
stateDiagram-v2
    [*] --> draft: createPage()
    draft --> draft: Save document
    draft --> published: Publish
    published --> published: Save document<br/>(live /p updates immediately)
    published --> draft: Unpublish / Save as draft
    draft --> [*]: optional delete page
    published --> [*]: optional delete page

    note right of draft
      Public /p/{pageKey} → 404
      Admin canvas still editable
    end note

    note right of published
      Public /p/{pageKey} renders
      Pencil shows when canAccess()
    end note
```

---

## Revisions

Enabled with `revisions.enabled: true`.

```mermaid
flowchart LR
    Live["Live BuilderDocument"]
    Save["saveDocument"]
    Pub["publish"]
    Manual["POST …/revisions<br/>Save version"]
    Snap["PageRevisionStore.snapshot"]
    Table["pb_page_revision"]
    List["Admin Versions UI"]
    Restore["POST …/revisions/{id}/restore"]

    Live --> Save
    Live --> Pub
    Live --> Manual
    Save -->|on_save: previous state| Snap
    Pub -->|on_publish: labeled| Snap
    Manual -->|skipIfUnchanged=false| Snap
    Snap --> Table
    Table --> List
    List --> Restore
    Restore -->|optional Before restore| Snap
    Restore -->|DocumentService.saveDocument| Live
    Snap -->|prune max_per_page| Table
```

---

## GrapesJS Twig pipeline

```mermaid
flowchart TB
    Raw["Grapes HTML with {{ }} / {% for %}"]
    San["GrapesDocumentSanitizer<br/>+ restoreTwigDelimiters"]
    CtxBuiltins["Built-ins:<br/>title, slug, pageKey, locale, status…"]
    CtxHost["Host GrapesTwigContextProviderInterface<br/>e.g. products, highlights"]
    Sandbox["GrapesTwigRenderer<br/>sandbox: if / for / set"]
    Out["Interpolated HTML for public page"]

    Raw --> San
    San --> Sandbox
    CtxBuiltins --> Sandbox
    CtxHost --> Sandbox
    Sandbox --> Out
```

---

## Security

```mermaid
flowchart TB
    Req["Request admin_page_builder_*"]
    Sub["PageBuilderKitAdminAccessSubscriber"]
    Checker{"PageBuilderKitAccessCheckerInterface<br/>canAccess()?"}
    Roles["ConfigurablePageBuilderKitAccessChecker<br/>access_roles"]
    Custom["Host access_checker service"]
    Allow["AllowAll…<br/>allow_unauthenticated"]
    OK["Controller"]
    Deny["AccessDenied"]

    Req --> Sub --> Checker
    Config["security.*"] --> Roles
    Config --> Custom
    Config --> Allow
    Roles --> Checker
    Custom --> Checker
    Allow --> Checker
    Checker -->|yes| OK
    Checker -->|no| Deny

    Public["Public page + pencil partial"]
    TwigFn["nowo_page_builder_can_edit()"]
    Public --> TwigFn --> Checker
```

---

## Asset upload

```mermaid
flowchart LR
    AM["GrapesJS Asset Manager"]
    EP["POST admin asset upload<br/>+ CSRF"]
    Handler["AssetUploadHandler"]
    Local["LocalFilesystemAssetStorage"]
    S3["AwsS3AssetStorage"]
    Custom["PageBuilderAssetStorageInterface"]

    AM --> EP --> Handler
    Handler -->|storage=local| Local
    Handler -->|storage=s3| S3
    Handler -->|storage=service| Custom
    Local --> URL["Public URL in canvas"]
    S3 --> URL
    Custom --> URL
```

---

## Public routing & i18n

One `BuilderPage` (`pageKey`) holds all locales. **Pretty URLs are host-owned.**

```mermaid
flowchart TB
    subgraph Bundle["Bundle identity"]
        Key["pageKey = about"]
        TrEn["Translation en<br/>slug: about"]
        TrEs["Translation es<br/>slug: sobre-nosotros"]
        Content["localeContent / widgetProps<br/>per locale"]
        Key --> TrEn
        Key --> TrEs
        Key --> Content
    end

    subgraph PatternA["Pattern A — same path"]
        A1["/about?_locale=en"]
        A2["/es/about"]
        A1 --> RenderA["getRenderedTree('about', locale)"]
        A2 --> RenderA
    end

    subgraph PatternB["Pattern B — different paths"]
        B1["/about → locale=en"]
        B2["/sobre-nosotros → locale=es"]
        B1 --> RenderB["getRenderedTree('about', 'en'|'es')"]
        B2 --> RenderB
    end

    subgraph Convenience["Bundle convenience only"]
        P["/p/about<br/>locale from Request"]
    end

    RenderA --> Key
    RenderB --> Key
    P --> Key
```

Details and code samples: [USAGE.md — Associating pages with public routes](USAGE.md#associating-pages-with-public-routes-i18n).

## Ports, SPI and events

Every port is an interface aliased in the container (autowire the interface; a host may alias it to its own service or
decorate it). No port, event or VO of this list takes a Symfony, Doctrine or HTTP type, except the deprecated ones.

| Interface | Kind | Built-in service / notes |
|-----------|------|--------------------------|
| `Service\PagePublisherInterface` | port (1.14) | `DocumentService`: create, save, publish, unpublish **by page key** |
| `Service\PageRenderProviderInterface` | port | `PageRenderProvider`: the render tree of a page |
| `Service\InlineContentFieldRendererInterface` | port | `InlineContentFieldRenderer`: one content field of a page |
| `Service\PublicBindHydratorInterface` | port | `PublicBindHydrator`: binds content fields into Grapes HTML |
| `Persistence\PageBuilderPendingEventsInterface` | port (1.14) | `PageBuilderTransaction`: dispatch the write events after your commit (`transaction: host`) |
| `Repository\BuilderPageRepositoryInterface`, `BuilderPageRevisionRepositoryInterface`, `BuilderPageTemplateRepositoryInterface`, `BuilderPageStatusQueryInterface` | read ports | bundle repositories; they return the bundle's own entities (standard hosts) or scalars |
| `Security\PageBuilderKitAccessCheckerInterface` | SPI | `security.access_checker`, else the role-based checker |
| `Media\PageBuilderAssetUploadStorageInterface` (1.15), `PageBuilderAssetLibraryInterface` | SPI | asset storage (`AssetUpload` VO); `PageBuilderAssetStorageInterface::store(UploadedFile)` is deprecated |
| `Service\GrapesTwigPageContextProviderInterface` (1.15) | SPI (tag `nowo_page_builder_kit.grapes_twig_context`) | `PageContext` VO; `GrapesTwigContextProviderInterface` (entity) is deprecated |
| `Content\ContentFieldDefinitionProviderInterface` | SPI | catalogue of content fields per page key |
| `Widget\WidgetTypeInterface`, `WidgetPackInterface`, `Grapes\GrapesBlockPackInterface` | SPI (tags) | classic widgets and GrapesJS blocks |
| `Security\Html\PageBuilderHtmlSanitizerInterface` | SPI | `html.sanitize.service` |
| `Debug\PageBuilderKitTraceInterface` | internal | profiler trace |

Events (`Event\*`: `PageCreatedEvent`, `PageDocumentSavedEvent`, `PageMetadataSavedEvent`, `PagePublishedEvent`,
`PageUnpublishedEvent`, `PageRevisionRestoredEvent`, `PageTemplateChangedEvent`) carry ids and scalars only and are
dispatched after the commit (never inside a flush, never for a rollback). Presentation stays themable through Twig
templates and the FormKit / UiKit profiles (REQ-TWIG-001); form types and page factories are UI services, not ports.

## Standard Symfony and DDD hosts

Both paths are supported; the standard one is the zero-config default. Examples: [USAGE.md](USAGE.md#standard-symfony-and-ddd--hexagonal-hosts).

### Standard Symfony host

Nothing to configure: the six bundle entities are mapped with attributes on the manager that maps them (your default
one), the admin UI and `DocumentService` flush their writes (`doctrine.transaction: bundle`), and listeners receive the
id events after the commit.

### DDD / hexagonal host

Opt-in, nothing of it is forced on standard hosts:

```yaml
nowo_page_builder_kit:
    doctrine:
        entity_manager: page_builder   # a manager that maps ONLY the bundle entities
        transaction: host              # the bundle never flushes when your code calls it
        mapping_type: xml              # 1.16: the shipped XML mapping instead of the attributes
```

- **Mapping.** The bundle entities are bundle-owned (no host entity extends or uses anything of the bundle; pages are
  addressed by key and uuid). `doctrine.mapping_type: xml` (1.16) registers `src/Resources/config/doctrine-xml/*.orm.xml`
  (same tables, columns, indexes and constraints as the attributes; checked by `tests/Unit/Doctrine/XmlMappingParityTest.php`)
  on `doctrine.entity_manager`, for managers that load XML mapping only. You may also map that directory yourself
  (`type: xml`, `prefix: Nowo\PageBuilderKitBundle\Entity`, `is_bundle: false`) on the manager you set in
  `doctrine.entity_manager`.
- **Persistence.** `entity_manager` keeps the entities on that manager only; `transaction: host` leaves the commit to
  your use case and the events wait for it (`PageBuilderPendingEventsInterface`).
- **Calls.** `PagePublisherInterface` (by key), `PageRenderProviderInterface`, the id events.

`tests/Integration/Persistence/PageBuilderPersistenceKernelTest.php` boots both shapes (attribute and XML mapping, own
manager, host transaction mode).

## REQ-ARCH-001 status

| Rule | Status | Where |
|------|--------|-------|
| 1. Interfaces + VOs | ✅ | ports above; key-based publisher (1.14), `PageContext` / `AssetUpload` VOs (1.15) |
| 2. No forced base class / trait | ✅ | bundle-owned entities, attribute mapping by default, XML mapping (1.16) |
| 3. No framework types in ports, events, VOs | ✅ | deprecated `store(UploadedFile)` / entity context provider kept until 2.0 |
| 4. Events carry ids | ✅ | `Event\*` |
| 5. SPI documented | ✅ | this page, [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md) |
| 6. Never flush the host EM | ⚠️ default until 2.0 | `doctrine.transaction: bundle` without `entity_manager` flushes the manager that maps the entities (the host default one) |
| 7. Events after commit | ✅ | after-commit middleware + outbox (1.14) |
| 8. Config through provider ports | ✅ | config tree and the SPI above |

## Deferred to 2.0

Tracked in [UPGRADING.md](UPGRADING.md#to-1160):

- **Rule 6 default.** Without `doctrine.entity_manager`, `transaction: bundle` (the default) flushes the manager that
  maps the bundle entities, which in a standard host is the default one (pending host changes included). Changing the
  default to a bundle-owned manager or to `host` mode in 1.x would move the tables to another manager or stop
  persisting the writes of existing hosts, so it waits for a major. Until then: set `doctrine.entity_manager` (and,
  for DDD hosts, `transaction: host`).
- **Removal of the deprecated SPI.** `PageBuilderAssetStorageInterface::store(UploadedFile)` and
  `GrapesTwigContextProviderInterface::getContext(BuilderPage, …)`.

## Related docs

- [USAGE.md](USAGE.md) — routes, Twig, i18n URL patterns, revisions, draft/publish, standard and DDD hosts
- [CONFIGURATION.md](CONFIGURATION.md) — YAML options
- [SECURITY.md](SECURITY.md) — access and sanitization
- [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) — phases and REQ anchors
- [USE-CASES.md](USE-CASES.md) — demo matrix
