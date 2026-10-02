# Use cases

Page Builder Kit Bundle + FrankenPHP demo cover the editor / publish / render matrix and Phase 5–6 content fields.

**Editor guide:** [BUILDER-MANUAL.md](BUILDER-MANUAL.md) (screenshots in [`images/demo/`](images/demo/)).

## Bundle capabilities

| Area | What you get |
| --- | --- |
| Engines | GrapesJS schema **v2** (default / primary) + classic section/column/widget **v1** (**legacy**) |
| Canvas | GrapesJS preset-webpage + plugins, devices, Asset Manager, compounds, **Content fields** blocks |
| i18n | Locale tabs → `localeContent` / classic `widgetPropsByLocale`; **content fields** → `fields` + `fieldValues[locale]` + `labels[locale]` |
| API | CSRF JSON GET/POST document + publish (required-field validation) |
| Public | `/p/{pageKey}` only when **published**; slots `[[fields.*]]` + Twig `fields.*` |
| Security | `*_roles` **or** custom `access_checker` (`layout` / `content` / `publish` / `templates`) |
| HTML safety | `html.sanitize` (allowlist) + `GrapesDocumentSanitizer` |
| Extensibility | Classic widget packs + Grapes block packs |
| SEO | Translation meta / OG / canonical / robots → `page_tree.seo` |
| Content | Admin `/pages/{pageKey}/content` — schema, values, repeaters, image upload, page reference |
| Templates | Apply with `include_field_schema` / `include_field_values` / `include_seo` |
| A11y | Grapes traits + landmark blocks; public skip link |
| Assets | Grapes Asset Manager + content picker → **S3 mock** (Adobe S3Mock on `:9190`) |


## Demo matrix (`/showcase`)

Seed version: `App\Demo\DemoUseCases::SEED_VERSION` (bump to reseed).

| Key | Route | Engine | Publish | Use case |
| --- | --- | --- | --- | --- |
| `home` | `/` | grapesjs | yes | Full marketing landing |
| `compounds` | `/compounds` | grapesjs | yes | All compound blocks |
| `pricing` | `/pricing` | grapesjs | yes | Pricing cards |
| `about` | `/about` | grapesjs | yes | About / story |
| `contact` | `/contact` | grapesjs | yes | Contact CTA |
| `blog` | `/blog` | grapesjs | yes | Long-form article |
| `faq` | `/faq` | grapesjs | yes | FAQ hub |
| `portfolio` | `/portfolio` | grapesjs | yes | Project grid |
| `product` | `/product` | grapesjs | yes | Product detail |
| `newsletter` | `/newsletter` | grapesjs | yes | Lead capture form |
| `forms` | `/forms` | grapesjs | yes | Forms plugin fields |
| `legal` | `/legal` | grapesjs | yes | Dense legal text |
| `empty` | `/empty` | grapesjs | yes | Blank starter |
| `i18n` | `/i18n` | grapesjs | yes | Divergent EN vs ES trees |
| `classic` | `/classic` | classic v1 (**legacy**) | yes | Nested widgets + locale props |
| `sections` | `/sections` | classic v1 (**legacy**) | yes | Multi-section shared layout · EN≠ES props |
| `sections-i18n` | `/sections-i18n` | grapesjs | yes | Different section trees per locale |
| `twig` | `/twig` | grapesjs | yes | Twig variables in Grapes HTML |
| `fields` | `/fields` | grapesjs | yes | **Content fields** + slots + repeater/reference |
| `seo` | `/seo` | grapesjs | yes | Meta / OG / robots + a11y landmarks |
| `draft` | `/draft` | grapesjs | **no** | Unpublished → `/p/draft` = 404 |
| `multi-render` | `/multi-render` | — (composite) | n/a | Embeds pricing+about+faq (collector ≥3) |

## Builder workflows (documented + e2e)

| Workflow | Demo entry | Admin URL | Covered by |
| --- | --- | --- | --- |
| List pages | Showcase → Admin | `/admin/page-builder/pages` | e2e + screenshot `builder-01` |
| Edit layout | Canvas | `/admin/page-builder/pages/{key}/canvas` | e2e + `builder-02` / `interaction` |
| Edit content fields | `/fields` | `/admin/page-builder/pages/fields/content` | e2e + `builder-03` / `builder-04` |
| Templates apply | Templates UI | `/admin/page-builder/templates` | e2e + `builder-05` |
| Publish / draft 404 | `/draft` | canvas publish | e2e |
| Locale switch | `?_locale=es` | canvas / content tabs | e2e |
| Classic sections | `/sections` | `…/sections` | showcase matrix |

## Coverage gaps (intentional)

| Topic | Status |
| --- | --- |
| Full media library browser | Local list + picker (Phase 7); no cross-page CDN UI |
| Unlimited nested repeaters | Safety cap 32 (Phase 7) |
| Visual dynamic-tag property panel | Traits **Dynamic tag** on text/link/image (Phase 7) |
| Multi-user workflow / approval | Out of scope |

## How to explore

```bash
make -C demo/symfony8 up
# http://localhost:8137/showcase
make -C demo/symfony8 test-e2e
make -C demo/symfony8 demo-screenshots
```

- Login: `admin` / `admin`
- Locale: `?_locale=es` / `?_locale=en`
- Admin list: `/admin/page-builder/pages`
- Canvas: `/admin/page-builder/pages/{pageKey}/canvas`
- Content: `/admin/page-builder/pages/{pageKey}/content`

## Reseed

Change `DemoUseCases::SEED_VERSION` (or delete pages in admin). The seeder rewrites when the seed marker is missing/outdated.
