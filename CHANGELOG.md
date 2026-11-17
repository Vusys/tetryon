# Changelog

All notable changes to this project are documented here. The format is based on
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project
adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.9.0] - 2026-11-17

A reused browser can now checkpoint the database too, so a test class seeds and logs in once. One change can break existing suites: `press()` only presses buttons now, so use `click()` for links and other elements.

### Added

- **A database checkpoint for reused browsers** (#78, Phase 2). With `Laravel\BrowserTestCase`, `#[ReuseBrowser(database: true)]` (or the trait's `snapshotsDatabase()`) checkpoints the database after `authenticate()` and puts it back before every test, so a class seeds and logs in once. SQLite uses `VACUUM INTO` and a table-by-table restore; MySQL and MariaDB use shadow tables. The test process restores the committed rows, which the served app then reads. See [`docs/writing-tests.md`](docs/writing-tests.md#checkpointing-the-database-laravel).

### Changed

- **`press()` only presses buttons** (#199). It was `click()` under another name, so it resolved its text against labels, placeholders and any visible text first. A field labelled "Search" took `press('Search')` from the "Search" button beside it, and the form never submitted. Now it considers only `<button>`, `<input type="submit|button|reset|image">` and `[role=button]` elements, by text, value, `aria-label`, `title`, test attribute or `name`. Code that used `press()` on a link or another non-button element should use `click()`.

## [0.8.1] - 2026-11-16

### Fixed

- **One suite report for a parallel run** (#193). Under paratest each worker rendered its own tests into the same report directory, so the last worker to finish overwrote the others. Now each worker adds its part, and the report is re-rendered from all of them, so it holds every test in the run. See [`docs/reporting.md`](docs/reporting.md#combining-a-whole-suite-into-one-report).

## [0.8.0] - 2026-11-16

Faster browser suites. A test class can reuse one browser instead of launching Firefox for every test, and parallel runs can let Tetryon start one app server per worker. One change can break existing suites: `Laravel\BrowserTestCase` now refuses `RefreshDatabase` and `DatabaseTransactions`, whose rolled-back rows the served app never sees, so switch those tests to `DatabaseTruncation`.

### Added

- **Reuse one browser across a test class** (#78). `#[ReuseBrowser]`, or the `ReusesBrowser` trait, launches Firefox once per class instead of once per test. An `authenticate()` hook runs once after launch, the cookies are snapshotted (plus localStorage and sessionStorage with `storage: true`), and every test starts from that snapshot on `about:blank` with empty diagnostics. A browser that stops answering is relaunched and authenticated again. A launch costs about 1.7s on a 2015 laptop; a restore costs about 70ms. See [`docs/writing-tests.md`](docs/writing-tests.md#reusing-one-browser-across-a-class).
- **A managed app server per parallel worker** (#74). With `tetryon.server.managed` on (`TETRYON_MANAGED_SERVER=true`), each process starts its own server on `base_port + TEST_TOKEN` from a command template, waits for a ready path, and stops it on exit. The server inherits the worker's environment plus a configured `env` with `{token}`, `{port}` and `{host}` filled in, so each worker's server uses that worker's database. See [`docs/laravel.md`](docs/laravel.md#parallel-browser-tests).
- **`Laravel\BrowserTestCase` refuses `RefreshDatabase` and `DatabaseTransactions`** (#74). The served app reads over its own connection and never sees rolled-back rows, so these traits used to fail confusingly with a missing seeded user. Now the test fails at once and says to use `DatabaseTruncation`.

## [0.7.1] - 2026-11-16

### Fixed

- **No browser diagnostics for a test that failed before loading a page** (#186). A plain PHP error in the test itself used to print the full diagnostics block and write artifacts describing a blank page. Now it prints one line saying there are none.

## [0.7.0] - 2026-11-15

Recorded browser tests. A test can record itself as a browsable HTML report a person can step through or play back like a video: captioned, timed, settled frames with gesture rings and failure diagnostics, and one combined report for a whole suite. Along the way, the configured viewport is finally applied, and `drag()` completes native HTML5 drag-and-drop. Still beta and pre-1.0; see [`docs/compatibility.md`](docs/compatibility.md).

### Added

- **Recorded browser tests** (#102). A test records as a browsable HTML report: a frame at each meaningful moment, captioned and timed, with a player that plays a whole suite back like a video. `#[Record]` on a test or class records from the browser's first command, or call `recording()`. `beat($label, $body)` labels a step; beats nest into the outer one. Assertions caption themselves, naming the element when the target is a selector, and `assertExpression*()` take `label:`. `unrecorded()` keeps setup out, and `loginAs()` uses it. `TETRYON_SUITE_REPORT=1` writes one report for a suite, with its sidebar grouped by folder and class. Frames wait for the page to load and settle, in-between frames keep animations watchable, and encoding runs in a background worker. See [`docs/reporting.md`](docs/reporting.md).
- **`device_pixel_ratio`** in the viewport config, for sharp 2× screenshots and recordings.

### Changed

- **`evaluate()` returns arrays and objects as PHP arrays.** Arrays and sets come back as lists, plain objects and maps as keyed arrays, recursively. They used to come back as raw WebDriver BiDi values (`['type' => 'number', 'value' => 800]` and so on), which meant reaching for `JSON.stringify` to get anything structured out. Values with no PHP equivalent (DOM nodes, windows, maps keyed by objects) keep their raw shape.

### Fixed

- **The configured viewport is applied.** It was parsed and never sent, so every test ran at Firefox's default window size. It is now set when the browser starts.
- **`drag()` completes native HTML5 drag-and-drop.** Firefox's input actions start an HTML5 drag but never deliver `drop` or `dragend`. When the source is inside a `draggable` element, `drag()` now dispatches the HTML5 sequence itself.
- **`fill()` on date, time, colour and range inputs** (#185). Firefox draws these as pickers that drop typed text, so `fill()` left them silently empty. It now sets the value and fires `input` and `change`, and throws `UndrivableElementException` when the input rejects the value's format.

## [0.6.0] - 2026-08-09

Custom widgets, driven behaviourally. Two things the TodoMVC epic reached for and shadow-DOM work exposed: a first-class `chooseFromDropdown()` for the WAI-ARIA combobox pattern that component libraries ship instead of a native `<select>`, and shadow-aware focus assertions — enough for the behavioural suite to run against Lit, where every todo is its own shadow root. Still beta and pre-1.0 — see [`docs/compatibility.md`](docs/compatibility.md).

### Added

- **`chooseFromDropdown()` for custom (non-native) comboboxes** (#81). `select()` only drives a native `<select>`; component libraries usually render the WAI-ARIA pattern instead (a trigger / `role="combobox"` input revealing a `role="listbox"` of `role="option"`s). `chooseFromDropdown($field, $option)` opens the control and clicks the option by visible text, matched globally so it works even when the list is portalled to `<body>`. `select()` continues to fail loudly (`UndrivableElementException`) when pointed at a non-`<select>`.
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

[Unreleased]: https://github.com/Vusys/tetryon/compare/v0.9.0...master
[0.9.0]: https://github.com/Vusys/tetryon/compare/v0.8.1...v0.9.0
[0.8.1]: https://github.com/Vusys/tetryon/compare/v0.8.0...v0.8.1
[0.8.0]: https://github.com/Vusys/tetryon/compare/v0.7.1...v0.8.0
[0.7.1]: https://github.com/Vusys/tetryon/compare/v0.7.0...v0.7.1
[0.7.0]: https://github.com/Vusys/tetryon/compare/v0.6.0...v0.7.0
[0.6.0]: https://github.com/Vusys/tetryon/compare/v0.5.0...v0.6.0
[0.5.0]: https://github.com/Vusys/tetryon/compare/v0.4.0...v0.5.0
[0.4.0]: https://github.com/Vusys/tetryon/compare/v0.3.0...v0.4.0
[0.3.0]: https://github.com/Vusys/tetryon/compare/v0.2.0...v0.3.0
[0.2.0]: https://github.com/Vusys/tetryon/compare/v0.1.0...v0.2.0
[0.1.0]: https://github.com/Vusys/tetryon/releases/tag/v0.1.0
