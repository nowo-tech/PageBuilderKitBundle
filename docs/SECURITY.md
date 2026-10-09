# Security

Security considerations for page builder admin, document API, and public rendering.

## Table of contents

- [Threat model](#threat-model)
- [Admin access guard (REQ-UI-002)](#admin-access-guard-req-ui-002)
- [Document API and CSRF](#document-api-and-csrf)
- [Rich text and HTML widgets](#rich-text-and-html-widgets)
- [Public HTML final hardening](#public-html-final-hardening)
- [Grapes Twig fallback](#grapes-twig-fallback)
- [Content Security Policy (nonce)](#content-security-policy-nonce)
- [Operational guidance](#operational-guidance)
- [Release security checklist](#release-security-checklist)
- [REQ-SEC-004 (AI security audit)](#req-sec-004-ai-security-audit)

## Threat model

| Risk | Mitigation |
| --- | --- |
| Unauthorized users reach admin or document API | `access_roles` / `layout_roles` / `content_roles` / `publish_roles` / `templates_roles`, custom `access_checker` (guard), Symfony Security (REQ-UI-002) |
| CSRF on document save or publish | `X-CSRF-TOKEN` validated against intention `page_builder_document` |
| CSRF on content field forms | `_csrf_token` intention `page_builder_content` |
| Stored XSS through `text` / `html` widgets / richtext fields | Twig auto-escaping; default `html.sanitize: allowlist` (DOM tag/attr allowlist); Grapes sanitizer on persist/render; always-on HTML5 `PublicHtmlHardener` before output |
| XSS via Grapes Twig errors / decoded Twig tokens | Twig source (entity-decoded tokens) is never printed; fallback keeps tokens encoded |
| Inline script injection when a CSP is enforced | Kit `<script>` / `<style>` carry `csp_nonce`; no inline event handlers |
| Layout vs content privilege escalation | Capabilities via roles or custom `PageBuilderKitAccessCheckerInterface`; `PageBuilderKitAccessGuard` in controllers |
| Overly broad demo access | `allow_unauthenticated` defaults to `false` |
| Shared-database table collisions | Optional `doctrine.table_prefix` |

## Admin access guard (REQ-UI-002)

The bundle protects route names beginning with `admin_page_builder_` via `PageBuilderKitAdminAccessSubscriber` (`canAccess()` then capability).

**Two options** (same as BlogKit / MarketingKit):

1. **Roles** — configure `access_roles` (shortcut for all) and optional `layout_roles` / `content_roles` / `publish_roles` / `templates_roles`.
2. **Custom guard** — implement `PageBuilderKitAccessCheckerInterface` and set `security.access_checker`. Controllers may also inject `PageBuilderKitAccessGuard`.

Default configuration:

```yaml
nowo_page_builder_kit:
    security:
        access_roles: [ROLE_EDITOR]
        layout_roles: [ROLE_EDITOR]
        content_roles: [ROLE_EDITOR]
        publish_roles: [ROLE_EDITOR]
        templates_roles: [ROLE_EDITOR]
        allow_unauthenticated: false
```

Twig helpers: `nowo_page_builder_can_edit()`, `nowo_page_builder_can('layout')`.

Recommended host access control (match `web_ui.path_prefix`, default `/admin/page-builder`):

```yaml
# config/packages/security.yaml
security:
    access_control:
        - { path: ^/admin/page-builder, roles: ROLE_EDITOR }
        # if you customized web_ui.path_prefix: /cms →
        # - { path: ^/cms, roles: ROLE_EDITOR }
```

For context-aware rules, implement `PageBuilderKitAccessCheckerInterface` and set `security.access_checker`.

## Document API and CSRF

Save and publish endpoints reject requests when the CSRF token is missing or invalid (`invalid_csrf` JSON, HTTP 403).

Recommendations:

- Do not expose admin routes outside authenticated editor sessions.
- When customizing the canvas client, keep sending `X-CSRF-TOKEN` for POST requests.
- Do not disable Symfony CSRF protection for document routes.

## Rich text and HTML widgets

Widgets `text` and `html` may store editor-authored HTML. The **default** strategy is `allowlist`. Opt out only for fully trusted staff editors:

```yaml
nowo_page_builder_kit:
    html:
        sanitize:
            strategy: none   # trusted editors only
```

| Strategy | Behaviour |
| --- | --- |
| `none` | Trusted editors only; HTML stored/rendered as-is (opt-in; not the default) |
| `allowlist` (default) | DOM allowlist via `AllowlistPageBuilderHtmlSanitizer`; drops `script` / `style` / `iframe` / `object` / `embed` / `link` / `meta` / `svg` with content, removes `on*` handlers, and rejects `javascript:` / `vbscript:` / non-image `data:` URLs (after stripping whitespace / control characters) |
| `strip` | Remove all tags |
| `service` | Host `PageBuilderHtmlSanitizerInterface` |

Only trusted editors should receive roles covered by the access checker.

## Public HTML final hardening

`Nowo\PageBuilderKitBundle\Html\PublicHtmlHardener` is the **always-on last step** before editor HTML is printed:

- `PageRenderProvider` hardens Grapes `html` (after Twig and `[[fields.*]]` slots) and `css`;
- kit templates use `|pbk_harden_html` (public page / `/p/{pageKey}`, classic `text` / `html` widgets, inline HTML fields) and `|pbk_harden_css` (Grapes `<style>`).

It parses with PHP 8.4 `Dom\HTMLDocument` (the HTML5 algorithm browsers use) and always re-serializes, so there is no parser differential with libxml-based sanitizers (`&colon;` / `&Tab;` entities, `<!-->` comments, `<xmp>` / `<noembed>` / `<noscript>` raw text, `<svg/onload>` …). It removes:

| Removed | Items |
| --- | --- |
| Elements (with content) | `script`, `style`, `iframe`, `frame(set)`, `object`, `embed`, `applet`, `base`, `meta`, `link`, `form`, `math`, `portal`, `noscript`, `template`, `xmp`, `noembed`, `noframes`, `plaintext`, SVG `animate*`, `set`, `foreignObject`, `handler`, `listener` |
| Attributes | `on*`, `formaction`, `srcdoc`, `action` |
| URL values | `javascript:` / `vbscript:` / non-image `data:` in `href`, `src`, `xlink:href`, `srcset` (each candidate), `poster`, `data-src`, SVG `values`/`from`/`to`/`by`, … (ASCII whitespace/control chars ignored) |

CSS: every `<` becomes the CSS escape `\3C `, so nested `</sty</stylele>` can never close the element. It runs regardless of `html.sanitize.strategy` (also `none`). `grapesjs.allow_scripts: true` keeps `<script>` elements only. The pass is idempotent. PHP 8.4 is the bundle minimum, so there is no non-HTML5 fallback.

Use the filters in host overrides that print editor HTML: `{{ html|pbk_harden_html }}`, `<style>{{ css|pbk_harden_css }}</style>`.

## Grapes Twig fallback

`GrapesDocumentSanitizer::sanitizeHtml()` decodes HTML/URL encoding inside `{{ }}`, `{% %}` and `{# #}` so the sandbox can compile editor templates; that string is Twig **source**, not safe HTML. Since v1.6.0 `GrapesTwigRenderer` never returns it: on a Twig error (or with Twig disabled) it returns `sanitizeHtml($html, false)` — tokens remain encoded, inert text — and Twig output is re-sanitized without restoring delimiters. Before v1.6.0 `{{ &lt;script&gt;… }}` with a syntax error rendered a live `<script>`.

## Content Security Policy (nonce)

Nowo-tech kit convention: when the request attribute `csp_nonce` is set, every `<script>` / `<style>` emitted by kit templates carries `nonce="…"`:

```twig
{% set _csp_nonce = app.request is defined and app.request ? app.request.attributes.get('csp_nonce')|default('') : '' %}
<script{% if _csp_nonce %} nonce="{{ _csp_nonce }}"{% endif %}>…</script>
```

The kit templates contain **no inline event handlers**: confirmations use `form[data-pbk-confirm]` + `js/page-builder-admin.js`, and the inline-edit modal form is blocked by a `submit` listener in `js/page-builder-inline-edit.js`. Host sketch:

```php
#[AsEventListener(KernelEvents::REQUEST, priority: 512)]
public function onRequest(RequestEvent $event): void
{
    $event->getRequest()->attributes->set('csp_nonce', base64_encode(random_bytes(16)));
}
// …then emit "script-src 'self' 'nonce-…'; style-src 'self' 'nonce-…'" on kernel.response.
```

Element `style="…"` attributes from GrapesJS still require `style-src-attr 'unsafe-inline'` (or `'unsafe-hashes'`).

## Operational guidance

- Audit which users receive `ROLE_EDITOR` or custom-checker access.
- Review Twig overrides that use `|raw` for widget output — use `|pbk_harden_html` / `|pbk_harden_css`.
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

## REQ-SEC-004 (AI security audit)

| Field | Value |
|-------|--------|
| **Date** | 2026-09-27 |
| **Method** | Cursor agent static pass (`src/`, Flex recipe, demo, this doc + `.github/SECURITY.md`) |
| **Overall risk** | **Low** |
| **Grade** | **Pass (good)** |
| **Record** | This document (in-package). Monorepo catalog: `BUNDLES_SECURITY_ANALYSIS.md` §4 / §7.2 / Appendix Z |

No Critical/High findings. Residuals: do not set `html.sanitize.strategy: none` for UGC or broad editor roles; host firewall on admin path prefix; keep CSRF on document API.

## Secrets

Never commit application secrets, `.env` files with credentials, or private keys. Use `.env.example` templates only.
