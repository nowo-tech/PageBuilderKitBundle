# Spec-driven development

## Table of contents

- [Three layers](#three-layers)
- [User stories](#user-stories)
- [Functional scope (shipped)](#functional-scope-shipped)
- [Roadmap](#roadmap)
- [Validating the spec](#validating-the-spec)
- [Requirement identifiers (`REQ-*`)](#requirement-identifiers-req-)
- [Suggested workflow for contributors](#suggested-workflow-for-contributors)
- [GitHub Spec Kit (summary)](#github-spec-kit-summary)
- [See also](#see-also)

## Three layers

In this repository, spec-driven development has three layers that stay in sync:

1. **GitHub Spec Kit**: `specs/001-baseline/` (Phase 1), `002`–`004` feature specs (Phases 2–4), plus canonical `code-inventory.md` under `001-baseline/`.
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
| US-07 | As a developer, I inspect page-builder render/admin activity in the Web Profiler toolbar in `dev` |

## Functional scope (shipped)

**Shipped in v1.0+:**

- Document schema v1 (classic) and v2 (GrapesJS)
- Core classic widget types + public Twig templates
- Admin list, create, canvas, sections, SEO, document JSON API with CSRF
- Publish / unpublish (draft), public `/p/{pageKey}`
- Draft **preview** for users who pass `PageBuilderKitAccessCheckerInterface` (banner; anonymous visitors still get 404)
- Revisions (`revisions.*`): list, create, restore, **diff preview**
- Page **duplicate**, JSON **export/import**, **templates** library (`BuilderPageTemplate`)
- Locale-aware props / Grapes `localeContent`, Doctrine optional table prefix
- Security access checker, HTML sanitize strategies, Twig-in-Grapes sandbox
- Web Profiler **DataCollector** (`debug.collector`, `kernel.debug` only)
- External **widget packs** (`WidgetPackInterface` + docs)
- FrankenPHP demo on port **8137**

## Roadmap

### Phase 2 — GrapesJS canvas (shipped)

See ARCHITECTURE / USAGE. Success criteria met in v1.0.0.

Spec Kit: [`specs/002-phase2-grapesjs/spec.md`](../specs/002-phase2-grapesjs/spec.md).

### Phase 3 — Revisions and templates (shipped)

- Revision history UI + restore + diff preview
- Duplicate page, import/export JSON (`formatVersion: 1`)
- Named page templates (save from page → create draft from template)

Spec Kit: [`specs/003-phase3-revisions-templates/spec.md`](../specs/003-phase3-revisions-templates/spec.md).

### Phase 4 — External widgets (shipped baseline)

- `WidgetPackInterface` + `WidgetPackRegistry` + compiler pass
- Author guide: [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md)
- Classic widget types remain the extension point for schema v1; Grapes block packs ship reusable BlockManager entries (see follow-ups)

Spec Kit: [`specs/004-phase4-external-widgets/spec.md`](../specs/004-phase4-external-widgets/spec.md).

### Follow-ups (optional)

Shipped in **v1.2.0**:

- Richer visual HTML/CSS side-by-side in revision diff (`DocumentDiff` panels + admin UI)
- Template JSON export/import for sharing across projects (`PageTemplateService::export` / `import`)
- Grapes block packs analogous to classic widget packs (`GrapesBlockPackInterface` + canvas `blockPacks`)

### Phase 5 — Content fields + capabilities (MVP shipped)

Typed CMS fields on documents (`structure.fields` + `structure.fieldValues[locale]`), Twig `fields.*` inject, admin content forms, and fine-grained access via **roles** (`layout_roles`…) **or** a custom **`access_checker` guard** (`PageBuilderKitAccessCheckerInterface` / `PageBuilderKitAccessGuard`). Classic schema v1 remains **legacy**.

Spec Kit: [`specs/005-content-fields-i18n/spec.md`](../specs/005-content-fields-i18n/spec.md).

### Phase 6 — Content hardening (MVP shipped)

Composite field types (`repeater` / `group` / `reference`), required validation on publish, and template apply options for field schema/values.

Spec Kit: [`specs/006-phase6-content-hardening/spec.md`](../specs/006-phase6-content-hardening/spec.md).

### Phase 6b — Content wave 2 (MVP shipped)

Nesting depth 2, Twig-free `[[fields.*]]` slots, Grapes Content-fields blocks, image upload + page reference pickers.

Spec Kit: [`specs/007-phase6b-content-wave2/spec.md`](../specs/007-phase6b-content-wave2/spec.md).

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
| REQ-DEBUG-001 | `docs/CONFIGURATION.md`, DataCollector | Web Profiler panel when `debug.collector` + `kernel.debug` |

## Suggested workflow for contributors

1. Clarify the behavior change or bug (check roadmap: is it already shipped?).
2. Update or create the relevant spec artifact.
3. Implement with tests when production behavior changes.
4. Update integrator docs when host applications must act.
5. Keep phase specs (`001`–`005`) and `specs/001-baseline/code-inventory.md` aligned with `src/`.

## GitHub Spec Kit (summary)

| Artifact | Path |
| --- | --- |
| Phase 1 baseline | `specs/001-baseline/spec.md` |
| Code inventory (all phases) | `specs/001-baseline/code-inventory.md` |
| Phase 2 GrapesJS | `specs/002-phase2-grapesjs/spec.md` |
| Phase 3 revisions/templates | `specs/003-phase3-revisions-templates/spec.md` |
| Phase 4 external widgets | `specs/004-phase4-external-widgets/spec.md` |
| Phase 5 content fields + ACL | `specs/005-content-fields-i18n/spec.md` |
| Tooling manual | `docs/SPEC-KIT.md` |

See [SPEC-KIT.md](SPEC-KIT.md) for install and Cursor skills.

## See also

- [ARCHITECTURE.md](ARCHITECTURE.md) — Mermaid diagrams
- [WIDGET_AUTHORS.md](WIDGET_AUTHORS.md) — external classic widgets
- [SPEC-KIT.md](SPEC-KIT.md)
- [INSTALLATION.md](INSTALLATION.md)
- [CONFIGURATION.md](CONFIGURATION.md)
- [USAGE.md](USAGE.md)
- [SECURITY.md](SECURITY.md)
