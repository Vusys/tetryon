# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- **`assertFocused()`/`isFocused()` descend shadow roots** (#165). When focus is inside a shadow root, `document.activeElement` reports the host, not the focused element; these now walk `shadowRoot.activeElement` to the real one. Combined with the suite reading state through a shadow-piercing helper, this lets the TodoMVC behavioural suite run against Lit — a web-component app where each todo is its own shadow root — like any other app. Lit's remaining skips are genuine Lit behaviours (commits edits on blur not Enter; no trim/reject of whitespace input; `autofocus`-based edit focus headless doesn't honor), not selector gaps.

## [0.5.0] - 2026-08-09

Shadow DOM, all the way. Following 0.4.0's CSS-based piercing, the behavioural text/label strategies pierce open shadow roots too — so a web-component app is drivable the way Tetryon is meant to be, by link text, button text, and label. Still beta and pre-1.0 — see [`docs/compatibility.md`](docs/compatibility.md).

### Added

- **Shadow-DOM piercing for the text/label strategies** (#162). Following #151 (CSS-based piercing), the behavioural strategies now pierce open shadow roots too — button text, link text, and label association (wrapping, `for=`, adjacency). XPath can't cross shadow boundaries, so each carries a JavaScript matcher the driver runs inside every shadow root when the native locate finds nothing. Web-component apps are now drivable behaviourally (`click('Go')`, `press('Save')`, `check('Accept terms')`). The accessible-name locator still doesn't pierce.

## [0.4.0] - 2026-08-09

Focus and shadow DOM — the two gaps the TodoMVC epic surfaced. Commit-on-blur flows work in headless, there's a first-class `blur()` and `assertFocused()`, and web-component apps that render into shadow roots are drivable by CSS/test-id/placeholder. Still beta and pre-1.0 — see [`docs/compatibility.md`](docs/compatibility.md).

### Added

- **`blur()` verb** (#142). Blurs the focused element (or a given target) to drive commit-on-blur flows — inline edits, validate-on-blur fields, autosave — instead of faking it with `pressKey('Tab')`.
- **`assertFocused()` / `assertNotFocused()` and `isFocused()`** (#141). Assert (with auto-wait) that a target is the document's focused element, so "double-click focuses the edit input" reads as behaviour rather than an `evaluate()` on `document.activeElement`.
- **Shadow DOM piercing for CSS-based resolution** (#151). Web-component apps that render into (nested) shadow roots are now reachable: when a CSS locator — explicit CSS/id, a test attribute, `[placeholder]`, or `[name]` — finds nothing in the light DOM, resolution descends open shadow roots. Actionability's hit-test and `visibleText()` (behind `assertSee()`) are shadow-aware too. Text/label strategies (XPath) don't pierce yet (#162).

### Fixed

- **Commit-on-blur works in headless** (#143). Headless Firefox never treats its window as focused (`document.hasFocus()` is `false`) and drops every `blur`/`focusout` event, so save-on-blur handlers never ran. `blur()` now dispatches those events itself when the browser didn't — exactly once, headless or headed. An application that commits by calling the field's own `blur()` internally (e.g. on `Enter`) still needs a focused window; run headed or under Xvfb in CI (see [`docs/ci.md`](docs/ci.md)).

## [0.3.0] - 2026-08-09

Validated against ten real-world frameworks. A shared behavioural suite runs against the TodoMVC apps (React, Vue, Angular, Svelte, Preact, react-redux, Lit, jQuery, Backbone, vanilla ES6), which drove out three selector/driver fixes and confirmed the behavioural selector strategy holds across markup we didn't write. Still beta and pre-1.0 — see [`docs/compatibility.md`](docs/compatibility.md).

### Fixed

- **`check()` / `uncheck()` resolve the checkbox behind a sibling label** (#138). A real checkbox next to a styled label with no `for=` and no wrapping — the ubiquitous custom-toggle pattern (`<input class="toggle"><label>Buy milk</label>`) — used to resolve the label (or its row) instead of the input, so `check('Buy milk')` failed. These verbs now associate a label with an adjacent checkbox/radio (by wrapping, `for=`, or immediate adjacency). The association is deliberately kept out of `click()` / `doubleClick()` resolution, which still target the visible label next to a hidden input.
- **`check()` / `uncheck()` drive visually-hidden custom checkboxes** (#139). These verbs toggle with a synthetic `click()`, which an `opacity: 0` / `pointer-events: none` real input still receives — but they routed through the full pointer-actionability probe and were rejected outright, so no custom checkbox, radio, or toggle on the real web was drivable. They now wait only for the element to exist and be enabled; `click()` and the other pointer verbs keep the full probe.

### Changed

- **`currentPath()` includes the URL fragment** (#140). It used to drop the fragment, so hash routes (`/app/#/active`) were invisible to `assertPathIs()` and `waitForPath()`. The fragment is now appended when present; a fragment-free URL is unchanged, and the query string is still excluded. If you assert a path on a URL that carries a fragment, update the expected value.

### Added

- **TodoMVC cross-framework compatibility suite.** An opt-in `TodoMvc` PHPUnit suite plus `composer todomvc:fetch`, running one shared behavioural suite against ten frameworks from a pinned upstream SHA. It exists to prove the selector strategy against markup we didn't author; the results, and a triage of every gap, are in [`docs/todomvc.md`](docs/todomvc.md). Gated in CI in its own workflow, out of the merge gate — it tracks third-party apps.

## [0.2.0] - 2026-08-08

Auto-wait and selector resolution grow up: actions now reach targets they
previously could never click, resolution picks the match a human meant, and
native dialogs stop wedging the session. Still beta and pre-1.0 — see
[`docs/compatibility.md`](docs/compatibility.md).

### Changed

- **Actionability scrolls when the hit test fails, not only when the rect is
  off-screen** (#117, #96). The probe used to decide whether to scroll by
  comparing the target's rect against the viewport alone, *before* anything was
  known about occlusion — so an element clipped out of a scrollable pane (a
  Bootstrap `modal-dialog-scrollable`, an `overflow: auto` panel), or sitting
  under a `position: fixed` action bar, was "already visible", never moved, and
  could never become actionable. Every click, fill and select against it timed
  out. In-view is now measured against the visible box of every scrollable
  ancestor as well as the viewport, and a failed hit test triggers a recovery
  centre-scroll before the target is reported occluded. A target that is already
  clear is still never moved, so an anchored popover or menu is not scrolled out
  from under its own click.
- **Resolution prefers a rendered match over an unrendered duplicate** (#101).
  The tie-break between several elements matching one target was all-or-nothing:
  a candidate either hit-tested to itself right now or counted for nothing, and
  when none qualified, DOM order won. Visibility is now *ranked* — clickable,
  then merely rendered, then DOM order — so a real option below the fold beats a
  zero-size measurement node (the hidden input sizer rich select / tree-select
  widgets keep alongside their visible options). Internally this replaces
  `Core\Selector\HitTestProbe::isHitTestable(): bool` with
  `VisibilityProbe::visibility(): Visibility`; `Core\Selector\*` is internal, so
  this only affects anything that implemented the probe itself.
- **`click()` / `press()` prefer an interactive target.** When more than one
  element matches, action verbs now pick the interactive candidate (button,
  link, input, …) instead of letting a non-interactive node — e.g. a heading
  sharing a button's text — shadow it and silently no-op (#72). When nothing
  interactive matches, the first match still wins.

### Added

- **Native dialog handling** (#118): `acceptDialog()`, `dismissDialog()`,
  `typeInDialog()` — each arranged *before* the action that opens the dialog,
  since a dialog blocks the page and the click that triggered it does not
  complete until it is answered — plus `assertDialogMessage()` and
  `dialogMessage()` for the wording afterwards. Previously a journey through
  `window.confirm` could not be tested at all: Firefox dismissed the dialog
  behind the test's back, so a destructive confirm always took the cancel
  branch, and when a dialog did stay up the session simply stopped responding.
  A dialog nobody arranged an answer for is now dismissed *and reported* — the
  action fails immediately with `UnhandledDialogException` naming the type and
  message, so what used to be a silent hang is a one-line diagnosis.
  `beforeunload` guards are left to the browser, so navigating away from a dirty
  form is never blocked.
- **Scroll verbs** (#117): `scrollTo($target)`, `scrollToBottom()`,
  `scrollToTop()`, and an `I scroll to "..."` natural-language step — for when
  the scroll itself is the behaviour under test (an infinite scroller, a
  scroll-spy nav, a lazy-loading list). `scrollTo()` waits for the element to
  exist but not to be clickable, so it reaches a region that has not rendered
  its controls yet.
- **Network observation** (#70): `waitForRequest()` / `waitForResponse()` to
  synchronise on an XHR/fetch instead of polling the DOM, and `assertRequested()`
  / `assertNotRequested()` (substring or `*`-glob match). A `network.log` of
  observed requests (method, URL, status) is now captured in the failure
  artifacts too, backed by BiDi `network.*` events.
- **Drag-and-drop** (#83): `drag($source, $target)`, `dragTo($source, $x, $y)`,
  and `dragUp`/`dragDown`/`dragLeft`/`dragRight($source, $pixels)`. Built on a new
  pointer-drag primitive that issues intermediate moves, so pointer-drag
  libraries (Sortable.js, vuedraggable) register the gesture. Pointer-based DnD;
  HTML5-native `draggable` events are out of scope.
- **Text verbs drive `contenteditable` editors** (#80). `fill()` / `type()` /
  `clear()` now work on rich-text / `contenteditable` elements as well as
  `<input>`/`<textarea>`, and `value()` reads their text back. Anything else
  still throws `UndrivableElementException`.
- **`select()` matches an option by its visible label or its value** (#73), so
  tests can pick "United Kingdom" without scraping an opaque option value first.
  `selectByValue()` keeps value-only selection; both throw
  `OptionNotFoundException` when no option matches.
- **Form verbs fail loudly on controls they can't drive** (#77). `fill()` /
  `type()` / `clear()` require an `<input>`/`<textarea>`, `select()` a
  `<select>`, and `check()` / `uncheck()` a checkbox/radio — otherwise they
  throw `UndrivableElementException` naming the resolved element, rather than
  silently doing nothing and surfacing later at an unrelated assertion.
- **JavaScript state probes** (#82): `waitForExpression()`, `assertExpression()`,
  and `assertExpressionEquals()` — the auto-wait/retry wait-and-assert layer on
  top of `evaluate()`, for page state the DOM doesn't render as text (store
  readiness, derived totals, a chart library's data).
- **Cookie API on `Browser`** — `setCookie()`, `cookie()`, `deleteCookie()`,
  `clearCookies()`. Backed by WebDriver BiDi storage (not `document.cookie`), so
  HttpOnly cookies work and a cookie set before the first `visit()` is carried
  by that request. Domain defaults to the base-URL host, path to `/`; options
  cover `secure`, `httpOnly`, `sameSite`, `expiry`, and explicit `domain`/`path`.
- **`Browser::evaluate(string $script): mixed`** — run a JavaScript expression in
  the page and get its value back. Promises are awaited, so an async IIFE
  resolves to its result. The supported escape hatch for in-page setup the
  fluent verbs don't model.
- **`protected driver(): FirefoxBiDiDriver`** on `InteractsWithBrowser` — reach
  the underlying driver from a subclass without reflection (boots the browser if
  needed). Prefer `evaluate()`; the driver type itself stays internal.
- **Form-control state assertions** (#75): `assertChecked` / `assertNotChecked`,
  `assertRadioSelected` / `assertRadioNotSelected`, `assertSelected` /
  `assertNotSelected`, plus `isChecked()` and `selected()` queries. These read
  `this.checked` / the selected option rather than `value` (a checkbox's value
  attribute isn't its checked state) and retry until they pass, like
  `assertValue`.
- **Enabled/disabled and attribute assertions** (#76): `assertEnabled` /
  `assertDisabled`, `assertAttribute` / `assertAttributeContains`, plus an
  `attribute()` query for reading `href` / `data-*` / `aria-*` and the like.

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

[Unreleased]: https://github.com/Vusys/tetryon/compare/v0.5.0...master
[0.5.0]: https://github.com/Vusys/tetryon/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/Vusys/tetryon/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/Vusys/tetryon/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/Vusys/tetryon/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Vusys/tetryon/releases/tag/v0.1.0
