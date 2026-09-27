# Use cases

Page Builder Kit Bundle + FrankenPHP demo cover the full editor/publish/render matrix.

## Bundle capabilities

| Area | What you get |
| --- | --- |
| Engines | GrapesJS schema **v2** (default) + classic section/column/widget **v1** |
| Canvas | GrapesJS preset-webpage + official plugins, devices, Asset Manager, compounds |
| i18n | Locale tabs → `localeContent` / classic `widgetPropsByLocale` with fallback |
| API | CSRF JSON GET/POST document + publish |
| Public | `/p/{pageKey}` only when **published** |
| Security | Role / custom checker / demo unauthenticated flag |
| HTML safety | `html.sanitize` + `GrapesDocumentSanitizer` (`allow_scripts` gated) |
| Extensibility | `WidgetTypeInterface` registry (classic) + DomComponents compounds (Grapes) |
| SEO | Translation meta / OG / canonical / robots → `page_tree.seo` |
| A11y | Grapes traits + landmark blocks; public skip link |
| Assets | Grapes Asset Manager upload → local disk or AWS S3 (`assets_upload`) |

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
| `classic` | `/classic` | classic v1 | yes | Nested widgets + locale props |
| `sections` | `/sections` | classic v1 | yes | Multi-section shared layout · EN≠ES props (Sections editor) |
| `sections-i18n` | `/sections-i18n` | grapesjs | yes | Different section trees per locale (`localeContent`) |
| `twig` | `/twig` | grapesjs | yes | Twig variables in Grapes HTML |
| `seo` | `/seo` | grapesjs | yes | Meta / OG / robots + a11y landmarks |
| `draft` | `/draft` | grapesjs | **no** | Unpublished → `/p/draft` = 404 |
| `multi-render` | `/multi-render` | — (composite) | n/a | Embeds `pricing`+`about`+`faq` in one request (collector ≥3 renders; no Doctrine page) |

## How to explore

```bash
make -C demo/symfony8 up
# http://localhost:8137/showcase
```

- Login: `admin` / `admin`
- Locale: `?_locale=es` / `?_locale=en`
- Admin list: `/admin/page-builder/pages`
- Canvas: `/admin/page-builder/pages/{pageKey}/canvas`

## Reseed

Change `DemoUseCases::SEED_VERSION` (or delete pages in admin). The seeder rewrites when the seed marker is missing/outdated.
