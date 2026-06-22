# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Changed

- **`click()` / `press()` prefer an interactive target.** When more than one
  element matches, action verbs now pick the interactive candidate (button,
  link, input, …) instead of letting a non-interactive node — e.g. a heading
  sharing a button's text — shadow it and silently no-op (#72). When nothing
  interactive matches, the first match still wins.

### Added

- **`select()` matches an option by its visible label or its value** (#73), so
  tests can pick "United Kingdom" without scraping an opaque option value first.
  `selectByValue()` keeps value-only selection; both throw
  `OptionNotFoundException` when no option matches.
- **Form verbs fail loudly on controls they can't drive** (#77). `fill()` /
  `type()` / `clear()` require an `<input>`/`<textarea>`, `select()` a
  `<select>`, and `check()` / `uncheck()` a checkbox/radio — otherwise they
  throw `UndrivableElementException` naming the resolved element, rather than
  silently doing nothing and surfacing later at an unrelated assertion.

## [0.1.0] - 2026-06-22

First tagged release. Beta and pre-1.0 — the public API may still change before
1.0 (see [`docs/compatibility.md`](docs/compatibility.md)). Browser testing with
PHPUnit 12/13 and Firefox on Linux and macOS, over WebDriver BiDi, with a
first-class Laravel integration.

### Changed

- Renamed `Browser::waitForLocation()` to `waitForPath()`, for consistency with
  `assertPathIs()` / `currentPath()` (pre-1.0 API review).

### Added

- **`Browser::within($target, $callback)`** — run a callback against a browser
  scoped to inside a container element, so element resolution *and* text
  assertions only match within it (sibling components with identical text are
  disambiguated). Backed by BiDi `startNodes`; the selector engine's generated
  XPath is now relative (`.//`) so it scopes correctly and still matches
  document-wide when unscoped.
- **Compatibility & supported-surface docs** (`docs/compatibility.md`):
  supported PHP/PHPUnit/Firefox/OS, the public API vs internals, and the
  pre-1.0 versioning policy.

- Project scaffolding: Composer package `vusys/tetryon`, PHPStan level 9, Pint,
  Rector, Infection, PHPUnit 12/13 config, CI matrix, Dependabot, CodeRabbit,
  and OpenSSF Scorecard.
- `Vusys\Tetryon\Core\Config\Timeouts` and `Viewport` immutable value objects.
- **Firefox WebDriver BiDi driver (v0.1 spike).** Direct, dependency-free
  control of headless Firefox: process launch with a throwaway profile and
  PID-only teardown, a hand-rolled WebSocket transport (`WebSocketClient`,
  RFC 6455), the BiDi protocol layer (`BiDiConnection`) with id correlation,
  event buffering, a structured command trace, and PSR-3 logging. The
  `FirefoxBiDiDriver` exposes navigate / evaluate JS / screenshot / console
  capture. Proven end-to-end against real Firefox in an opt-in Browser suite
  plus a Firefox CI workflow (Linux + macOS).
- **PHPUnit browser API (v0.2).** `BrowserTestCase` + `InteractsWithBrowser`
  trait expose a fluent `$this->browser()`: navigation (`visit`/`back`/
  `forward`/`refresh`), a human-readable selector engine (test attributes →
  label → accessible name → placeholder → button/link text → name → id →
  visible text, with `@`/css/xpath escape hatches), interaction
  (`click`/`press`/`doubleClick`/`rightClick`/`hover`/`fill`/`type`/`clear`/
  `select`/`check`/`uncheck`/`choose`/`upload`/`pressKey`/`value`),
  and assertions (`assertSee`/`assertDontSee`/`assertUrlIs`/`assertPathIs`/
  `assertTitleIs`/`assertValue`/`assertVisible`/`assertMissing`/
  `assertTextNear`). Configured via `Configuration` (env or array).
- **Auto-waiting.** Every action waits for its target to be actionable
  (present, visible, enabled) and every assertion retries until it passes or
  the configured timeout elapses — so tests never need a manual `sleep()`.
  Explicit `waitForText` / `waitUntilMissing` / `waitForUrl` / `waitForLocation`
  are available too. Backed by an injectable-clock `Waiter` (unit-tested).
- **Failure diagnostics.** When a browser test fails, Tetryon captures a
  screenshot, the page HTML, the current URL, console logs, the BiDi command
  trace, browser stderr, and the viewport into a per-test artifact directory
  (`tests/Browser/Artifacts` by default), and prints a report pointing at
  them. Good errors are the product.
- **First-class logging.** The BiDi layer logs every command/response/event to
  an optional PSR-3 logger and records a structured command trace. Set
  `TETRYON_DEBUG` to stream the log to stderr (via the bundled `StreamLogger`),
  override `browserLogger()` to plug in your own, and read `$browser->trace()`
  to inspect what the browser did.
- **Laravel integration (v0.4).** Auto-discovered `TetryonServiceProvider`
  (merges + publishes `config/tetryon.php`), the `tetryon:install` /
  `tetryon:doctor` / `tetryon:serve` artisan commands, a self-booting
  `Laravel\BrowserTestCase` (factories, DB, and `$this->browser()` wired from
  `config('tetryon')`), and `loginAs()` via a session-aware route registered
  only in local/testing. Laravel remains optional — the core never requires it.
- **Natural-language steps (v0.3).** A small, deterministic grammar that lives
  inside PHPUnit — `$browser->step('I fill "Email" with "x"')` and
  `$this->scenario()->given(...)->when(...)->then(...)` compile to the same
  fluent calls. No `.feature` files, no separate runner, no AI; unknown
  sentences throw. See `docs/natural-language.md`.
- **`tetryon doctor` CLI.** `vendor/bin/tetryon doctor` runs preflight checks
  — PHP, required extensions, Firefox, a real headless launch + BiDi handshake,
  and a writable artifact directory — and prints a report with fix hints,
  exiting non-zero if anything is wrong.

[Unreleased]: https://github.com/Vusys/tetryon/compare/v0.1.0...master
[0.1.0]: https://github.com/Vusys/tetryon/releases/tag/v0.1.0
