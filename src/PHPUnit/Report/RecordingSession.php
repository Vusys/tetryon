<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Throwable;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\PHPUnit\Browser;

/**
 * The recording collaborator every {@see Browser}
 * holds — inert until {@see Browser::recording()}
 * activates it, so a test that never opts in pays no screenshot cost and
 * sees no behaviour change (issue #102).
 *
 * Once active, {@see Browser::beat()} is the single
 * marker verb: it closes the previous beat (capturing an "after" moment,
 * timed) and opens the next one (capturing a "before" moment) — replacing
 * the old `note()`/`step()`/`type()` split with chain position alone.
 * Assertions caption themselves via {@see captureAssertion()}, called from
 * every `Browser::assertX()` method — no verb, no label.
 */
final class RecordingSession
{
    private bool $active = false;

    private ?string $title = null;

    private int $beatIndex = 0;

    private ?string $currentLabel = null;

    private ?float $beatStartedAt = null;

    /** @var list<Moment> */
    private array $moments = [];

    public function __construct(private readonly FirefoxBiDiDriver $driver) {}

    public function activate(?string $title = null): void
    {
        $this->active = true;
        $this->title = $title;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Close the current beat (if one is open) and open the next, labelled
     * $label. $between runs after the close and before the open — the
     * escape hatch for a non-browser wait (a database poll after an
     * optimistic UI save) that belongs between two beats but isn't a
     * gesture on this browser.
     *
     * @param  (callable(): void)|null  $between
     */
    public function advanceBeat(string $label, ?callable $between = null): void
    {
        if (! $this->active) {
            return;
        }

        $this->closeCurrentBeat();

        if ($between !== null) {
            $between();
        }

        $this->beatIndex++;
        $this->currentLabel = $label;
        $this->beatStartedAt = microtime(true);
        $this->capture($label, $this->beatIndex, $this->beatIndex - 1, 0);
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
     */
    public function captureFailure(Throwable $exception, ?string $caption = null): void
    {
        if (! $this->active) {
            return;
        }

        try {
            $screenshot = $this->driver->screenshot();
        } catch (Throwable) {
            return;
        }

        $this->moments[] = new Moment(
            screenshotPng: $screenshot,
            caption: $caption ?? $this->currentLabel ?? 'Failed',
            stepIndex: $this->beatIndex,
            progress: max(0, $this->beatIndex - 1),
            durationMs: 0,
            outcome: 'failed',
            selectorFailure: $exception instanceof ElementNotFoundException ? $exception : null,
        );
    }

    /**
     * Close any open beat, append the closing pass/fail moment, and return
     * this test's recording. $fallbackTitle is used only when
     * {@see Browser::recording()} was called without
     * an explicit title.
     */
    public function finish(string $testId, bool $passed, ?ArtifactBag $diagnostics, string $fallbackTitle): TestRecording
    {
        $this->closeCurrentBeat();
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

    private function closeCurrentBeat(): void
    {
        if ($this->currentLabel === null) {
            return;
        }

        $durationMs = (int) round((microtime(true) - ($this->beatStartedAt ?? microtime(true))) * 1000);
        $this->capture(sprintf('%s · %dms', $this->currentLabel, $durationMs), $this->beatIndex, $this->beatIndex, $durationMs);
        $this->currentLabel = null;
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
            screenshotPng: $this->driver->screenshot(),
            caption: $caption,
            stepIndex: $stepIndex,
            progress: max(0, $progress),
            durationMs: $durationMs,
            outcome: $outcome,
            verified: $verified,
            assertions: $assertions,
        );
    }
}
