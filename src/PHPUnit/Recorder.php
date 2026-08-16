<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use InvalidArgumentException;
use Throwable;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\PHPUnit\Report\Moment;
use Vusys\Tetryon\PHPUnit\Report\ReportRenderer;
use Vusys\Tetryon\PHPUnit\Report\SuiteReport;
use Vusys\Tetryon\PHPUnit\Report\TestRecording;

/**
 * Records a browser test as a browsable HTML report: a screenshot at each
 * meaningful moment, captioned and timed, assembled into an interactive
 * `index.html` (issue #102).
 *
 * A test can render its own report via {@see render()}, or hand its
 * recording off to {@see SuiteReport} (automatically, via
 * {@see InteractsWithBrowser::recorder()}) so many tests combine into one
 * report of the whole run.
 *
 * Soft-optional by design: {@see render()} never throws. A failure while
 * writing or encoding is reported through {@see skipReason()} instead —
 * recording must never be why a test fails.
 */
final class Recorder
{
    private int $stepIndex = 0;

    /** @var list<Moment> */
    private array $moments = [];

    private ?bool $passed = null;

    private ?ArtifactBag $diagnostics = null;

    private ?string $skipReason = null;

    public function __construct(
        private readonly FirefoxBiDiDriver $driver,
        private readonly Configuration $configuration,
        private readonly string $testId,
        private readonly string $title,
        private readonly int $totalSteps,
    ) {
        if ($totalSteps < 1) {
            throw new InvalidArgumentException('Recorder needs at least one total step.');
        }
    }

    /**
     * Add a standalone moment — a beat that isn't tied to a timed action,
     * e.g. "Opening state" before the first step. Does not consume a step
     * number.
     */
    public function note(string $label): self
    {
        $this->captureMoment($label, max(0, $this->stepIndex - 1), 0);

        return $this;
    }

    /**
     * Fill a field and capture the step as two moments — before and after —
     * so the report shows what was there and what got typed, tagged with how
     * long it took.
     */
    public function type(Browser $browser, string $field, string $value, string $label): self
    {
        $this->stepIndex++;
        $this->captureMoment($label, $this->stepIndex - 1, 0);

        try {
            $start = microtime(true);
            $browser->clear($field);
            $browser->type($field, $value);
            $durationMs = (int) round((microtime(true) - $start) * 1000);
        } catch (Throwable $e) {
            $this->captureFailureMoment($label, $e);
            throw $e;
        }

        $this->captureMoment(sprintf('%s · %dms', $label, $durationMs), $this->stepIndex, $durationMs);

        return $this;
    }

    /**
     * Time an arbitrary action and capture it as two moments — a "before"
     * moment, then, once the action returns, an "after" moment captioned
     * with how long it took.
     *
     * @param  callable(): void  $action
     */
    public function step(string $label, callable $action): self
    {
        $this->stepIndex++;
        $this->captureMoment($label, $this->stepIndex - 1, 0);

        try {
            $start = microtime(true);
            $action();
            $durationMs = (int) round((microtime(true) - $start) * 1000);
        } catch (Throwable $e) {
            $this->captureFailureMoment($label, $e);
            throw $e;
        }

        $this->captureMoment(sprintf('%s · %dms', $label, $durationMs), $this->stepIndex, $durationMs);

        return $this;
    }

    /**
     * Run an assertion and capture the proof: a moment checkmarked and
     * captioned with what was confirmed, listing exactly which
     * {@see Browser} assertion calls ran and with what arguments — package
     * positioning is that assertions auto-wait and retry, and this is where
     * the report shows that off, not just the actions leading up to it.
     * Doesn't consume a step number. $browser must be the same instance the
     * assertion calls run against, so its assertion log can be drained.
     *
     * @param  callable(): void  $assertion
     */
    public function assert(Browser $browser, string $label, callable $assertion): self
    {
        $browser->drainAssertionLog();

        try {
            $assertion();
        } catch (Throwable $e) {
            $this->captureFailureMoment($label, $e);
            throw $e;
        }

        $this->moments[] = new Moment(
            screenshotPng: $this->driver->screenshot(),
            caption: $label,
            stepIndex: $this->stepIndex,
            totalSteps: $this->totalSteps,
            progress: $this->stepIndex,
            durationMs: 0,
            verified: true,
            assertions: $browser->drainAssertionLog(),
        );

        return $this;
    }

    /**
     * Append the test's closing moment and record its outcome. Called
     * automatically by {@see InteractsWithBrowser} for any test that used
     * {@see InteractsWithBrowser::recorder()}; only call this directly when
     * driving a recorder outside that trait.
     */
    public function result(bool $passed, ?ArtifactBag $diagnostics = null): self
    {
        $this->passed = $passed;
        $this->diagnostics = $diagnostics;

        $this->moments[] = new Moment(
            screenshotPng: $this->driver->screenshot(),
            caption: $passed ? 'Passed' : 'Failed',
            stepIndex: $this->totalSteps,
            totalSteps: $this->totalSteps,
            progress: $this->totalSteps,
            durationMs: 0,
            outcome: $passed ? 'passed' : 'failed',
        );

        return $this;
    }

    /**
     * This test's recording — the escape hatch for handing this recorder's
     * moments off to a combined report instead of (or as well as) rendering
     * its own `index.html`.
     */
    public function recording(): TestRecording
    {
        return new TestRecording(
            testId: $this->testId,
            title: $this->title,
            totalSteps: $this->totalSteps,
            passed: $this->passed ?? true,
            moments: $this->moments,
            diagnostics: $this->diagnostics,
        );
    }

    /**
     * Render this test's recording into a browsable report at
     * $outputDirectory. Returns the path to its `index.html` on success, or
     * null if nothing was recorded or rendering failed — check
     * {@see skipReason()} for why. Never throws.
     */
    public function render(string $outputDirectory): ?string
    {
        $this->skipReason = null;

        if ($this->moments === []) {
            $this->skipReason = 'Tetryon: nothing was recorded — no note()/type()/step()/assert() calls were made.';

            return null;
        }

        $result = ReportRenderer::render([$this->recording()], $outputDirectory, $this->configuration);
        if ($result === null) {
            $this->skipReason = 'Tetryon: report rendering failed; continuing without a report.';
        }

        return $result;
    }

    /**
     * Set only after {@see render()} returns null — explains why (nothing to
     * render, or a rendering failure).
     */
    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    private function captureMoment(string $caption, int $progress, int $durationMs): void
    {
        $this->moments[] = new Moment(
            screenshotPng: $this->driver->screenshot(),
            caption: $caption,
            stepIndex: $this->stepIndex,
            totalSteps: $this->totalSteps,
            progress: max(0, $progress),
            durationMs: $durationMs,
        );
    }

    /**
     * Captures the moment an action failed — guarded, since a driver that
     * just threw may be in a bad state. Carries the selector-resolution
     * trace when the failure was an {@see ElementNotFoundException}, since
     * that's only available here, not at teardown time.
     */
    private function captureFailureMoment(string $label, Throwable $exception): void
    {
        try {
            $screenshot = $this->driver->screenshot();
        } catch (Throwable) {
            return;
        }

        $this->moments[] = new Moment(
            screenshotPng: $screenshot,
            caption: $label,
            stepIndex: $this->stepIndex,
            totalSteps: $this->totalSteps,
            progress: max(0, $this->stepIndex - 1),
            durationMs: 0,
            outcome: 'failed',
            selectorFailure: $exception instanceof ElementNotFoundException ? $exception : null,
        );
    }
}
