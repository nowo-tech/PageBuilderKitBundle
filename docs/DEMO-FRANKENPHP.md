# Demo applications with FrankenPHP

This repository ships a Symfony 8 demo for Page Builder Kit Bundle running on FrankenPHP.

## Contents

- [Overview](#overview)
- [What the demo includes](#what-the-demo-includes)
- [Development configuration](#development-configuration)
- [Production-style worker mode](#production-style-worker-mode)
- [Switching classic vs worker](#switching-classic-vs-worker)
- [Troubleshooting](#troubleshooting)

## Overview

Run the demo from the bundle root:

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8137`

The demo proves that:

- the bundle boots on Symfony 8
- Doctrine mappings are valid
- admin and public routes respond
- FrankenPHP works in classic and worker mode

## What the demo includes

- Symfony 8 application under `demo/symfony8`
- FrankenPHP + Docker Compose
- MySQL 8 service (not published to the host)
- Path-mounted `PageBuilderKitBundle` from the repository root
- `TwigExtraBundle`, `FormKitBundle`, `UiKitBundle`, and demo-only dev bundles
- Form login: **`admin`** / **`admin`** (`ROLE_ADMIN`)
- Bundle config: `config/packages/nowo_page_builder_kit.yaml`
- **Pre-built GrapesJS + classic seeds** — see [USE-CASES.md](USE-CASES.md) and `/showcase` (seed v4):

| Route | Page key | Notes |
| --- | --- | --- |
| `/showcase` | — | Full use-case hub |
| `/` | `home` | Marketing landing |
| `/compounds` | `compounds` | Compound gallery |
| `/pricing` `/about` `/contact` | … | Marketing/content |
| `/blog` `/faq` `/portfolio` `/product` | … | Content / commerce |
| `/newsletter` `/forms` | … | Forms |
| `/legal` `/empty` `/i18n` | … | Legal, blank, locale divergence |
| `/classic` | `classic` | Schema **v1** widgets + nesting |
| `/draft` | `draft` | Unpublished (`/p/draft` → 404) |

Seeds re-apply when `data-pbk-demo-seed` / classic marker does not match `DemoUseCases::SEED_VERSION`. Locale: `?_locale=es` / `en`.

Important routes:

| Route | Path | Notes |
| --- | --- | --- |
| `demo_home` | `/` | Demo landing |
| `app_login` | `/login` | Demo login |
| `admin_page_builder_list` | `/admin/page-builder/pages` | Page list + create |
| `admin_page_builder_canvas` | `/admin/page-builder/pages/{pageKey}/canvas` | Visual editor |
| `page_builder_public_render` | `/p/{pageKey}` | Published pages only |

## Development configuration

- Use `docker/frankenphp/Caddyfile.dev` for faster iteration
- Keep `APP_ENV=dev`
- Use `FRANKENPHP_MODE=classic` when you want per-request PHP execution

## Production-style worker mode

The demo defaults `FRANKENPHP_MODE=worker` in `.env.example` to validate long-lived workers.

See [FRANKENPHP-WORKER-AUDIT.md](FRANKENPHP-WORKER-AUDIT.md).

## Switching classic vs worker

Set in `demo/symfony8/.env`:

```dotenv
FRANKENPHP_MODE=classic
```

or:

```dotenv
FRANKENPHP_MODE=worker
```

Recreate the container:

```bash
make -C demo/symfony8 down
make -C demo/symfony8 up
```

## Troubleshooting

- **Port conflict:** change `PORT=8137` in `.env`.
- **HTTP 502 on first boot:** wait for Composer install in the entrypoint; check `docker compose logs php`.
- **Bundle changes not visible:** restart the demo or run `make -C demo/symfony8 link-bundle` if your Makefile target provides it.

Smoke test from bundle root: `make demo-smoke`.
