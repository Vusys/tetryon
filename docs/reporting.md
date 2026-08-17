# Reporting

Record a test as a browsable HTML report — a screenshot at each meaningful moment, captioned and timed, with a click-through timeline instead of a wall of terminal output.

WebDriver BiDi has no screencast API and the driver is single-threaded, so a frame can only be captured between commands — this isn't a screen recording, it's closer to a trace viewer: meaningful states, not motion.

## Recording a test

Turn recording on with `Browser::recording()`, then mark each labelled beat with `Browser::beat()` — the chain never breaks, and `$browser` never needs to be hoisted out into a closure:

```php
public function test_guest_can_log_in(): void
{
    $this->browser()->visit('/login')->recording('Guest logs in')
        ->beat('Fill in credentials')
            ->fill('Email', 'bryan@example.com')
            ->fill('Password', 'password')
        ->beat('Log in')
            ->press('Log in')
        ->assertSee('Dashboard');
}
```

- `recording(?string $title = null)` — turns recording on for this test. Worth titling by hand, since a good title is a sentence a teammate can skim; left unset, it falls back to PHPUnit's own derived test name (`#[TestDox]`, or the prettified method name). A test that never calls this pays no screenshot cost and behaves exactly as before.
- `beat($label)` — the one marker verb. Closes the previous beat (capturing an "after" moment, timed) and opens the next (capturing a "before" moment). Everything chained after a beat belongs to it until the next `beat()` call or the test ends — grouping several gestures under one beat (a compound filter, a multi-field form) is an editorial choice, not something counted for you.
- Assertions caption themselves — no verb, no label. Every `assertX()` call captures its own proof moment while recording is active, showing exactly which call ran and with what arguments, e.g. `assertSee("Dashboard")`.

The closing moment — passed or failed — is appended automatically once the test finishes; you don't call anything for that yourself. The step count shown in the report ("step 3/6") is derived from the beats actually reached, not a number you declare and keep in sync by hand.

If a gesture fails to resolve or reach its target (most commonly an unresolved selector), the recording captures that failure as its own moment — carrying the selector-resolution trace when available — and rethrows. The test still fails normally; the report just shows exactly where and why.

### A non-browser wait between two beats

`beat()` takes an optional second argument for a wait that isn't a gesture on this browser — a database poll after an optimistic UI save, say — so it can sit between two beats without breaking the chain:

```php
$this->browser()->recording('Answer and submit')
    ->beat('Answer the question')->click('Yes')
    ->beat('Persist', fn () => $this->waitForAnswersPersisted($record, 1))
    ->beat('Submit')->click('Submit');
```

## Combining a whole suite into one report

Set `TETRYON_SUITE_REPORT` and every recording-instrumented test in the process contributes to one combined report, written once at process shutdown:

```bash
TETRYON_SUITE_REPORT=1 vendor/bin/phpunit --testsuite Browser
```

By default it's written to `tests/Browser/Artifacts/suite-report`; override the path with `TETRYON_SUITE_REPORT_PATH`. The combined report's sidebar lists every test with its pass/fail state, and opens on the first failure if there is one.

An ordinary test run pays nothing for this — accumulation and rendering are both no-ops unless `TETRYON_SUITE_REPORT` is set, and a test that never calls `recording()` contributes nothing to it either way.

## What's in the report

- A header summary — pass/fail counts and a segmented progress bar.
- A sidebar of every test, filterable by title.
- A thumbnail rail and a large current-moment view: screenshot, caption, step count, duration, and a green checkmark on a verified assertion moment.
- Player controls — Play/Pause, a 0.5×/1×/2×/4× speed selector, and Prev/Next (arrow keys, or Space to toggle play). Playback holds each moment for roughly how long its action took, and flows from one test straight into the next, so pressing Play turns the whole suite into a watchable run instead of a screenshot wall — pause and scrub anywhere at any point.
- An assertions panel on any verified moment, listing the exact `Browser` calls that moment proved — e.g. `assertSee("1 item left")`.
- A selector-attempts table on any moment where a gesture failed to resolve its target.
- For a failing test: links to its console log, network log, page HTML, and BiDi trace, plus the same plain-text failure report `tetryon doctor` and stderr print on failure — captured once and shared, not re-queried from the driver.

## Screenshot format

Screenshots are written as WebP when PHP's `ext-gd` is available (much smaller than PNG at report scale), falling back to PNG otherwise. Either way this is transparent — the report references whatever format got written.

## Instrumenting once for many scenarios

Because `recording()`/`beat()` live directly on `Browser`, a shared base class (or a helper method returning `Browser`) can instrument its scenarios once and have every subclass benefit — see `tests/TodoMvc/TodoMvcTestCase.php` for a real example spanning ten framework apps.
