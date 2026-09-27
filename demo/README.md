# Page Builder Kit Bundle — demos

| Demo | Symfony | PHP | Port (default) |
| --- | --- | --- | --- |
| [symfony8](symfony8/) | 8.x | 8.5 (FrankenPHP) | 8137 |

From the bundle root:

```bash
make -C demo/symfony8 up
make demo-smoke
```

The Symfony 8 demo exposes the public pages at `/` and `/contact`, plus the bundle admin UI at:

- `/admin/pages/home/layout`
- `/admin/pages/contact/layout`

Use the demo credentials `admin` / `admin` to sign in via `/login`.

See [docs/DEMO-FRANKENPHP.md](../docs/DEMO-FRANKENPHP.md).
