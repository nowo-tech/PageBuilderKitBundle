# Symfony 8 demo — Page Builder Kit Bundle

Minimal Symfony 8 application running under **FrankenPHP** with MySQL and the path-mounted bundle.

## Quick start

From the bundle root:

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8137`

## What to try

1. Open `/`
2. Open `/api/ping`
3. Open `/admin/pages/home/layout`
4. Open `/admin/pages/contact/layout`

Admin credentials: `admin` / `admin`

## Useful commands

```bash
make -C demo/symfony8 test
make -C demo/symfony8 shell
make -C demo/symfony8 down
```

## Configuration

- Bundle config: `config/packages/nowo_page_builder_kit.yaml`
- Routes import: `config/routes/nowo_page_builder_kit.yaml`
- Security: in-memory `ROLE_ADMIN` demo user with HTTP Basic
- Database: MySQL 8 (`mysql` service, not published to the host; DSN via `DATABASE_URL`)

See [../../docs/DEMO-FRANKENPHP.md](../../docs/DEMO-FRANKENPHP.md).
