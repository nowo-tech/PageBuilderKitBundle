# Symfony 8 demo — Page Builder Kit Bundle

Minimal Symfony 8 application running under **FrankenPHP** with MySQL and the path-mounted bundle.

## Quick start

From the bundle root:

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8137`

## What to try

1. Open `/` (or `/showcase` for the full seed matrix)
2. Open `/api/ping`
3. Log in at `/login` — credentials: `admin` / `admin` (form login, `ROLE_ADMIN`)
4. Admin list: `/admin/page-builder/pages`
5. Canvas (Grapes): `/admin/page-builder/pages/home/canvas`
6. Content fields: `/admin/page-builder/pages/fields/content` (public demo `/fields`)
7. Classic legacy sections: `/admin/page-builder/pages/classic/sections`
8. Composite demo: `/multi-render`
9. Builder manual screenshots: `make demo-screenshots` → [`docs/BUILDER-MANUAL.md`](../../docs/BUILDER-MANUAL.md)

## Useful commands

```bash
make -C demo/symfony8 assets          # pnpm + Vite (Pentatrion) → public/build/
make -C demo/symfony8 test
make -C demo/symfony8 test-e2e
make -C demo/symfony8 demo-screenshots
make -C demo/symfony8 shell
make -C demo/symfony8 down
```

## Frontend (Pentatrion Vite)

Demo shell assets use **pnpm** + **Vite** + [`vite-plugin-symfony`](https://symfony-vite.pentatrion.com/) (`pentatrion/vite-bundle`):

- Sources: `assets/app.ts`, `assets/app.css`
- Build output: `public/build/` (Twig `vite_entry_*` helpers in `templates/base.html.twig`)
- Dev: `pnpm run dev` (Vite HMR) alongside the FrankenPHP container

Bundle admin assets (canvas / inline-edit) are built from the bundle root with `make assets` (TypeScript → `src/Resources/public/`).

## Configuration

- Bundle config: `config/packages/nowo_page_builder_kit.yaml`
- Routes import: `config/routes/nowo_page_builder_kit.yaml`
- Security: in-memory `ROLE_ADMIN` with form login (`/login`)
- Database: MySQL 8 (`mysql` service, not published to the host; DSN via `DATABASE_URL`)
- **Asset uploads (S3):** Adobe S3Mock service `s3` — API `http://localhost:9190`. Bundle uses `grapesjs.assets_upload.storage: s3` with `App\Demo\DemoS3Helper` (`aws/aws-sdk-php`).

See [../../docs/DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md) and [../../docs/USE-CASES.md](../../docs/USE-CASES.md).
