# Architecture

Mermaid diagrams for **Page Builder Kit Bundle**. Rendered by GitHub, many IDEs, and Spec Kit docs viewers.

## Table of contents

- [System overview](#system-overview)
- [Doctrine model](#doctrine-model)
- [Document schemas](#document-schemas)
- [Admin edit → public render](#admin-edit--public-render)
- [Draft / publish lifecycle](#draft--publish-lifecycle)
- [Revisions](#revisions)
- [GrapesJS Twig pipeline](#grapesjs-twig-pipeline)
- [Security](#security)
- [Asset upload](#asset-upload)
- [Public routing & i18n](#public-routing--i18n)

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

## Related docs

- [USAGE.md](USAGE.md) — routes, Twig, i18n URL patterns, revisions, draft/publish
- [CONFIGURATION.md](CONFIGURATION.md) — YAML options
- [SECURITY.md](SECURITY.md) — access and sanitization
- [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md) — phases and REQ anchors
- [USE-CASES.md](USE-CASES.md) — demo matrix
