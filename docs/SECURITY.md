# Security

Security considerations for page builder admin, document API, and public rendering.

## Table of contents

- [Threat model](#threat-model)
- [Admin access guard (REQ-UI-002)](#admin-access-guard-req-ui-002)
- [Document API and CSRF](#document-api-and-csrf)
- [Rich text and HTML widgets](#rich-text-and-html-widgets)
- [Operational guidance](#operational-guidance)
- [Release security checklist](#release-security-checklist)

## Threat model

| Risk | Mitigation |
| --- | --- |
| Unauthorized users reach admin or document API | `access_roles`, custom `access_checker`, Symfony Security (REQ-UI-002) |
| CSRF on document save or publish | `X-CSRF-TOKEN` validated against intention `page_builder_document` |
| Stored XSS through `text` / `html` widgets | Twig auto-escaping where applicable; configurable `html.sanitize` on persist and render |
| Overly broad demo access | `allow_unauthenticated` defaults to `false` |
| Shared-database table collisions | Optional `doctrine.table_prefix` |

## Admin access guard (REQ-UI-002)

The bundle protects route names beginning with `admin_page_builder_` via `PageBuilderKitAdminAccessSubscriber`.

Default configuration:

```yaml
nowo_page_builder_kit:
    security:
        access_roles: [ROLE_EDITOR]
        allow_unauthenticated: false
```

Recommended host access control:

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/page-builder/, roles: ROLE_EDITOR }
```

For context-aware rules, implement `PageBuilderKitAccessCheckerInterface` and set `security.access_checker`.

## Document API and CSRF

Save and publish endpoints reject requests when the CSRF token is missing or invalid (`invalid_csrf` JSON, HTTP 403).

Recommendations:

- Do not expose admin routes outside authenticated editor sessions.
- When customizing the canvas client, keep sending `X-CSRF-TOKEN` for POST requests.
- Do not disable Symfony CSRF protection for document routes.

## Rich text and HTML widgets

Widgets `text` and `html` may store editor-authored HTML. Configure sanitization explicitly in production:

```yaml
nowo_page_builder_kit:
    html:
        sanitize:
            strategy: allowlist   # or strip | service
```

| Strategy | Behaviour |
| --- | --- |
| `none` (default) | Trusted editors only; HTML stored/rendered as-is |
| `allowlist` | DOM allowlist via `AllowlistPageBuilderHtmlSanitizer` |
| `strip` | Remove all tags |
| `service` | Host `PageBuilderHtmlSanitizerInterface` |

Only trusted editors should receive roles covered by the access checker.

## Operational guidance

- Audit which users receive `ROLE_EDITOR` or custom-checker access.
- Review Twig overrides that use `|raw` for widget output.
- Use `doctrine.table_prefix` when multiple apps share one schema.
- Leave `allow_unauthenticated: false` in production.
- Never commit `.env` secrets or demo credentials into production configs.

## Release security checklist

| Item | Notes |
| --- | --- |
| `SECURITY.md` | Current and linked from the README |
| Admin access | Documented and tested |
| CSRF | Document API POST flows require valid token |
| HTML sanitize | Production strategy documented |
| Demo config | Demo-only shortcuts separated from production guidance |
| Dependencies | `composer audit` reviewed with QA |

See also [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

## Secrets

Never commit application secrets, `.env` files with credentials, or private keys. Use `.env.example` templates only.
