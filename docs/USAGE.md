# Usage

How to manage builder pages, save documents, and render them publicly.

## Table of contents

- [Admin routes](#admin-routes)
- [DocumentService](#documentservice)
- [Document JSON API and CSRF](#document-json-api-and-csrf)
- [Twig rendering](#twig-rendering)
- [Widget types](#widget-types)
- [Elementor-like Style and Advanced](#elementor-like-style-and-advanced)
- [i18n: structure and props model](#i18n-structure-and-props-model)
- [Custom access logic](#custom-access-logic)
- [Twig overrides](#twig-overrides)
- [Custom widgets (Phase 4 preview)](#custom-widgets-phase-4-preview)

## Admin routes

| Route name | Method | Path | Purpose |
| --- | --- | --- | --- |
| `admin_page_builder_list` | GET, POST | `/admin/page-builder/pages` | List pages; create new page (form) |
| `admin_page_builder_canvas` | GET | `/admin/page-builder/pages/{pageKey}/canvas` | Visual editor shell |
| `admin_page_builder_document_get` | GET | `/admin/page-builder/pages/{pageKey}/document` | Load structure + props JSON |
| `admin_page_builder_document_save` | POST | `/admin/page-builder/pages/{pageKey}/document` | Persist document |
| `admin_page_builder_document_publish` | POST | `/admin/page-builder/pages/{pageKey}/publish` | Set page status to published |

Public bundle route (optional for hosts that do not use custom controllers):

| Route name | Method | Path | Purpose |
| --- | --- | --- | --- |
| `page_builder_public_render` | GET | `/p/{pageKey}` | Render published page (404 if draft) |

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

Published pages are available at `/p/{pageKey}` when routes are imported.

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

Endpoint: `POST /admin/page-builder/assets/upload` (CSRF `page_builder_asset_upload`).

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

Legacy section/column/widget trees remain readable. Their Appearance settings (`cssId`, `cssClasses`, `style`, `attributes`) still render via `nowo_pbk_element_attrs()` / `ElementAppearanceNormalizer`.

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

## Custom access logic

```yaml
nowo_page_builder_kit:
    security:
        access_roles: [ROLE_EDITOR]
```

Custom checker:

```php
use Nowo\PageBuilderKitBundle\Security\PageBuilderKitAccessCheckerInterface;

final class CmsEditorAccessChecker implements PageBuilderKitAccessCheckerInterface
{
    public function canAccess(): bool
    {
        return true; // project rules
    }
}
```

```yaml
nowo_page_builder_kit:
    security:
        access_checker: App\Security\CmsEditorAccessChecker
```

## Twig overrides

Override bundle templates in the host:

```text
templates/bundles/NowoPageBuilderKitBundle/
├── widgets/heading.html.twig
├── public/page.html.twig
└── admin/pages/canvas.html.twig
```

Widget types resolve public templates via `@NowoPageBuilderKitBundle/widgets/{type}.html.twig`.

## Custom widgets (Phase 4 preview)

Phase 1 ships six core widgets. Phase 4 will document host-provided widget bundles. Today you can register services tagged `nowo_page_builder_kit.widget_type` implementing `WidgetTypeInterface`; `WidgetTypePass` collects them into `WidgetTypeRegistry`.

See [SPEC-DRIVEN-DEVELOPMENT.md](SPEC-DRIVEN-DEVELOPMENT.md#roadmap-phases-2-4).
