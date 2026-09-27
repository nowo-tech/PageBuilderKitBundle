# Spec-driven development

## Table of contents

- [Three layers](#three-layers)
- [User stories](#user-stories)
- [Functional scope (Phase 1)](#functional-scope-phase-1)
- [Non-goals and planned phases](#non-goals-and-planned-phases)
- [Roadmap (Phases 2–4)](#roadmap-phases-2-4)
- [Validating the spec](#validating-the-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## Three layers

In this repository, spec-driven development has three layers that stay in sync:

1. **GitHub Spec Kit baseline**: `specs/001-baseline/` documents Phase 1 behavior and maps production files under `src/`.
2. **Product behavior**: Canvas admin, document API, widgets, i18n props, publish flow, and public rendering are documented in integrator docs.
3. **Traceability anchors**: Stable `REQ-*` identifiers link docs, demo expectations, and QA workflows.

## User stories

| ID | Story |
| --- | --- |
| US-01 | As an editor, I create pages with stable keys and open a visual canvas to compose sections and widgets |
| US-02 | As an editor, I save locale-specific widget content and publish pages when ready |
| US-03 | As a developer, I render a published page tree through `PageRenderProvider` or Twig |
| US-04 | As an integrator, I configure locales, editor access, admin shell, and HTML sanitization |
| US-05 | As a maintainer, I run the Symfony 8 FrankenPHP demo and QA checks to verify the bundle boots cleanly |
| US-06 | As a maintainer, I understand the roadmap for nesting, revisions, and external widgets without surprise scope creep |

## Functional scope (Phase 1)

**In scope (shipped):**

- Document schema v1: sections, columns, widgets
- Six core widget types and public Twig templates
- Admin list, create form, canvas UI, JSON document API with CSRF
- Publish status and public `/p/{pageKey}` rendering
- Locale-aware widget props with default-locale fallback
- Doctrine persistence, optional table prefix, widget type registry
- Security access checker and HTML sanitize strategies
- FrankenPHP demo on port **8137**

**Non-goals for Phase 1:** nested sections, revision UI, page templates library, third-party widget marketplace.

## Non-goals and planned phases

Phases 2–4 are **planned**, not part of the 1.0 contract. The `BuilderPageRevision` entity exists as a persistence hook for Phase 3 but is not exposed in the admin UI in Phase 1.

## Roadmap (Phases 2–4)

### Phase 2 — GrapesJS canvas (shipped)

- Admin canvas migrated to **GrapesJS** (CDN) with block manager, devices, style manager
- Document schema **v2**: `engine: grapesjs`, `html`, `css`, `grapes`, `localeContent`
- Public render of sanitized HTML/CSS; `grapesjs.allow_scripts` gated
- Legacy schema **v1** (sections/columns/widgets) remains readable and renderable
- Nesting, custom HTML, and Elementor-like style/advanced via GrapesJS native panels

**Success criteria:** editors compose free-form layouts per locale; public `/p/{pageKey}` renders Grapes HTML/CSS; classic documents still render.

### Phase 3 — Revisions and templates (planned)

- User-facing revision history using `BuilderPageRevision` (list, diff preview, restore)
- Named page templates (duplicate structure + starter props)
- Import/export of documents between environments

**Success criteria (draft):** editors can roll back to a prior revision; templates accelerate new page creation.

### Phase 4 — External widgets (planned)

- Formal widget pack registration (Composer packages or host modules)
- Versioned widget metadata, capability flags, and sandboxed defaults
- Documentation for third-party widget authors

**Success criteria (draft):** a host can ship a custom widget type without forking the bundle core.

## Validating the spec

```bash
make test
make phpstan
make validate-translations
make demo-smoke
make release-check
```

## Requirement identifiers (`REQ-*`)

| ID | Where | What it marks |
| --- | --- | --- |
| REQ-DOCS-002 | `README.md` | Canonical documentation link order |
| REQ-DOCS-018 | `README.md` | Root README structure for bundle docs |
| REQ-UI-002 | `docs/SECURITY.md`, admin subscriber | Admin routes require editor access unless explicitly relaxed |
| REQ-TWIG-001 | `TwigPathsPass` | Host Twig overrides win over bundle templates |
| REQ-TWIG-004 | `docs/INSTALLATION.md`, demo | Twig Extra requirement is documented |
| REQ-TEST-011 | `Makefile` `demo-smoke` | Demo boots and returns HTTP 200 (port 8137) |
| REQ-MAKE-002 | `Makefile` `release-check` | Pre-release QA chain |
| REQ-MAKE-004 | `Makefile` `validate-translations` | Translation parity validation hook |
| REQ-GIT-001 | `docs/GITHUB_CI.md` | No Cursor co-author trailers in git history |
| REQ-CS-007 | `docs/PSR.md` | PSR adoption evaluation |

## Suggested workflow for contributors

1. Clarify the behavior change or bug (check roadmap: is it Phase 1 or a later phase?).
2. Update or create the relevant spec artifact.
3. Implement with tests when production behavior changes.
4. Update integrator docs when host applications must act.
5. Keep `specs/001-baseline/spec.md` and `code-inventory.md` aligned with `src/`.

## GitHub Spec Kit (summary)

| Artifact | Path |
| --- | --- |
| Baseline spec | `specs/001-baseline/spec.md` |
| Code inventory | `specs/001-baseline/code-inventory.md` |
| Tooling manual | `docs/SPEC-KIT.md` |

See [SPEC-KIT.md](SPEC-KIT.md) for install and Cursor skills.

## See also

- [SPEC-KIT.md](SPEC-KIT.md)
- [INSTALLATION.md](INSTALLATION.md)
- [CONFIGURATION.md](CONFIGURATION.md)
- [USAGE.md](USAGE.md)
- [SECURITY.md](SECURITY.md)
