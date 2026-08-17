# Reporting

Record a test as a browsable HTML report — a screenshot at each meaningful moment, captioned and timed, with a click-through timeline instead of a wall of terminal output.

WebDriver BiDi has no screencast API and the driver is single-threaded, so a frame can only be captured between commands — this isn't a screen recording, it's closer to a trace viewer: meaningful states, not motion.

## Recording a test

Turn recording on with `Browser::recording()`, then wrap each labelled beat's gestures in `Browser::beat()`'s closure — the chain never breaks, and `$browser` never needs to be hoisted out into a local variable first, since the closure receives it as its own `$b` parameter:

```php
public function test_guest_can_log_in(): void
{
    $this->browser()->visit('/login')->recording('Guest logs in')
        ->beat('Fill in credentials', fn (Browser $b) => $b
            ->fill('Email', 'bryan@example.com')
            ->fill('Password', 'password'))
        ->beat('Log in', fn (Browser $b) => $b->press('Log in'))
        ->assertSee('Dashboard');
}
```

- `recording(?string $title = null)` — turns recording on for this test. Worth titling by hand, since a good title is a sentence a teammate can skim; left unset, it falls back to PHPUnit's own derived test name (`#[TestDox]`, or the prettified method name). A test that never calls this pays no screenshot cost and behaves exactly as before.
- `beat($label, $body)` — the one marker verb. Captures a "before" moment, runs `$body($browser)`, then captures a timed "after" moment. `$body` always runs — recording only ever changes whether a beat is photographed, never whether it happens — so a beat behaves identically whether or not `recording()` was called. Grouping several gestures under one beat (a compound filter, a multi-field form) is an editorial choice, not something counted for you.
- Assertions caption themselves — no verb, no label, and they don't need to be inside a beat's closure. Every `assertX()` call captures its own proof moment while recording is active, showing exactly which call ran and with what arguments, e.g. `assertSee("Dashboard")`.

The closure is the point, not ceremony: a gesture has to be lexically inside some beat's body to be captured at all, rather than "belongs to whatever beat came before it" being a convention you hold in your head. It isn't airtight — nothing stops a bare `$browser->click(...)` outside any beat, and that gesture will run exactly as normal but leave no trace in the report — but it makes the correct shape the natural one to reach for.

The closing moment — passed or failed — is appended automatically once the test finishes; you don't call anything for that yourself. The step count shown in the report ("step 3/6") is derived from the beats actually reached, not a number you declare and keep in sync by hand.

If a gesture or assertion fails (most commonly an unresolved selector, or a failed assertion), the recording captures that failure as its own moment — captioned with what failed, carrying the selector-resolution trace when available — and rethrows. The test still fails normally; the report just shows exactly where and why.

A beat's body doesn't have to call a `Browser` method at all — that's also how a non-browser wait (a database poll after an optimistic UI save) sits between two beats without breaking the chain:

```php
$this->browser()->recording('Answer and submit')
    ->beat('Answer the question', fn (Browser $b) => $b->click('Yes'))
    ->beat('Persist', fn () => $this->waitForAnswersPersisted($record, 1))
    ->beat('Submit', fn (Browser $b) => $b->click('Submit'));
```

### Beat-boundary screenshots are not synchronized

A beat's "before"/"after" moments are raw screenshots taken the instant `beat()` opens or closes it — unlike a gesture (`click()`, `fill()`, …), which auto-waits for its target to be actionable first, a beat boundary waits for nothing. If a gesture inside the beat's body triggered something asynchronous — a Bootstrap modal fading in, a CSS-transitioned panel sliding into place, anything a JS handler kicks off after the click already returned — the "after" moment can land mid-transition, or even before the transition has started at all. A beat containing nothing but a `click()` can end up with a pixel-identical "before"/"after" pair that shows none of the UI the beat is named after.

Assertions don't have this problem, because they retry: an `assertVisible(...)` or `assertSee(...)` placed at the end of the beat's body only lets the body return once its own condition holds, so the "after" moment is always a settled frame. That's the workaround today — if a beat's "after" shot needs to be trustworthy (the modal genuinely visible, the panel genuinely in place), end the body with a retrying assertion:

```php
->beat('Open the delete confirmation', fn (Browser $b) => $b
    ->click('[data-delete-url]')
    ->assertVisible('.modal.show'))     // settles the frame before the beat closes
->beat('Confirm deletion', fn (Browser $b) => $b->click('@confirm-delete'))
```

Without it, the beat photography itself contributes nothing beyond luck — whether an "after" moment happens to land on a settled frame depends entirely on what the body does last, not on anything the recording feature guarantees.

**The retried condition has to be visual, not just a passing wait.** `waitForExpression()` (or an assertion built on the same DOM value) genuinely resolving does not mean the frame is settled — plenty of real UI code sets its state *before* it animates into view, not after. A Bootstrap modal is a common case: its JS often populates the modal's fields on `show.bs.modal`, which fires synchronously the instant `.show()` is called, before the `.modal.fade` CSS transition has even started. A wait built on that field's value passes at essentially t=0 of a transition that takes another 150–300ms to become visible, so the beat's "after" moment still photographs nothing:

```php
// Passes, but doesn't settle the frame — the field is populated
// before the modal has visibly appeared, not after:
->beat('Open the edit-row modal', fn (Browser $b) => $b
    ->click('@edit-row')
    ->waitForExpression('document.querySelector(\'[data-edit-row-field="name"]\')?.value === "Ada"'))
```

Use a condition that samples what the page actually looks like — `assertVisible()`, a geometry or visibility check — not one that only samples application state a script may set ahead of the animation.

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
