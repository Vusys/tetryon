# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with
code in this repository.

## What this package is

`vusys/tetryon` — a PHP-native browser testing package for PHPUnit. Firefox-first
and PHPUnit-first. Targets **PHP 8.4+** and **PHPUnit 12 / 13**. Library code
only. Pre-1.0, so backwards-compat breaks are acceptable when called out.

The mantra: **PHPUnit. Firefox. WebDriver BiDi. Composer install. Useful errors.
No faff.**

Key positioning (see `start.md` for the full spec):

- **PHPUnit only.** No Pest, Behat, Codeception, Gherkin, or a custom runner.
  Tests are normal PHPUnit tests; the package owns browser automation, PHPUnit
  owns everything else.
- **Firefox only for v1.** WebDriver BiDi is the primary control protocol —
  direct control, minimal moving parts. No Node, Playwright, Selenium Server,
  ChromeDriver, or CDP-as-core. geckodriver is a fallback of last resort only.
- **Bring your own server.** The package never serves the app; it points a
  browser at a configured `base_url`.
- **Auto-wait by default.** Every action waits until the target is actionable;
  every assertion retries until timeout. Never encourage `sleep()`.
- **Failure diagnostics are a feature**, not polish — screenshot, HTML, URL,
  console logs, last actions, and the selector-resolution trace on every failure.

## Commands

```bash
composer check           # Pint --test → Rector --dry-run → PHPStan level 9, halt on first failure. Run before pushing.
composer test            # PHPUnit default (Unit) suite — pure PHP, no browser
composer test:browser    # opt-in Firefox-backed Browser suite (needs Firefox + base URL)
composer analyse         # PHPStan level 9, NO baseline allowed
composer pint:check      # Laravel Pint, --test mode
composer rector:check    # Rector --dry-run
composer infection       # mutation testing
composer test:coverage   # XDEBUG_MODE=coverage phpunit --coverage-text
```

`composer check` is the static-analysis gate — it does **not** run tests. Pair
it with `composer test` for full local validation before pushing.

Run a single test:

```bash
vendor/bin/phpunit --filter test_method_name
vendor/bin/phpunit tests/Unit/Core/Config/TimeoutsTest.php
vendor/bin/phpunit --testsuite Browser
```

## Architecture (target)

Single Composer package now; sub-namespaces mirror the eventual package split
(extraction is a post-1.0 concern — nothing is published yet):

- `Vusys\Tetryon\Core` — framework-agnostic browser API, value objects,
  selector resolution, waiting, failure reports. No PHPUnit or Laravel coupling.
- `Vusys\Tetryon\Firefox` — the `FirefoxBiDiDriver` and WebDriver BiDi transport.
- `Vusys\Tetryon\PHPUnit` — `BrowserTestCase`, the `InteractsWithBrowser` trait,
  artifact capture wired into PHPUnit lifecycle hooks.
- `Vusys\Tetryon\Laravel` — optional service provider, `tetryon:*` commands,
  `loginAs()` helper. Laravel must never be required by the core.

Core objects from the spec: `Browser`, `Page`, `Locator`, `Command`, `Scenario`,
`StepParser`, `ArtifactBag`, `FailureReport`, `FirefoxBiDiDriver`. Keep
interfaces minimal — the implementation can be proudly Firefox-specific; the
**public** API avoids Firefox-specific names. Do not over-abstract for imaginary
future browsers.

### Selector strategy

Tests read like user behaviour (`->fill('Email', ...)`, `->press('Save')`), not
CSS. Element lookup order: explicit selector → configured test attributes
(`data-testid`/`data-test`/`data-cy`) → label text → accessible name/ARIA →
placeholder → button text → link text → input `name` → input `id` → visible
text. Explicit selectors use `@name`, `[data-testid="..."]`, or `#id`.

## Code conventions

- **`declare(strict_types=1);`** in every file.
- **PHPStan level 9, no baseline, no `@phpstan-ignore`.** Fix the type instead.
- **No code comments unless WHY is non-obvious.** Never comment WHAT.
- Laravel Pint enforces style — run `composer pint` to fix.
- Immutable value objects are `final readonly`; validate in the constructor.

## Tests

PHPUnit 12/13. Two suites (see `phpunit.xml`):

- **Unit** (default) — pure-PHP, no browser. Extend `PHPUnit\Framework\TestCase`.
  Use `#[CoversClass]` attributes.
- **Browser** (`tests/Browser`, opt-in) — drives a real Firefox over BiDi.
  Needs Firefox installed and a reachable `TETRYON_BASE_URL`. Not run on the
  default `composer test`; gated separately in CI.

## Things to avoid

- Adding `@phpstan-ignore` comments or a PHPStan baseline (disallowed in
  `.coderabbit.yaml`). Fix the type.
- Encouraging `sleep()` anywhere in the public API or docs — auto-wait instead.
- Pulling Laravel (or any framework) into `Core` or `Firefox` namespaces.
- Building a competing test runner or a `tetryon test` command — tests run
  through PHPUnit.
- Adding Chrome/CDP/Playwright/Selenium code paths — out of scope for v1.

## Reference

- `start.md` — the full product spec and MVP roadmap. Treat as source of truth.
- GitHub milestones v0.1–v1.0 track the roadmap; issues map to roadmap items.
