# Usage

## Screenshots

| Overview | Interaction |
|----------|-------------|
| ![Public pricing with demo chrome and Symfony Web Profiler](images/demo/overview.png) | ![GrapesJS admin canvas with Web Profiler toolbar](images/demo/interaction.png) |

Full viewport captures (demo nav/chrome + content + **Symfony Web Profiler** toolbar). Regenerate with `make -C demo/symfony8 demo-screenshots` (REQ-DEMO-013).


How to manage builder pages, save documents, and render them publicly.

Architecture diagrams (Mermaid): [ARCHITECTURE.md](ARCHITECTURE.md).

## Table of contents

- [Screenshots](#screenshots)

- [Admin routes](#admin-routes)
- [DocumentService](#documentservice)
- [Document JSON API and CSRF](#document-json-api-and-csrf)
- [Twig rendering](#twig-rendering)
- [Public HTML cleanup](#public-html-cleanup)
- [Public bind hydration, section filter and status query](#public-bind-hydration-section-filter-and-status-query)
- [Associating pages with public routes i18n](#associating-pages-with-public-routes-i18n)
- [Widget types](#widget-types)
- [Elementor-like Style and Advanced](#elementor-like-style-and-advanced)
- [i18n: structure and props model](#i18n-structure-and-props-model)
- [Content fields (Phase 5)](#content-fields-phase-5)
- [Custom access logic](#custom-access-logic)
- [Twig overrides](#twig-overrides)
- [Custom widgets](#custom-widgets)
- [Web Profiler collector](#web-profiler-collector)

## Admin routes

Paths below assume default `web_ui.path_prefix: /admin/page-builder`. Change the prefix in config to remount the whole admin UI (route **names** stay the same — prefer `path()` / `generateUrl()`).

| Route name | Method | Path (default prefix) | Purpose |
| --- | --- | --- | --- |
| `admin_page_builder_list` | GET, POST | `{prefix}/pages` | List pages; create new page (form) |
| `admin_page_builder_templates` | GET | `{prefix}/templates` | Page templates library |
| `admin_page_builder_templates_export_all` | GET | `{prefix}/templates/export` | Export all templates JSON |
| `admin_page_builder_templates_export` | GET | `{prefix}/templates/{templateKey}/export` | Export one template JSON |
| `admin_page_builder_templates_import` | POST | `{prefix}/templates/import` | Import template JSON (single or bundle) |
| `admin_page_builder_canvas` | GET | `{prefix}/pages/{pageKey}/canvas` | Visual editor shell (**layout**) |
| `admin_page_builder_content` | GET, POST | `{prefix}/pages/{pageKey}/content` | Typed content fields (**content** / layout for schema) |
| `admin_page_builder_content_schema` | POST | `{prefix}/pages/{pageKey}/content/schema` | Add/remove field defs (**layout**) |
| `admin_page_builder_document_get` | GET | `{prefix}/pages/{pageKey}/document` | Load structure + props JSON |
| `admin_page_builder_document_save` | POST | `{prefix}/pages/{pageKey}/document` | Persist document |
| `admin_page_builder_document_publish` | POST | `{prefix}/pages/{pageKey}/publish` | Set page status to published |
| `admin_page_builder_document_unpublish` | POST | `{prefix}/pages/{pageKey}/unpublish` | Set page status back to draft |
| `admin_page_builder_document_duplicate` | POST | `{prefix}/pages/{pageKey}/duplicate` | Clone page as a new draft |
| `admin_page_builder_document_export` | GET | `{prefix}/pages/{pageKey}/export` | Export page JSON |
| `admin_page_builder_document_import` | POST | `{prefix}/pages/import` | Import page JSON |
| `admin_page_builder_revisions` | GET | `{prefix}/pages/{pageKey}/revisions` | Version list (when enabled) |
| `admin_page_builder_revisions_diff` | GET | `{prefix}/pages/{pageKey}/revisions/{id}/diff` | Diff live vs revision |
| `admin_page_builder_assets_list` | GET | `{prefix}/assets` | Media library list (when upload enabled) |
| `admin_page_builder_asset_upload` | POST | `{prefix}/assets/upload` | Upload image (CSRF) |

Public bundle route (optional for hosts that do not use custom controllers):

| Route name | Method | Path | Purpose |
| --- | --- | --- | --- |
| `page_builder_public_render` | GET | `/p/{pageKey}` | Render published page; **draft preview** for editors who pass the access checker (banner); 404 for everyone else when draft |

## DocumentService

Inject `DocumentService` for programmatic page lifecycle:

```php
use Nowo\PageBuilderKitBundle\Service\DocumentService;

// Create a page with empty document and locale rows
$page = $documentService->createPage('landing', 'Landing page', 'es');

// Load by stable key
$page = $documentService->loadPageByKey('landing');

// Save canvas payload (GrapesJS v2 structure; widgetPropsByLocale unused for grapes)
$documentService->saveDocument($page, $structure, $widgetPropsByLocale);

// Publish
$documentService->publish($page);
// …
$documentService->unpublish($page); // back to draft — public /p/{pageKey} returns 404
```

`saveDocument()` normalizes structure through `DocumentNormalizer`. For GrapesJS documents it sanitizes HTML/CSS via `GrapesDocumentSanitizer`. Classic v1 documents still validate widget types and sanitize props per locale.

## Document JSON API and CSRF

The GrapesJS canvas client POSTs JSON:

```json
{
  "structure": {
    "version": 2,
    "engine": "grapesjs",
    "html": "<section>…</section>",
    "css": ".x{}",
    "grapes": {},
    "localeContent": {
      "en": { "html": "…", "css": "…", "grapes": {} },
      "es": { "html": "…", "css": "…", "grapes": {} }
    },
    "sections": []
  },
  "widgetPropsByLocale": {}
}
```

Legacy classic payloads (`version: 1`, `sections`) are still accepted.

Mutating requests must send header:

```http
X-CSRF-TOKEN: <token for intention page_builder_document>
```

The canvas template receives a CSRF token from `CsrfTokenManagerInterface` (`page_builder_document`).

## Twig rendering

**Option A — Twig function:**

```twig
{% set page_tree = nowo_page_builder_render('landing', app.request.locale) %}
{% include '@NowoPageBuilderKitBundle/public/page.html.twig' with { page_tree: page_tree } %}
```

**Option B — inject `PageRenderProvider` in a controller:**

```php
use Nowo\PageBuilderKitBundle\Service\PageRenderProvider;

$tree = $pageRenderProvider->getRenderedTree('landing', $request->getLocale());
return $this->render('site/page.html.twig', ['page_tree' => $tree]);
```

`page_tree` contains page metadata plus either:

- **GrapesJS** (`engine: grapesjs`): sanitized `html` + `css`
- **Classic** (`engine: classic`): resolved sections/columns/widgets with merged props

**Option C — bundle public controller:**

Published pages are available at `/p/{pageKey}` when routes are imported. Locale comes from `$request->getLocale()` (Symfony), not from the path segment.

## Public HTML cleanup

`Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer` is autowired. Call it on Grapes HTML after `PageRenderProvider` (or your host renderer) so public markup passes the Nu Html Checker:

- strip invalid `</source>` wrapping `<img>` inside `<picture>` (DOMDocument artifact)
- add a 1×1 GIF `src` on skeleton lazy images that only have a class (default `site-skeleton__img`)
- optional host PNG→WebP `<picture>` upgrades (`webpPictureUpgrades`: `png` / `webp` / `classContains`) — keep clinic/host asset paths in the host app

```php
use Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer;

$tree = $pageRenderProvider->getRenderedTree('landing', $request->getLocale());
$tree['html'] = $publicHtmlNormalizer->normalize((string) ($tree['html'] ?? ''));
```

Override constructor args in the host:

```yaml
Nowo\PageBuilderKitBundle\Html\PublicHtmlNormalizer:
    arguments:
        $skeletonImgClass: 'site-skeleton__img'
        $webpPictureUpgrades:
            - { png: '/build/images/hero.png', webp: '/build/images/hero.webp', classContains: 'hero' }
```

## Associating pages with public routes (i18n)

The bundle stores **one page entity per logical page**, not one page per language.

| Concept | Role |
| --- | --- |
| **`pageKey`** | Stable technical id (`about`, `pricing`). Used by admin, API, `/p/{pageKey}`, and `nowo_page_builder_render()`. **Not** locale-specific. |
| **`BuilderPageTranslation.slug`** | Per-locale SEO slug / Twig `{{ slug }}` / suggested canonical. **Does not** resolve HTTP routes by itself (no `findBySlug`). |
| **Locale content** | Classic: `widgetPropsByLocale`. Grapes: `localeContent[locale]`. Chosen at render time from the request locale (+ `default_locale` fallback). |

**The host app owns pretty URLs.** Map each marketing path → `(pageKey, locale)` and call `PageRenderProvider` / Twig. The optional `/p/{pageKey}` route is a convenience preview, not a CMS router.

### Pattern A — Same path, locale changes

One route; language via Symfony `{_locale}` prefix, subdomain, or query (`?_locale=es` like the demo).

```php
#[Route('/about', name: 'about')]
// or: #[Route('/{_locale}/about', name: 'about', requirements: ['_locale' => 'en|es'])]
public function about(Request $request, PageRenderProvider $pages): Response
{
    $tree = $pages->getRenderedTree('about', $request->getLocale());

    return $this->render('site/page.html.twig', ['page_tree' => $tree]);
}
```

```twig
{# same pageKey, different locale #}
{% set page_tree = nowo_page_builder_render('about', app.request.locale) %}
```

Examples: `/about` + `?_locale=es`, or `/en/about` ↔ `/es/about` with the same `pageKey=about`.

### Pattern B — Totally different paths per locale

Declare **two (or more) host routes** that point to the **same** `pageKey` but force the locale:

```php
#[Route('/about', name: 'about_en', defaults: ['_locale' => 'en'])]
public function aboutEn(PageRenderProvider $pages): Response
{
    return $this->render('site/page.html.twig', [
        'page_tree' => $pages->getRenderedTree('about', 'en'),
    ]);
}

#[Route('/sobre-nosotros', name: 'about_es', defaults: ['_locale' => 'es'])]
public function aboutEs(PageRenderProvider $pages): Response
{
    return $this->render('site/page.html.twig', [
        'page_tree' => $pages->getRenderedTree('about', 'es'),
    ]);
}
```

Keep `BuilderPageTranslation.slug` aligned with the pretty path when you care about SEO (`about` vs `sobre-nosotros`), and set `canonicalUrl` per locale in Admin → SEO if needed.

### What the bundle does *not* do

- No automatic routing table from slugs.
- No built-in “one different URL per locale” generator.
- `/p/{pageKey}` always uses the **key**, not the translation slug (if `slug !== pageKey`, they can diverge — prefer host routes for production).

See also [ARCHITECTURE.md](ARCHITECTURE.md#public-routing--i18n) and the demo (`DemoController`: fixed paths + `?_locale=`).

## Widget types

| Type | Template | Typical props |
| --- | --- | --- |
| `heading` | `widgets/heading.html.twig` | `text`, `tag` (`h1`–`h6`) |
| `text` | `widgets/text.html.twig` | `content` (plain or HTML per sanitize config) |
| `html` | `widgets/html.html.twig` | `html` |
| `image` | `widgets/image.html.twig` | `src`, `alt`, `link` |
| `button` | `widgets/button.html.twig` | `label`, `url`, `variant` |
| `spacer` | `widgets/spacer.html.twig` | `height` |

List registered types in Twig:

```twig
{% for widget in nowo_page_builder_widget_types() %}
  {{ widget.type }} — {{ widget.label_key|trans({}, 'NowoPageBuilderKitBundle') }}
{% endfor %}
```

## GrapesJS Style and Advanced

The admin canvas is **GrapesJS** (CDN). Style Manager, Layer Manager, and trait panels provide Elementor-like Style/Advanced editing on free-form HTML. Locale tabs load/save `localeContent[locale]`.

Optional blocks: Custom HTML (`grapesjs.allow_custom_code`), Script (`grapesjs.allow_scripts`), and **Twig** insert helpers (`grapesjs.twig.canvas_helpers`).

### Twig variables in Grapes HTML

1. Insert tokens from the Block Manager category **Twig**, or type `{{ title }}` in text.
2. On public render, `GrapesTwigRenderer` evaluates a **sandboxed** Twig template.
3. Built-in vars: `title`, `slug`, `pageKey`, `locale`, `status`, `page.title`, …
4. Host vars: implement `GrapesTwigContextProviderInterface` or pass `$context` to `getRenderedTree()` / `nowo_page_builder_render()`.

Demo: `/twig` and `/p/twig`.

### Draft vs published

- New pages start as **draft**. `Save` only persists the document; it does **not** change status.
- **Publish** → `status=published` (public `/p/{pageKey}` works).
- **Save as draft** / **Unpublish** → `status=draft` again (public route 404 until republished).
- Canvas and classic Sections editors expose both actions; API: `POST …/publish` and `POST …/unpublish`.

### Page versions (revisions)

Optional history of document snapshots (`BuilderPageRevision`):

```yaml
nowo_page_builder_kit:
    revisions:
        enabled: true
        max_per_page: 50
        on_save: true      # snapshot before each save
        on_publish: true   # labeled snapshot on publish
```

- Admin **Versions** screen: list, manual “Save version”, restore (rolls back structure + locale props).
- Autosave revisions skip when content is unchanged vs the latest snapshot; restore creates a “Before restore” safety snapshot when `on_save` is on.
- API: `GET …/revisions.json`, `POST …/revisions`, `POST …/revisions/{id}/restore` (CSRF `page_builder_document`).

Demo: enable in `nowo_page_builder_kit.yaml`, open Admin → Versions on any page.

### Edit pencil on public pages

When `PageBuilderKitAccessCheckerInterface::canAccess()` is true (logged-in editor / custom guard), public templates show a floating pencil:

```twig
{% include '@NowoPageBuilderKitBundle/public/_edit_button.html.twig' with { page_tree: page_tree } %}
```

- Twig function: `nowo_page_builder_can_edit()` (do **not** cache this in a Twig global — FrankenPHP workers).
- Grapes pages → canvas; classic pages → Sections editor.
- Host custom guard:

```yaml
nowo_page_builder_kit:
    security:
        access_checker: App\Security\PageBuilderAccessChecker
```

```php
final class PageBuilderAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return $this->auth->isGranted('ROLE_EDITOR');
    }
}
```

### Twig lists / products in Grapes HTML

Provide array context from the host (Doctrine → plain arrays/scalars — sandbox cannot call entity methods):

```php
#[AutoconfigureTag('nowo_page_builder_kit.grapes_twig_context')]
final class ProductsTwigContext implements GrapesTwigContextProviderInterface
{
    public function getContext(BuilderPage $page, string $locale): array
    {
        return [
            'products' => [
                ['name' => 'Starter', 'price' => '19 €', 'url' => '/pricing'],
            ],
        ];
    }
}
```

In GrapesJS (or seed HTML):

```twig
{% for p in products %}
  <li><a href="{{ p.url }}">{{ p.name }}</a> — {{ p.price }}</li>
{% endfor %}
```

Allowed tags: `if`, `for`, `set`. Demo: `/twig` (products + highlights loops).

### SEO & accessibility

- **Page SEO** (meta title/description, Open Graph, canonical, robots): Admin → **SEO** (`/admin/page-builder/pages/{pageKey}/seo`). Emitted via `page_tree.seo` and `_seo_meta.html.twig`.
- **A11y in canvas**: Block Manager category **A11y** + traits (`aria-label`, `role`, `alt`, …) when `grapesjs.a11y_helpers` is true.
- Public render includes a skip link targeting `#pbk-main-content`.

Demo: `/seo` and `/p/seo` (view page source for meta tags).

### Classic sections per locale

Schema **v1** keeps one shared section → column → widget tree. Content is stored in `widgetPropsByLocale`.

- Public demos: `/sections` (multi-section EN≠ES copy) and `/classic`
- Admin editor: `/admin/page-builder/pages/{pageKey}/sections` — locale tabs edit props section-by-section
- Grapes alternative with **different section trees** per locale: `/sections-i18n` (`localeContent`)

### Asset uploads (local / S3)

Enable GrapesJS Asset Manager uploads:

```yaml
nowo_page_builder_kit:
    grapesjs:
        asset_embed_as_base64: false
        assets_upload:
            enabled: true
            storage: local          # local | s3 | service
            max_bytes: 5242880
            local:
                directory: '%kernel.project_dir%/public/uploads/page-builder'
                public_prefix: /uploads/page-builder
```

**AWS S3** (requires `core/aws-s3-bundle`):

```yaml
nowo_page_builder_kit:
    grapesjs:
        assets_upload:
            enabled: true
            storage: s3
            s3:
                helper_service: core_aws_s3.service.helper
                folder: page-builder
                private: false
                public_base_url: 'https://cdn.example.com'  # optional CloudFront
```

Custom backends: implement `PageBuilderAssetStorageInterface` and set `storage: service` + `service: app.my_storage`.
For media library browsing, also implement `PageBuilderAssetLibraryInterface::list()`.

Endpoints:
- `POST /admin/page-builder/assets/upload` (CSRF `page_builder_asset_upload`)
- `GET /admin/page-builder/assets` — library list for content picker + Grapes Asset Manager seed

### Compound example blocks (`grapesjs.compound_examples`)

Category **Compound** in the Block Manager (enabled by default):

| Block | DomComponents type | Structure |
| --- | --- | --- |
| Hero | `pbk-compound-hero` | Eyebrow + title + lead + 2 CTAs |
| Feature grid | `pbk-compound-feature-grid` | Heading + 3× feature cards |
| Feature card | `pbk-compound-feature-card` | Icon + title + body |
| CTA banner | `pbk-compound-cta` | Copy + button row |
| Testimonial | `pbk-compound-testimonial` | Quote + avatar + author |
| Pricing card | `pbk-compound-pricing` | Plan + price + feature list + CTA |
| Media + text | `pbk-compound-media-split` | Image + copy column |
| Stats row | `pbk-compound-stats` | 3 metric cells |
| FAQ item | `pbk-compound-faq` | `<details>` / `<summary>` |

Each type is a nested editable tree (`data-pbk-compound="…"`). Children can be selected and styled independently.

### Register your own compound (host / override)

Override or extend `page-builder-canvas.js` after GrapesJS init:

```js
editor.DomComponents.addType('app-pricing-trio', {
  model: {
    defaults: {
      name: 'Pricing trio',
      tagName: 'section',
      attributes: { 'data-pbk-compound': 'pricing-trio' },
      components: [
        { type: 'pbk-compound-pricing' },
        { type: 'pbk-compound-pricing' },
        { type: 'pbk-compound-pricing' },
      ],
    },
  },
});

editor.BlockManager.add('app-pricing-trio', {
  label: 'Pricing trio',
  category: 'Compound',
  content: { type: 'app-pricing-trio' },
});
```

### Classic documents (schema v1)

**Legacy.** Prefer GrapesJS schema v2 + content fields for new pages. Classic section/column/widget trees remain readable and editable (Sections UI). Their Appearance settings (`cssId`, `cssClasses`, `style`, `attributes`) still render via `nowo_pbk_element_attrs()` / `ElementAppearanceNormalizer`. Demo routes `/classic` and `/sections` exist for regression only.

## i18n: structure and props model

The document splits **layout** from **translatable content**:

1. **`structure`** (JSON on `BuilderDocument`): version, sections, columns, widget ids/types, and **Elementor-like appearance** (`settings`: style, cssId, cssClasses, attributes). Shared across locales.
2. **`widgetPropsByLocale`**: map `locale → { widgetId → content props }`. Stored in `BuilderDocumentLocale`.

Example:

```yaml
structure:
  version: 1
  sections:
    - id: sec-1
      settings: {}
      columns:
        - id: col-1
          settings: { width: 12 }
          widgets:
            - id: w-heading-1
              type: heading

widgetPropsByLocale:
  es:
    w-heading-1: { text: "Bienvenido", tag: h1 }
  en:
    w-heading-1: { text: "Welcome", tag: h1 }
```

Public render merges props for the requested locale with fallback to `default_locale`. Page titles and slugs live on `BuilderPageTranslation` entities (separate from widget props).

## Content fields (Phase 5)

Prefer **shared Grapes layout** + typed values instead of duplicating HTML in `localeContent` for every string.

### Host Twig component (inline edit)

Declare a multilingual variable in any host template. Visitors see the stored value; users with **content** capability get a pencil that opens a modal.

```twig
{{ nowo_page_builder_field('home', 'hero_title', {
  type: 'html',
  labels: { es: 'Título hero', en: 'Hero title' },
  locale: app.request.locale,
  tag: 'h1',
  class: 'hero__title',
  default: 'Welcome'
}) }}

{# Singular `label` still works (BC); stored under the active locale on first save #}
{{ nowo_page_builder_field('home', 'price', { type: 'number', label: 'Price' }) }}
{{ nowo_page_builder_field('home', 'badge_icon', { type: 'icon', default: 'bi bi-star' }) }}
{{ nowo_page_builder_field('home', 'disclaimer', { type: 'raw' }) }}
```

| Type | Display | Persist |
| --- | --- | --- |
| `string` / `text` / `number` / `url` / `image` / `icon` / `bool` / `select` | escaped (or semantic HTML) | as-is |
| `html` / `richtext` | `|raw` after allowlist sanitize on save | sanitized |
| `raw` | `|raw` trusted | **not** sanitized |

- Field **labels** are multilingual: `labels[locale]` with fallback to `default_locale`, then singular `label`, then the field key.
- First save from the modal **creates** the field definition on the page document (`structure.fields` + `fieldValues`).
- API: `POST /admin/page-builder/pages/{pageKey}/fields/{fieldKey}` (CSRF `page_builder_content`, capability `content`).
- Assets (`page-builder.css` + `page-builder-inline-edit.js`) load once per request when an editable field is rendered. Run `assets:install` so `/bundles/nowopagebuilderkit/...` is available.

### Grapes HTML

1. Layout editor opens `/admin/page-builder/pages/{pageKey}/content` and adds fields (`hero_title`, type `string`, …).
2. Content editor fills values **and per-locale labels** via locale tabs (no canvas required).
3. In Grapes HTML use Twig:

```twig
<h1>{{ fields.hero_title }}</h1>
{% if fields.show_cta %}<a href="{{ fields.cta_url }}">…</a>{% endif %}
```

Stored shape (inside document `structure`):

```json
{
  "fields": [
    {
      "key": "hero_title",
      "type": "string",
      "label": "Hero title",
      "labels": { "es": "Título hero", "en": "Hero title" },
      "required": true,
      "options": [],
      "default": null
    }
  ],
  "fieldValues": {
    "es": { "hero_title": "Hola" },
    "en": { "hero_title": "Hello" }
  }
}
```

MVP types: `string`, `text`, `richtext`, `html`, `raw`, `number`, `url`, `image`, `icon`, `bool`, `select`. Spec: [`specs/005-content-fields-i18n/spec.md`](../specs/005-content-fields-i18n/spec.md).

### Phase 6 composites

Additional types: `repeater`, `group`, `reference`. Nesting is practically unlimited (safety cap **32**; deeper composites coerce to `string`).

### Phase 7 media library + dynamic tags

- Content admin image fields: **Library** picker (lists uploaded assets) + upload.
- Canvas: `assetsLibraryUrl` seeds Grapes Asset Manager; Traits panel **Dynamic tag** binds text/link/image to `[[fields.key]]`.
- Optional host storage: implement `PageBuilderAssetLibraryInterface::list()` alongside `PageBuilderAssetStorageInterface`.

```twig
{% for item in fields.faqs %}
  <h3>{{ item.question }}</h3>
  <p>{{ item.answer }}</p>
{% endfor %}
```

```json
{
  "key": "faqs",
  "type": "repeater",
  "label": "FAQs",
  "required": true,
  "min": 1,
  "fields": [
    { "key": "question", "type": "string", "label": "Question" },
    { "key": "answer", "type": "text", "label": "Answer" }
  ]
}
```

- Admin schema: subfields as `question:string,answer:text`; optional min/max for repeaters.
- **Publish** rejects empty required fields (and repeater `min`) for every configured locale.
- **Templates → apply**: checkboxes *Copy field schema* (default on) and *Copy field values* (default off).
- Twig-free slots: `[[fields.hero_title]]`, `[[fields.hero.title]]`, `[[fields.faqs.0.question]]` (also `[[@fields…]]`).
- Grapes category **Content fields** inserts bindings for the open page schema.
- Content admin: page select for `reference`; image upload when assets upload is enabled.

Spec: [`specs/006-phase6-content-hardening/spec.md`](../specs/006-phase6-content-hardening/spec.md) · [`specs/007-phase6b-content-wave2/spec.md`](../specs/007-phase6b-content-wave2/spec.md).

## Custom access logic

**Option A — roles** (BlogKit-style):

```yaml
nowo_page_builder_kit:
    security:
        access_roles: []          # optional shortcut for every capability
        layout_roles: [ROLE_PBK_LAYOUT]
        content_roles: [ROLE_PBK_CONTENT]
        publish_roles: [ROLE_PBK_LAYOUT]
        templates_roles: [ROLE_PBK_LAYOUT]
```

**Option B — custom guard** (`security.access_checker`):

```php
use Nowo\PageBuilderKitBundle\Enum\PageBuilderCapability;
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;

final class CmsEditorAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    public function canAccess(): bool { /* … */ }

    public function canLayout(): bool { /* … */ }

    public function canContent(): bool { /* … */ }

    public function canPublish(): bool { /* … */ }

    public function canTemplates(): bool { /* … */ }

    public function can(PageBuilderCapability|string $capability): bool
    {
        return match ($capability instanceof PageBuilderCapability ? $capability->value : $capability) {
            'layout' => $this->canLayout(),
            'content' => $this->canContent(),
            'publish' => $this->canPublish(),
            'templates' => $this->canTemplates(),
            default => false,
        };
    }
}
```

```yaml
nowo_page_builder_kit:
    security:
        access_checker: App\Security\CmsEditorAccessChecker
```

Controllers may inject `PageBuilderKitAccessGuard` (`assertLayout()`, …). Twig: `nowo_page_builder_can('content')`.

## Twig overrides

Override bundle templates in the host:

```text
templates/bundles/NowoPageBuilderKitBundle/
├── widgets/heading.html.twig
├── public/page.html.twig
└── admin/pages/canvas.html.twig
```

Widget types resolve public templates via `@NowoPageBuilderKitBundle/widgets/{type}.html.twig`.

## Custom widgets

Core classic widgets ship with the bundle. Hosts and Composer packs can add more:

- Tag `WidgetTypeInterface` services with `nowo_page_builder_kit.widget_type`
- Optionally group them with `WidgetPackInterface` (`nowo_page_builder_kit.widget_pack`)

Full guide: [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md).

## GrapesJS block packs

For schema v2 (Grapes) canvases, implement `GrapesBlockPackInterface` (tag `nowo_page_builder_kit.grapes_block_pack`). Blocks appear in the BlockManager via canvas config `blockPacks`. See [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md#grapesjs-block-packs) and the [Cookbook](COOKBOOK.md#ship-a-grapes-block-pack).

## Template sharing

Export/import template JSON (`admin_page_builder_templates_export` / `_export_all` / `_import`) to move reusable structures between projects. Format: `formatVersion: 1`, `kind: page_builder_template` (single) or `page_builder_templates` (bundle). Step-by-step: [Cookbook § Share templates](COOKBOOK.md#share-templates-across-projects).

Apply options (form checkboxes / JSON body):

- `include_field_schema` (default **true**) — copy `structure.fields`
- `include_field_values` (default **false**) — copy `structure.fieldValues`
- `include_seo` (default **false**) — copy SEO meta from `templateSeoByLocale` into `BuilderPageTranslation` (meta/OG/canonical/robots). Snapshotted when saving a template from a page; stripped from live page structure by `DocumentNormalizer`.

## Web Profiler collector

In `dev` (`kernel.debug=true`), the toolbar shows a **Page Builder** panel (layout icon) when `debug.collector` is true (default). It lists:

- **Effective capabilities** for the current user: `access` / `layout` / `content` / `publish` / `templates`
- Renders: `pageKey`, locale, status, engine, **field keys**, Twig applied/error, context **key names**, timings (ms)
- Public outcomes: `published` / `draft_preview` / `not_found_draft` / `not_found_missing`
- Admin actions: save, publish, unpublish, restore, duplicate, export, import, template_*

Render count is **per HTTP request**. The FrankenPHP demo route `/multi-render` calls `getRenderedTree()` three times so the toolbar shows ≥ 3.

See [CONFIGURATION.md](CONFIGURATION.md#debug).

## Public bind hydration, section filter and status query

**Bind hydration.** Grapes public HTML may contain `<span data-pbk-bind="hero_title">Resolved text</span>` slots. For users with the `content` capability, `PublicBindHydratorInterface::hydrate($html, $pageKey)` (or `{{ nowo_page_builder_hydrate_binds(html, pageKey)|raw }}`) wraps each slot with the inline-edit pencil/modal, using the slot inner HTML as value (no page query). Visitors receive the HTML unchanged. Field types and labels come from your `ContentFieldDefinitionProviderInterface`:

```php
final class ClinicContentFieldDefinitionProvider implements ContentFieldDefinitionProviderInterface
{
    public function definitions(string $pageKey): array
    {
        return [['key' => 'hero_title', 'type' => 'string', 'label' => 'content.hero.title']];
    }
}
```

**Section filter.** `ContentSectionFilter::sanitize($request->query->get('section'))` returns a lowercase `[a-z0-9_]+` slug or `null`; `ContentSectionFilter::filterSchema($schema, $section)` keeps rows whose `key` equals the section or starts with `{section}_`.

**Status query.** `BuilderPageStatusQueryInterface::findStatusByPageKey($pageKey)` returns `?PageStatus` with a scalar `SELECT p.status` (no document JSON hydrated) — use it in public controllers to decide 404 vs render.
