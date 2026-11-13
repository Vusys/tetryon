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

- `recording(?string $title = null)` — turns recording on for this test. Worth titling by hand, since a good title is a sentence a teammate can skim; left unset, it falls back to the test method's name turned into a sentence (`test_guest_can_log_in` → "Guest can log in"). A test that never calls this pays no screenshot cost and behaves exactly as before.
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

### What a beat photographs

A beat is more than a "before" and an "after". While recording, three things add frames inside it — each kept only when the page actually looks different from the frame before, so a page that sits still costs nothing:

- **Waiting.** Every `Browser` wait — `waitForText()`, `waitForPath()`, the auto-wait before a gesture, a retrying assertion — photographs the page each time it polls and comes up empty. A slow step (a form submitting, a page navigating) is recorded as the states it actually passed through, not as one still held for its whole duration.
- **Gestures.** As a gesture's target becomes actionable, the frame records where that element is, and the report draws a ring around it. For a native `<select>` it also records the options and which is selected, once as the gesture lands and once more after the choice — `select()` sets the value by script and the browser's own option list never opens, so the report draws the list itself. Nothing is drawn into the page under test.
- **Settling.** Once the body returns, the beat keeps photographing until two frames 100ms apart match, for at most half a second. A modal still fading in or a panel still sliding is followed to its resting state before the closing "after" moment is taken. A page that never stops moving (a spinner, a carousel) costs that half second, not a wait timeout.

Recording still never changes what a test does, only how long it takes: a screenshot per poll, and up to half a second per beat.

Settling follows a change that is already under way. It cannot know about one that hasn't started — a panel that only appears after a request comes back, say. If a beat has to close on that, end its body with an assertion that waits for it to be *visible*:

```php
->beat('Open the delete confirmation', fn (Browser $b) => $b
    ->click('[data-delete-url]')
    ->assertVisible('.modal.show'))
```

A wait on application state alone (`waitForExpression()` on a value a script sets before it animates anything into view) can pass while the page still looks exactly as it did.

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
- A thumbnail rail (one thumbnail per change of picture) and a large current-moment view: screenshot, caption, step count, duration, a ring around the element a gesture is acting on, and a green checkmark on a verified assertion moment.
- Player controls — Play/Pause, a 0.5×/1×/2×/4× speed selector, and Prev/Next (arrow keys, or Space to toggle play). Playback follows the real gaps between frames, clamped at both ends — long enough to read a new caption, never more than a second and a half on a page where nothing was changing (the report says how much it skipped) — and flows from one test straight into the next, so pressing Play turns the whole suite into a watchable run instead of a screenshot wall. Pause and scrub anywhere at any point.
- An assertions panel on any verified moment, listing the exact `Browser` calls that moment proved — e.g. `assertSee("1 item left")`.
- A selector-attempts table on any moment where a gesture failed to resolve its target.
- For a failing test: links to its console log, network log, page HTML, and BiDi trace, plus the same plain-text failure report `tetryon doctor` and stderr print on failure — captured once and shared, not re-queried from the driver.

## Screenshot format

Screenshots are written as WebP when PHP's `ext-gd` is available (much smaller than PNG at report scale), falling back to PNG otherwise. Either way this is transparent — the report references whatever format got written. Each distinct image is written once, however many moments or tests show it.

## Instrumenting once for many scenarios

Because `recording()`/`beat()` live directly on `Browser`, a shared base class (or a helper method returning `Browser`) can instrument its scenarios once and have every subclass benefit — see `tests/TodoMvc/TodoMvcTestCase.php` for a real example spanning ten framework apps.
