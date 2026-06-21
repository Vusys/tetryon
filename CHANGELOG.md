# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

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
- **`tetryon doctor` CLI.** `vendor/bin/tetryon doctor` runs preflight checks
  — PHP, required extensions, Firefox, a real headless launch + BiDi handshake,
  and a writable artifact directory — and prints a report with fix hints,
  exiting non-zero if anything is wrong.

[Unreleased]: https://github.com/Vusys/tetryon/commits/master
