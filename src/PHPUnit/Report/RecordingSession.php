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
        $this->settle();
        $this->capture(sprintf('%s · %dms', $label, $durationMs), $this->beatIndex, $this->beatIndex, $durationMs);
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
            caption: $this->currentLabel,
            stepIndex: $this->beatIndex,
            progress: max(0, $this->beatIndex - 1),
            durationMs: 0,
            atMs: $this->elapsedMs(),
            overlay: $overlay,
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
    private function settle(): void
    {
        $deadline = microtime(true) + self::SETTLE_CAP_MS / 1000;
        $this->sample();

        do {
            usleep(self::SETTLE_INTERVAL_MS * 1000);
            $count = count($this->moments);
            $this->sample();
        } while (count($this->moments) > $count && microtime(true) < $deadline);
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

        $this->capture($description, $this->beatIndex, $this->beatIndex, 0, verified: true, assertions: [$description]);
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
    ): void {
        $this->moments[] = new Moment(
            screenshot: $this->shoot(),
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
