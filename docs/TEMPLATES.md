# Templates

Pages of PageBuilderKitBundle on the UiKit page contract (REQ-UI-003, UiKit 2.17). The admin UI (under
`web_ui.path_prefix`, default `/admin/page-builder`) has eight pages. `Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPageFactory`
builds the UiKit view models (`Nowo\UiKitBundle\View\*`) for the controllers; `Nowo\PageBuilderKitBundle\Ui\PageBuilderKitPages`
resolves the layout (`web_ui.layout_template`, null = `nowo_ui_kit.layouts.admin`) and the per-page overrides
(`web_ui.templates.<page>`). Seven pages extend a UiKit scaffold and fill data blocks only; the GrapesJS canvas is a
unique page. Contract test: `tests/Integration/Contract/PageContractTest.php` (UiKit `PageContractTestCase`, strict
missing-translation rule, 15 fixtures). Twig namespace: `@NowoPageBuilderKitBundle` (views in `src/Resources/views/`).

## Table of contents

- [Pages](#pages)
- [Unique pages](#unique-pages)
- [Per-page overrides](#per-page-overrides)
- [Overriding templates (REQ-TWIG-001)](#overriding-templates-req-twig-001)
- [Bundle partials and assets](#bundle-partials-and-assets)
- [Translations](#translations)
- [Layout bridge (1.x layouts)](#layout-bridge-1x-layouts)
- [Allowed exceptions](#allowed-exceptions)

## Pages

Every view model passes `bundle: 'NowoPageBuilderKitBundle'` (theme exception
`nowo_ui_kit.theme.bundles.NowoPageBuilderKitBundle`) and `layout: PageBuilderKitPages::layout()`. Labels are
`NowoPageBuilderKitBundle` translation keys (`PageBuilderKitPages::label()`). Actions are offered by capability
(`layout`, `content`, `publish`, `templates`; the controllers and the access subscriber still enforce them). POST
actions carry the real CSRF `FormView`; duplicate, restore, apply, delete and remove sit behind the UiKit confirm
`<dialog>` (`View\Confirm`; duplicate and apply show their fields — new page key, title, copy options — inside the
dialog). Editor-defined labels (content fields, widget props) are printed as given (`TranslatableText`), never looked
up in the catalogue.

| Page (`web_ui.templates` id) | Template | Scaffold | View model | Data blocks filled |
| --- | --- | --- | --- | --- |
| Pages (`index`) | `admin/pages/index.html.twig` | `page/list` | `ListPage` (`index()`: header `admin.pages.title` / `admin.pages.intro`, action Templates; columns `pageKey` (required), `status`, `engine` (priority 2); rows = `list<BuilderPage>`; row actions Edit (canvas or classic sections editor), Content, SEO, Export, Duplicate (dialog), Versions; `Pagination` 50 per page; `EmptyState` `admin.pages.empty`; `data-testid="pbk-pages"`) | `nowo_ui_list_row` (key on `code`, status on `badge_success` / `badge_neutral`, editor on `badge_info` / `badge_warning`), `nowo_ui_list_after` ("Create page" card: `section_form` partial with `create_form`) |
| Canvas (`canvas`) | `admin/pages/canvas.html.twig` | unique (layout) | `ShowPage` carrier (`canvas()`: header `admin.canvas.title` / `admin.canvas.grapes_hint`, actions SEO, Versions, Preview, back link) | see [Unique pages](#unique-pages) |
| Content fields (`content`) | `admin/pages/content.html.twig` | `page/settings` | `SettingsPage` (`content()`: header `admin.content.title` / `admin.content.hint`, action Edit, back link; section `schema` (form = add-field form) and section `values` (form = values form of the active locale, or null)); `schema_table` = `Table` (`schemaTable()`: key, type, label; row action Remove behind a confirm) | `nowo_ui_settings_section` (schema: field table through the `table` partial + add form through `section_form`; values: locale tabs through the `tabs` partial + the values form with the image picker buttons), `nowo_ui_modals` (media library through the `dialog` partial) |
| SEO (`seo`) | `admin/pages/seo.html.twig` | `page/form` | `FormPage` (`seo()`: header `admin.seo.title` / `admin.seo.hint`, actions Versions, Edit, back link; form = `BuilderPageSeoType`; submit `admin.seo.save`; cancel = list) | `nowo_ui_form_body` (locale tabs, three fieldsets — page, search / meta, Open Graph — then `{{ parent() }}`) |
| Classic sections (`sections`) | `admin/pages/sections.html.twig` | `page/form` | `FormPage` (`sections()`: header `admin.sections.title` / `admin.sections.hint`, actions SEO, Versions, Preview and Publish or Unpublish (POST, publish capability), back link; form = `SectionsEditType`; body-only mode without sections) | `nowo_ui_form_body` (legacy notice on `flash_warning`, locale tabs, one `<details>` card per section with its columns and widget sub-forms (REQ-TWIG-003 child loop), `empty` partial without sections, then `{{ parent() }}`) |
| Versions (`revisions`) | `admin/pages/revisions.html.twig` | `page/list` | `ListPage` (`revisions()`: header `admin.revisions.title` / `admin.revisions.hint`, action Edit, back link; columns `id` (required), `label`, `createdAt`, `engine` (priority 2); row actions Diff and Restore (confirm); `EmptyState` `admin.revisions.empty`) | `nowo_ui_list_filters` ("Save version" card with `create_form`, then `{{ parent() }}`), `nowo_ui_list_row` |
| Version diff (`revision_diff`) | `admin/pages/revision_diff.html.twig` | `page/show` | `ShowPage` (`revisionDiff()`: header `admin.revisions.diff_title` / `admin.revisions.diff_hint`, back link to the versions) | `nowo_ui_show_sections` (summary + changed paths card, or the `empty` partial when identical), `nowo_ui_show_after` (side-by-side HTML / CSS / structure panels) |
| Templates (`templates`) | `admin/pages/templates.html.twig` | `page/list` | `ListPage` (`templates()`: header `admin.templates.title` / `admin.templates.hint`, action Export all, back link; columns `templateKey` (required), `label`; row actions Export, Create page (dialog with the target page fields), Delete (confirm); `EmptyState` `admin.templates.empty`) | `nowo_ui_list_filters` ("Save template from page" and "Import templates" cards, then `{{ parent() }}`), `nowo_ui_list_row` |

Other variables passed by the controllers (for host templates set through `web_ui.templates.<page>`; the 1.x names
are kept): `builder_page` (the `BuilderPage` entity — the 1.x `page` variable is now the view model), `pages`,
`engines`, `current_page`, `total_pages`, `create_form`, `duplicate_forms` (index); `page_key`, `locales`,
`default_locale`, `structure`, `grapesjs_config`, `grapesjs_cdn_version`, `csrf_token`, `document_*_url`,
`page_status`, `preview_url` (canvas); `pageKey`, `schema`, `fieldValues`, `active_locale`, `schema_add_form`,
`schema_remove_forms`, `values_form`, `can_edit_schema`, `can_edit_values`, `asset_*` (content); `locale`, `form`
(seo, sections); `sections_tree`, `publish_form`, `unpublish_form` (sections); `revisions`, `engine`, `max_per_page`,
`create_form`, `restore_forms` (revisions); `revisionId`, `diff` (revision diff); `templates`, `save_forms`,
`import_form`, `apply_forms`, `delete_forms` (templates); plus the bridge variables `nowo_page_builder_legacy_layout`
/ `nowo_page_builder_legacy_blocks`.

## Unique pages

- `admin/pages/canvas.html.twig` — the GrapesJS editor (full-height canvas, block manager, locale tabs, save / publish
  toolbar driven by `page-builder-canvas.js`). Extends `page.layout ?? nowo_ui_layout.admin` directly and fills
  `nowo_ui_document_title`, `nowo_ui_page_header` (`nowo_ui_page_header(page.header, page.bundle)`),
  `nowo_ui_content` (toolbar on the `toolbar` / `btn_primary` / `btn_secondary` / `badge_*` slots, locale tabs
  through the `tabs` partial, `#gjs` mount point, status line on the `muted` slot), `nowo_ui_styles` (local
  `css/grapes.min.css`, `css/grapick.min.css`, `css/page-builder.css`) and `nowo_ui_scripts`
  (`js/page-builder-canvas.js`, `type="module"`, CSP nonce when the request has one). The script swaps the slot
  classes it reads from `data-pbk-class-*` attributes (status badge, active locale tab), so it never hard-codes a CSS
  framework class.

## Per-page overrides

- `nowo_page_builder_kit.web_ui.templates.<page>` (`index`, `canvas`, `content`, `seo`, `sections`, `revisions`,
  `revision_diff`, `templates`): a host template that extends `@!NowoPageBuilderKitBundle/admin/pages/<page>.html.twig`
  and overrides a data block (`nowo_ui_list_row`, `nowo_ui_list_after`, `nowo_ui_settings_section`,
  `nowo_ui_form_body`, `nowo_ui_show_after`, …). Unknown page ids fail the container compilation. Prefer theming the
  UiKit slots (`nowo_ui_kit.theme`) and scaffolds once for every bundle.
- Record such overrides in the application's override inventory.

## Overriding templates (REQ-TWIG-001)

Any bundle template can be replaced by a file at `templates/bundles/NowoPageBuilderKitBundle/<subpath>` (same relative
path as under `src/Resources/views/`). `TwigPathsPass` registers the application directory first (`prependPath`) and
the bundle views after it (`addPath`), so **an application file at the override path always wins**.

**Freeze rule.** An application file at the override path always wins and will not pick up vendor changes for that
path until you remove or merge it: after `composer update` the application keeps rendering its own copy.
Upgrade-safe customisation, in this order:

| Depth | How | On a PageBuilderKitBundle upgrade |
| --- | --- | --- |
| Preferred — no Twig fork | `nowo_ui_kit.layouts.admin` (or `web_ui.layout_template`), `nowo_ui_kit.theme` (class / attribute maps, tokens, `theme.bundles.NowoPageBuilderKitBundle`), translations (`translations/NowoPageBuilderKitBundle.<locale>.yaml`), CSS on `nowo-ui-*` / `pbk-*` / `--nowo-ui-*` | Vendor templates and fixes apply automatically |
| Surgical | `web_ui.templates.<page>` with a thin `{% extends '@!NowoPageBuilderKitBundle/admin/pages/<page>.html.twig' %}` overriding one data block (with `{{ parent() }}` where possible) | Only that block is frozen |
| Full-file override | Copy a template into `templates/bundles/NowoPageBuilderKitBundle/<subpath>` | That `<subpath>` is frozen until removed or merged |

**Procedure.**

1. Find the `<subpath>` in the table below (path relative to `src/Resources/views/`).
2. Create `templates/bundles/NowoPageBuilderKitBundle/<subpath>` in the application (same file name).
3. Clear the cache in dev if needed: `php bin/console cache:clear`.
4. Treat the file as a fork: it does not change when the bundle ships a new version of that `<subpath>`.

**Recovering upstream after a freeze.** Delete (or rename aside) the application file, clear the Twig cache
(`php bin/console cache:clear`), then re-apply only surgical customisations if still needed. Diff your copy against
the new vendor file under `vendor/nowo-tech/page-builder-kit-bundle/src/Resources/views/<subpath>` before re-copying
anything.

**Overridable subpaths.**

| `<subpath>` | Purpose |
| --- | --- |
| `admin/pages/index.html.twig` | Pages list (list scaffold) |
| `admin/pages/canvas.html.twig` | GrapesJS canvas (unique page) |
| `admin/pages/content.html.twig` | Content fields (settings scaffold) |
| `admin/pages/seo.html.twig` | Page SEO (form scaffold) |
| `admin/pages/sections.html.twig` | Classic sections editor (form scaffold) |
| `admin/pages/revisions.html.twig` | Versions (list scaffold) |
| `admin/pages/revision_diff.html.twig` | Version diff (show scaffold) |
| `admin/pages/templates.html.twig` | Templates (list scaffold) |
| `admin/layout.html.twig` | Deprecated 1.x default layout (extends the UiKit admin layout) |
| `admin/_legacy_layout.html.twig` | Deprecated 1.x host-layout bridge |
| `public/page.html.twig` | Public page (`/p/{pageKey}`) |
| `public/_seo_meta.html.twig` | SEO / Open Graph meta tags |
| `public/_edit_button.html.twig`, `public/_editable_field.html.twig`, `public/_icon_pencil.svg.twig` | Front-end edit pencil and inline field editor |
| `widgets/*.html.twig` | Classic widget markup |
| `Collector/page_builder.html.twig` | Web Profiler panel |

Renaming or removing a `<subpath>` or a data block listed here is a breaking change (see [UPGRADING](UPGRADING.md)).

## Bundle partials and assets

The bundle ships no admin layout of its own (the deprecated 1.x `admin/layout.html.twig` extends the UiKit admin
layout), no local pagination, flash, confirm, modal, page header or empty-state partial and no inline script: the
scaffolds and the UiKit partials (`action`, `confirm`, `dialog`, `empty`, `flashes`, `pagination`, `page_header`,
`section_form`, `table`, `tabs`, `scripts`) render them, and the host layout loads the CSS framework. Bundle assets
(asset package `nowo_page_builder_kit`): `css/page-builder.css` (token-based layout rules on `pbk-*` classes),
`css/grapes.min.css` / `css/grapick.min.css` (vendored GrapesJS 0.22.9 / grapick 0.1.13 styles of the canvas),
`js/page-builder-canvas.js`, `js/page-builder-admin.js` (image upload + media library picker of the content page) and
`js/page-builder-inline-edit.js` (public inline editor). The GrapesJS editor scripts themselves are loaded at run time
by `page-builder-canvas.js` from `grapesjs_config.pluginCdnBase` (esm.sh) — see [CONFIGURATION](CONFIGURATION.md).

## Translations

Domain `NowoPageBuilderKitBundle` (REQ-I18N-003), catalogues `src/Resources/translations/NowoPageBuilderKitBundle.<locale>.yaml`
for en, es, fr, it, pt, de and nl (same keys; `make validate-translations`). Flash messages are translated when the
controllers add them, so any host layout prints them as is. Override any key in the application
(`translations/NowoPageBuilderKitBundle.<locale>.yaml`).

## Layout bridge (1.x layouts)

`web_ui.layout_template` may still point at a 1.x layout (no `nowo_ui_page_header` block, e.g. the project
`base.html.twig` rendering `admin`, or a copy of the 1.x `admin/layout.html.twig`): `PageBuilderKitPages` then wraps
it in `admin/_legacy_layout.html.twig` (maps the canonical blocks into `admin`, `title`, `page_title`, `stylesheets`,
`javascripts`) and triggers a deprecation. When the 1.x layout renders `nowo_ui_flashes` itself, the flashes are not
printed twice and the page header is printed there. The value `@NowoPageBuilderKitBundle/admin/layout.html.twig` (the
1.x default) is mapped to null. Page copies that still extend the deprecated `nowo_page_builder_layout_template()`
function render inside the UiKit admin layout. The bridge, the 1.x layout and the deprecated functions are removed in
2.0.

## Allowed exceptions

- (none) — the contract test declares no `allowedClasses()` and no `ignoredMissingTranslations()`.
