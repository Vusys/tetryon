# Reporting

Record a test's steps as a browsable HTML report — a screenshot at each meaningful moment, captioned and timed, with a click-through timeline instead of a wall of terminal output.

WebDriver BiDi has no screencast API and the driver is single-threaded, so a frame can only be captured between commands — this isn't a screen recording, it's closer to a trace viewer: meaningful states, not motion.

## Recording a test

Get a recorder from `InteractsWithBrowser::recorder()` (available on `BrowserTestCase`), titled and scoped to how many steps you'll capture:

```php
public function test_guest_can_log_in(): void
{
    $browser = $this->browser()->visit('/login');
    $recorder = $this->recorder('Guest logs in', totalSteps: 3);

    $recorder->type($browser, 'Email', 'bryan@example.com', 'Type the email');
    $recorder->type($browser, 'Password', 'password', 'Type the password');
    $recorder->step('Log in', function () use ($browser): void {
        $browser->press('Log in');
    });

    $recorder->assert($browser, 'Sees the dashboard', function () use ($browser): void {
        $browser->assertSee('Dashboard');
    });
}
```

- `note($label)` — a standalone moment not tied to an action, e.g. "Opening state" before the first step. Doesn't consume a step number.
- `type($browser, $field, $value, $label)` — clears and fills a field, captured as a before/after pair tagged with how long it took.
- `step($label, $callable)` — times an arbitrary action, captured the same way.
- `assert($browser, $label, $callable)` — runs an assertion and captures the proof: a checkmarked moment listing exactly which `Browser` assertion calls ran and with what arguments — e.g. `assertSee("Dashboard")` — not just the caller's label. `$browser` must be the same instance the assertion calls run against. Doesn't consume a step number.

The recorder's closing moment — passed or failed — is appended automatically once the test finishes; you don't call anything for that yourself.

If a `step()`/`type()`/`assert()` action throws (most commonly an unresolved selector), the recorder captures that failure as its own moment — carrying the selector-resolution trace when available — and rethrows. The test still fails normally; the report just shows exactly where and why.

## Rendering a report

A single test can render its own report:

```php
$recorder->render('tests/Browser/Artifacts/login-report');
```

This writes `index.html` plus a `screenshots/` directory into that path. Open `index.html` directly in a browser — no server required.

`render()` never throws. If nothing was recorded, or rendering failed, it returns `null` — check `$recorder->skipReason()` for why. Recording must never be why a test fails.

## Combining a whole suite into one report

Set `TETRYON_SUITE_REPORT` and every recorder-instrumented test in the process contributes to one combined report, written once at process shutdown:

```bash
TETRYON_SUITE_REPORT=1 vendor/bin/phpunit --testsuite Browser
```

By default it's written to `tests/Browser/Artifacts/suite-report`; override the path with `TETRYON_SUITE_REPORT_PATH`. The combined report's sidebar lists every test with its pass/fail state, and opens on the first failure if there is one.

An ordinary test run pays nothing for this — accumulation and rendering are both no-ops unless `TETRYON_SUITE_REPORT` is set.

## What's in the report

- A header summary (suite reports only) — pass/fail counts and a segmented progress bar.
- A sidebar of every test, filterable by title (suite reports only).
- A thumbnail rail and a large current-moment view: screenshot, caption, step count, duration, and a green checkmark on `assert()` moments.
- Player controls — Play/Pause, a 0.5×/1×/2×/4× speed selector, and Prev/Next (arrow keys, or Space to toggle play). Playback holds each moment for roughly how long its action took, and flows from one test straight into the next, so pressing Play turns the whole suite into a watchable run instead of a screenshot wall — pause and scrub anywhere at any point.
- An assertions panel on any `assert()` moment, listing the exact `Browser` calls that moment proved — e.g. `assertSee("1 item left")` — not just the label you gave it.
- A selector-attempts table on any moment where an action failed to resolve its target.
- For a failing test: links to its console log, network log, page HTML, and BiDi trace, plus the same plain-text failure report `tetryon doctor` and stderr print on failure — captured once and shared, not re-queried from the driver.

## Screenshot format

Screenshots are written as WebP when PHP's `ext-gd` is available (much smaller than PNG at report scale), falling back to PNG otherwise. Either way this is transparent — the report references whatever format got written.

## Instrumenting once for many scenarios

Because `recorder()` lives on the trait every browser test already uses, a shared base class can instrument its scenarios once and have every subclass benefit — see `tests/TodoMvc/TodoMvcTestCase.php` for a real example spanning ten framework apps.
