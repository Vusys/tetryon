<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Throwable;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Core\Support\ImageEncoder;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\PHPUnit\Browser;

/**
 * The recording collaborator every {@see Browser} holds — inert until
 * {@see Browser::recording()} activates it, so a test that never opts in
 * pays no screenshot cost and sees no behaviour change (issue #102).
 *
 * Once active, {@see Browser::beat()} is the single marker verb: it
 * captures a "before" moment, runs its body, then captures a timed "after"
 * moment — replacing the old `note()`/`step()`/`type()` split with one
 * container. Assertions caption themselves via {@see captureAssertion()},
 * called from every `Browser::assertX()` method — no verb, no label.
 */
final class RecordingSession
{
    private bool $active = false;

    private ?string $title = null;

    private int $beatIndex = 0;

    private ?string $currentLabel = null;

    /** @var list<Moment> */
    private array $moments = [];

    private float $startedAt = 0.0;

    /** md5 of the last raw screenshot, and the encoded bytes it produced. */
    private ?string $lastHash = null;

    private string $lastImage = '';

    private const int SETTLE_INTERVAL_MS = 100;

    private const int SETTLE_CAP_MS = 500;

    private const int LOAD_CAP_MS = 2000;

    /** Resolves with location.href once loaded, fonts ready and two frames painted — or after %d ms regardless. */
    private const string LOADED_SCRIPT = <<<'JS'
        new Promise((done) => {
            setTimeout(() => done(location.href), %d);
            const painted = () => document.fonts.ready.then(() => requestAnimationFrame(() => requestAnimationFrame(() => done(location.href))));
            if (document.readyState === 'complete') { painted(); } else { addEventListener('load', painted, { once: true }); }
        })
        JS;

    public function __construct(private readonly FirefoxBiDiDriver $driver) {}

    public function activate(?string $title = null): void
    {
        if (! $this->active) {
            $this->startedAt = microtime(true);
        }
        $this->active = true;
        $this->title = $title;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Run $body as a labelled beat: capture a "before" moment, invoke
     * $body, then capture a timed "after" moment — unless $body's own
     * failure already captured one (see {@see captureFailure()}), in which
     * case the exception it threw is still propagating and this method
     * never reaches its closing capture at all. $body always runs, active
     * or not — recording only ever changes whether the beat is
     * photographed, never whether it happens.
     *
     * @param  callable(): mixed  $body
     */
    public function runBeat(string $label, callable $body): void
    {
        if (! $this->active) {
            $body();

            return;
        }

        $this->beatIndex++;
        $this->currentLabel = $label;
        $startedAt = microtime(true);
        $this->capture($label, $this->beatIndex, $this->beatIndex - 1, 0);

        $body();

        $durationMs = (int) round((microtime(true) - $startedAt) * 1000);
        $this->capture(sprintf('%s · %dms', $label, $durationMs), $this->beatIndex, $this->beatIndex, $durationMs, settle: true);
        $this->currentLabel = null;
    }

    /**
     * A frame of whatever the page looks like right now, kept only if it
     * differs from the last one captured. Called on every failed poll of a
     * `Browser` wait, so a slow step (a form submitting, a page navigating)
     * shows up as the frames it actually went through rather than one still
     * held for its whole duration. Only inside a beat — outside one there is
     * no label to file the frame under. Never throws.
     *
     * @param  array<string, mixed>|null  $overlay  see {@see Moment::$overlay}; a frame carrying one is always kept
     */
    public function sample(?array $overlay = null): void
    {
        if (! $this->active || $this->currentLabel === null) {
            return;
        }

        $this->frame($this->currentLabel, $overlay);
    }

    /**
     * Shoot and append a frame unless it repeats the last one. A gesture
     * frame is a key moment; anything else is an in-between frame.
     *
     * @param  array<string, mixed>|null  $overlay
     */
    private function frame(string $caption, ?array $overlay = null): void
    {
        $previous = $this->lastHash;
        try {
            $image = $this->shoot();
        } catch (Throwable) {
            return;
        }

        $last = $this->moments === [] ? null : $this->moments[count($this->moments) - 1];
        $unchanged = $this->lastHash === $previous;

        // Nothing new to show: the same picture, and either no gesture or
        // the very gesture the last frame already marked out (a verb that
        // probes its target twice).
        if ($unchanged && ($overlay === null || $last?->overlay === $overlay)) {
            return;
        }

        $this->moments[] = new Moment(
            screenshot: $image,
            caption: $caption,
            stepIndex: $this->beatIndex,
            progress: max(0, $this->beatIndex - 1),
            durationMs: 0,
            atMs: $this->elapsedMs(),
            overlay: $overlay,
            key: $overlay !== null,
        );
    }

    /**
     * Whether a gesture happening right now would be photographed — lets
     * `Browser` skip measuring the target when nothing would use it.
     */
    public function isInBeat(): bool
    {
        return $this->active && $this->currentLabel !== null;
    }

    /**
     * Keep sampling after a beat's body returns until the page stops
     * changing, so the closing frame isn't taken mid-transition (a modal
     * still fading in). Pixel-based and bounded: a page that never stops
     * moving costs the cap, not a wait timeout.
     *
     * ponytail: a change that starts more than one interval after the body
     * returns is still missed; end the body with a visual assertion for those.
     */
    private function settle(string $caption): void
    {
        $deadline = microtime(true) + self::SETTLE_CAP_MS / 1000;
        $this->frame($caption);

        do {
            usleep(self::SETTLE_INTERVAL_MS * 1000);
            $count = count($this->moments);
            $this->frame($caption);
        } while (count($this->moments) > $count && microtime(true) < $deadline);
    }

    /**
     * Wait (bounded) for the page to finish loading — images, stylesheets,
     * web fonts — and paint, then return its URL. A proof taken the instant
     * a URL changes otherwise shows a page with its background still missing.
     * Null when the page can't be asked, e.g. mid-navigation.
     */
    private function loadedUrl(): ?string
    {
        try {
            $url = $this->driver->evaluateScript(sprintf(self::LOADED_SCRIPT, self::LOAD_CAP_MS));
        } catch (Throwable) {
            return null;
        }

        return is_string($url) ? $url : null;
    }

    /**
     * A self-captioned proof moment for an assertion that just passed —
     * doesn't advance the beat counter, since it belongs to whichever beat
     * is currently open.
     */
    public function captureAssertion(string $description): void
    {
        if (! $this->active) {
            return;
        }

        $this->capture($description, $this->beatIndex, $this->beatIndex, 0, verified: true, assertions: [$description], settle: true);
    }

    /**
     * Captures the moment a gesture or assertion failed — guarded, since a
     * driver that just threw may be in a bad state. Carries the
     * selector-resolution trace when the failure was an
     * {@see ElementNotFoundException}, since that's only available here, not
     * at teardown time. $caption defaults to the current beat's label
     * (a gesture failure); pass the assertion's own description (e.g.
     * `assertSee("...")`) for an assertion failure, so the report names what
     * actually failed instead of falling back to the beat's label.
     *
     * The exception this is called for is always rethrown by the caller
     * afterwards, which is what stops {@see runBeat()} from also appending
     * a neutral "after" moment for the same beat — this failure moment is
     * that beat's closing evidence.
     */
    public function captureFailure(Throwable $exception, ?string $caption = null): void
    {
        if (! $this->active) {
            return;
        }

        try {
            $screenshot = $this->shoot();
        } catch (Throwable) {
            return;
        }

        $this->moments[] = new Moment(
            screenshot: $screenshot,
            caption: $caption ?? $this->currentLabel ?? 'Failed',
            stepIndex: $this->beatIndex,
            progress: max(0, $this->beatIndex - 1),
            durationMs: 0,
            outcome: 'failed',
            selectorFailure: $exception instanceof ElementNotFoundException ? $exception : null,
            atMs: $this->elapsedMs(),
        );
    }

    /**
     * Append the closing pass/fail moment and return this test's recording.
     * $fallbackTitle is used only when {@see Browser::recording()} was
     * called without an explicit title.
     */
    public function finish(string $testId, bool $passed, ?ArtifactBag $diagnostics, string $fallbackTitle): TestRecording
    {
        $this->capture(
            $passed ? 'Passed' : 'Failed',
            $this->beatIndex,
            $this->beatIndex,
            0,
            outcome: $passed ? 'passed' : 'failed',
            settle: true,
        );

        return new TestRecording(
            testId: $testId,
            title: $this->title ?? $fallbackTitle,
            passed: $passed,
            moments: $this->moments,
            diagnostics: $diagnostics,
        );
    }

    /**
     * @param  'passed'|'failed'|null  $outcome
     * @param  list<string>  $assertions
     */
    private function capture(
        string $caption,
        int $stepIndex,
        int $progress,
        int $durationMs,
        bool $verified = false,
        ?string $outcome = null,
        array $assertions = [],
        bool $settle = false,
    ): void {
        // A test's first beat opens before anything has been visited.
        if ($this->loadedUrl() === 'about:blank') {
            return;
        }

        if ($settle) {
            $this->settle($this->currentLabel ?? $caption);
        }

        // Firefox refuses a screenshot mid-navigation; recording must never fail the test.
        try {
            $screenshot = $this->shoot();
        } catch (Throwable) {
            return;
        }

        // The key moment supersedes an in-between frame of the same picture just before it.
        $last = $this->moments === [] ? null : $this->moments[count($this->moments) - 1];
        if ($last instanceof Moment && ! $last->key && $last->screenshot === $screenshot) {
            array_pop($this->moments);
        }

        $this->moments[] = new Moment(
            screenshot: $screenshot,
            caption: $caption,
            stepIndex: $stepIndex,
            progress: max(0, $progress),
            durationMs: $durationMs,
            outcome: $outcome,
            verified: $verified,
            assertions: $assertions,
            atMs: $this->elapsedMs(),
        );
    }

    /**
     * Screenshot the page and return report-ready bytes. An unchanged page
     * returns the very same string as last time, so identical frames cost
     * no extra memory and no re-encode.
     */
    private function shoot(): string
    {
        $png = $this->driver->screenshot();
        $hash = md5($png);

        if ($hash !== $this->lastHash) {
            $this->lastHash = $hash;
            $this->lastImage = ImageEncoder::encode($png)->bytes;
        }

        return $this->lastImage;
    }

    private function elapsedMs(): int
    {
        return (int) round((microtime(true) - $this->startedAt) * 1000);
    }
}
