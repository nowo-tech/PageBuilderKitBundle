# PHP-FIG PSR evaluation (REQ-CS-007)

Package: `nowo-tech/page-builder-kit-bundle` (`symfony-bundle`)

This document records which [PHP-FIG PSRs](https://www.php-fig.org/psr/) apply to this package.
Only contracts that add clear interoperability or maintainability value are **Adopted**.
Others are **N/A** (or already covered by Symfony) so the decision stays auditable.

## Baseline (always)

| PSR | Decision | How |
| --- | -------- | --- |
| PSR-12 (coding style) | **Adopted** | `@PSR12` in `.php-cs-fixer.dist.php` (Nowo REQ-CS-001). |
| PSR-4 (autoloading) | **Adopted** | `composer.json` `autoload` / `autoload-dev` PSR-4 map for package sources and tests. |

## Interface / contract PSRs

| PSR | Decision | Notes |
| --- | -------- | ----- |
| PSR-3 Logger | **N/A** | No public logger SPI; do not add `psr/log` without type-hints. |
| PSR-6 / PSR-16 Cache | **N/A** | No package-owned cache layer. |
| PSR-7 / PSR-17 HTTP messages | **N/A** | Symfony HttpFoundation is the surface. |
| PSR-18 HTTP client | **N/A** | No outbound HTTP client. |
| PSR-11 Container | **N/A** | Constructor injection only in public API. |
| PSR-14 Event dispatcher | **Already satisfied via Symfony** | Event subscribers use Symfony contracts. |
| PSR-15 HTTP middleware | **N/A** | Kernel subscribers only. |
| PSR-20 Clock | **N/A** | No clock SPI required. |

## Summary

- **Adopted beyond baseline:** none (baseline PSR-12 + PSR-4 only).
- **Rule:** do not add `psr/*` Composer dependencies without matching type-hints and DI wiring.
- **Re-evaluate** when the package gains logging, HTTP, cache, clock, or event SPIs.

---

_REQ-CS-007 evaluation date: 2026-09-25._
