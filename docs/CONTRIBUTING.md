# Contributing Guide

Thank you for contributing to **Page Builder Kit Bundle**.

## Table of contents

- [Code of Conduct](#code-of-conduct)
- [Reporting Bugs and Gaps](#reporting-bugs-and-gaps)
- [Submitting Changes](#submitting-changes)
- [Development setup](#development-setup)
- [Quality gates](#quality-gates)
- [Project structure](#project-structure)
- [Demo](#demo)
- [Questions](#questions)

## Code of Conduct

Please follow [CODE_OF_CONDUCT.md](../CODE_OF_CONDUCT.md). Report unacceptable behavior to `hectorfranco@nowo.tech`.

## Reporting Bugs and Gaps

Open a GitHub issue with:

- A clear description of the problem
- Reproduction steps
- Expected vs actual behavior
- PHP, Symfony, and bundle versions
- Relevant `nowo_page_builder_kit` configuration
- Whether the issue affects admin canvas, document API, or public rendering

## Submitting Changes

1. Fork the repository and create a branch from `main`.
2. Make the smallest coherent change you can.
3. Update docs when integrator-visible behavior changes.
4. Update `specs/001-baseline/` whenever `src/` changes.
5. Open a pull request against `main`.

## Development setup

```bash
# Playwright e2e + README widget screenshots (REQ-DEMO-013)
make -C demo/symfony8 test-e2e
make -C demo/symfony8 demo-screenshots
git clone https://github.com/your-username/PageBuilderKitBundle.git
cd PageBuilderKitBundle
make up
make setup-hooks
```

Useful commands:

```bash
make test
make phpstan
make validate-translations
make release-check
```

If CI reports Cursor co-author trailers in git history, run:

```bash
make check-no-cursor-coauthor
make strip-cursor-coauthor-from-history
```

See [GITHUB_CI.md](GITHUB_CI.md).

## Quality gates

Before opening a PR, aim to pass:

- `make test`
- `make test-coverage` (≥99% element coverage, same gate as CI)
- `make phpstan`
- `make validate-translations`
- `make release-check`

## Project structure

```text
PageBuilderKitBundle/
├── src/                    # Bundle source code
│   ├── Controller/         # Admin + public controllers
│   ├── Entity/             # Doctrine model
│   ├── Service/            # DocumentService, PageRenderProvider, …
│   ├── Widget/             # Widget types and registry
│   └── Resources/          # Twig, translations, assets, config
├── tests/                  # PHPUnit tests
├── demo/symfony8/          # FrankenPHP Symfony 8 demo (port 8137)
├── docs/                   # Integrator documentation
└── specs/001-baseline/     # Spec Kit baseline
```

## Demo

```bash
make -C demo/symfony8 up
```

Default URL: `http://localhost:8137`. Login in the demo: **`admin`** / **`admin`**.

## Questions

Open a GitHub Discussion or issue, or contact `hectorfranco@nowo.tech`.
