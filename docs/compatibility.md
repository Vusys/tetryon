# Compatibility & supported surface

Tetryon is **beta** and **pre-1.0** (current release: 0.1.0): the public API may
still change before 1.0, and changes are called out in the
[changelog](../CHANGELOG.md). The supported surface is deliberately narrow.

## Supported

| | |
| --- | --- |
| PHP | 8.4, 8.5 |
| PHPUnit | 12, 13 |
| Browser | Firefox (stable; ESR where practical) |
| OS | Linux, macOS |
| Protocol | WebDriver BiDi — direct, no Selenium Server or geckodriver |

The Browser suite runs against real Firefox on Linux **and** macOS in CI.

## Not supported (by design, for v1)

Chrome / Chromium / Edge / Safari · Windows · mobile browsers · Selenium Grid ·
CDP / Playwright / Puppeteer compatibility · a standalone Tetryon test runner ·
visual regression · video recording · cloud browser providers · AI-generated
tests.

## Versioning

Pre-1.0, so minor breaking changes are possible; they are documented under the
changelog's **Unreleased → Changed / Removed**. When 1.0 lands, the surface
above becomes a semantic-versioning promise:

> Tetryon v1 supports browser testing with PHPUnit 12/13 and Firefox on macOS
> and Linux.

No Chrome promise. No Playwright promise. No Selenium Server promise.

## Public API

These are the types and entry points you build on; they follow the versioning
policy above:

- `Vusys\Tetryon\PHPUnit\BrowserTestCase`, `InteractsWithBrowser`, `Scenario`
- `Vusys\Tetryon\PHPUnit\Browser` — the fluent browser object
- `Vusys\Tetryon\Laravel\BrowserTestCase`
- `Vusys\Tetryon\Core\Config\Configuration`
- `vendor/bin/tetryon` (the `doctor` CLI) and the `tetryon:*` artisan commands

Everything else — `Core\Selector\*`, `Firefox\*`, the BiDi transport — is
**internal** and may change without notice. If you find yourself reaching into
it, please open an issue so the need can be met by the public surface instead.
