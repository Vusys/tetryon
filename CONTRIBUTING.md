# Contributing

Thanks for considering a contribution. Tetryon is a browser-testing library for
PHPUnit. Issues and PRs are most useful when they ship with tests against the
package's own suite.

## Quick start

```bash
composer install
composer test          # default Unit suite (no browser needed)
composer analyse       # PHPStan level 9, no baseline allowed
composer pint:check    # Laravel Pint style check
composer rector:check  # Rector dry-run
composer check         # all three static gates, halts on first failure
```

Open issues live at <https://github.com/Vusys/tetryon/issues>. Pre-1.0, so
backwards-compat breaks are acceptable when called out.

## Running browser tests locally

The `Browser` suite drives a real Firefox over WebDriver BiDi, so it is opt-in
and not part of the default `composer test` run:

```bash
# 1. Start whatever app you want to test against, e.g.:
php -S 127.0.0.1:8000 -t tests/Fixtures/static-site

# 2. Point Tetryon at it and run the Browser suite:
TETRYON_BASE_URL=http://127.0.0.1:8000 composer test:browser
```

Prerequisites: a Firefox install on `PATH` (stable or ESR), on macOS or Linux.
`TETRYON_HEADLESS=false` runs headed for debugging.

## Other useful commands

```bash
composer test:coverage           # XDEBUG_MODE=coverage phpunit --coverage-text
composer infection               # mutation testing
vendor/bin/phpunit --filter <name>           # one test by name
vendor/bin/phpunit tests/Unit/<File>.php     # one file
vendor/bin/phpunit --testsuite Browser       # opt-in browser suite
```

See [`CLAUDE.md`](CLAUDE.md) for project-level architecture notes.

## Changelog

User-facing changes go in [`CHANGELOG.md`](CHANGELOG.md) under **Unreleased**
(Keep a Changelog format). Call out any pre-1.0 backwards-compatibility break
under **Changed** / **Removed**.
