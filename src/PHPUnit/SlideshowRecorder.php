<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use InvalidArgumentException;
use Throwable;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\PHPUnit\Recording\ExternalTool;
use Vusys\Tetryon\PHPUnit\Recording\Slide;
use Vusys\Tetryon\PHPUnit\Recording\SlideshowEncoder;
use Vusys\Tetryon\PHPUnit\Recording\SuiteRecording;

/**
 * Spike (issue #102): records a browser test as an annotated slideshow — a
 * screenshot per meaningful moment, framed with a title bar, a captioned
 * timing, and a chaptered progress timeline, assembled into an `.mp4`.
 *
 * WebDriver BiDi has no screencast API and the driver is single-threaded, so
 * a frame can only be captured between commands — true motion video isn't
 * available from the main thread. This composites discrete frames instead,
 * which is closer to a trace viewer than a screen recording.
 *
 * Soft-optional by design: {@see render()} never throws. Missing `magick`/
 * `ffmpeg`, or any failure while compositing or encoding, is reported through
 * {@see skipReason()} instead — recording must never be why a test fails.
 *
 * A test can render its own `.mp4` via {@see render()}, or hand its slides
 * off to {@see SuiteRecording} via {@see slides()} so many tests combine into
 * one video of the whole run — see {@see InteractsWithBrowser::recorder()}.
 */
final class SlideshowRecorder
{
    private const int STEP_START_HOLD_MS = 1200;

    private const int TYPING_FRAME_MS = 90;

    private const int NOTE_HOLD_MS = 1800;

    private const int MIN_STEP_HOLD_MS = 1800;

    private const int SWEEP_FRAMES = 6;

    private const int MIN_FRAME_MS = 50;

    private int $stepIndex = 0;

    /** @var list<Slide> */
    private array $slides = [];

    private ?string $skipReason = null;

    public function __construct(
        private readonly FirefoxBiDiDriver $driver,
        private readonly string $artifactDirectory,
        private readonly string $title,
        private readonly int $totalSteps,
    ) {
        if ($totalSteps < 1) {
            throw new InvalidArgumentException('SlideshowRecorder needs at least one total step.');
        }
    }

    /**
     * Add a standalone slide — a beat that isn't tied to a timed action, e.g.
     * "Opening state" before the first step. Does not consume a step number.
     */
    public function note(string $label): self
    {
        $this->captureFrame($label, self::NOTE_HOLD_MS);

        return $this;
    }

    /**
     * Fill a field one growing prefix at a time, snapping a frame per
     * character so the slideshow reads as the text being typed, then holds
     * the finished value while the timeline sweeps across this step's
     * segment for the time the whole fill took.
     */
    public function type(Browser $browser, string $field, string $value, string $label): self
    {
        $this->stepIndex++;
        $this->captureFrame($label, self::STEP_START_HOLD_MS);

        $browser->clear($field);
        $start = microtime(true);
        foreach (mb_str_split($value) as $character) {
            $browser->type($field, $character);
            $this->captureFrame($label, self::TYPING_FRAME_MS);
        }
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->appendSweep($label, $durationMs);

        return $this;
    }

    /**
     * Time an arbitrary action and capture it as one step: a "before" frame,
     * then — once the action returns — a run of frames over the measured
     * duration during which the timeline sweeps this step's segment from
     * empty to full, so the bar's speed reflects how long the step actually
     * took.
     *
     * @param  callable(): void  $action
     */
    public function step(string $label, callable $action): self
    {
        $this->stepIndex++;
        $this->captureFrame($label, self::STEP_START_HOLD_MS);

        $start = microtime(true);
        $action();
        $durationMs = (int) round((microtime(true) - $start) * 1000);

        $this->appendSweep($label, $durationMs);

        return $this;
    }

    /**
     * Append the test's closing frame: the timeline held complete, tinted and
     * captioned with its outcome. Called automatically by
     * {@see InteractsWithBrowser} for any test that used {@see recorder()};
     * only call this directly when driving a recorder outside that trait.
     */
    public function result(bool $passed): self
    {
        $this->slides[] = new Slide(
            screenshotPng: $this->driver->screenshot(),
            title: $this->title,
            totalSteps: $this->totalSteps,
            stepLabel: $this->headerStepLabel(),
            caption: $passed ? 'Passed' : 'Failed',
            progress: $this->totalSteps,
            durationMs: self::NOTE_HOLD_MS,
            outcome: $passed ? 'passed' : 'failed',
        );

        return $this;
    }

    /**
     * Every slide recorded so far — the escape hatch for handing this
     * recorder's frames off to a combined recording instead of (or as well
     * as) rendering its own `.mp4`.
     *
     * @return list<Slide>
     */
    public function slides(): array
    {
        return $this->slides;
    }

    /**
     * Composite every recorded slide into an `.mp4` at $outputPath. Returns
     * the path on success, or null if recording was skipped or failed — check
     * {@see skipReason()} for why. Never throws.
     */
    public function render(string $outputPath): ?string
    {
        $this->skipReason = null;

        if ($this->slides === []) {
            $this->skipReason = 'Tetryon: nothing was recorded — no note()/type()/step() calls were made.';

            return null;
        }

        $magick = ExternalTool::locate('magick');
        $ffmpeg = ExternalTool::locate('ffmpeg');
        if ($magick === null || $ffmpeg === null) {
            $missing = array_keys(array_filter(
                ['magick' => $magick, 'ffmpeg' => $ffmpeg],
                static fn (?string $path): bool => $path === null,
            ));
            $this->skipReason = sprintf(
                'Tetryon: skipping slideshow recording — %s not found on PATH.',
                implode(' and ', $missing),
            );

            return null;
        }

        try {
            $workingDirectory = rtrim($this->artifactDirectory, '/').'/slideshow-'.substr(sha1($outputPath), 0, 8);

            return SlideshowEncoder::encode($magick, $ffmpeg, $this->slides, $workingDirectory, $outputPath);
        } catch (Throwable $e) {
            $this->skipReason = "Tetryon: slideshow rendering failed ({$e->getMessage()}); continuing without a recording.";

            return null;
        }
    }

    /**
     * Set only after {@see render()} returns null — explains why (missing
     * tools, a compositing/encoding failure, or nothing to render).
     */
    public function skipReason(): ?string
    {
        return $this->skipReason;
    }

    private function captureFrame(string $caption, int $durationMs): void
    {
        $this->slides[] = new Slide(
            screenshotPng: $this->driver->screenshot(),
            title: $this->title,
            totalSteps: $this->totalSteps,
            stepLabel: $this->headerStepLabel(),
            caption: $caption,
            progress: max(0, $this->stepIndex - 1),
            durationMs: max($durationMs, self::MIN_FRAME_MS),
        );
    }

    private function appendSweep(string $label, int $durationMs): void
    {
        $screenshot = $this->driver->screenshot();
        $caption = sprintf('%s · %dms', $label, $durationMs);
        $held = max($durationMs, self::MIN_STEP_HOLD_MS);
        $perFrameMs = max(intdiv($held, self::SWEEP_FRAMES), self::MIN_FRAME_MS);

        for ($frame = 1; $frame <= self::SWEEP_FRAMES; $frame++) {
            $this->slides[] = new Slide(
                screenshotPng: $screenshot,
                title: $this->title,
                totalSteps: $this->totalSteps,
                stepLabel: $this->headerStepLabel(),
                caption: $caption,
                progress: ($this->stepIndex - 1) + ($frame / self::SWEEP_FRAMES),
                durationMs: $perFrameMs,
            );
        }
    }

    private function headerStepLabel(): string
    {
        return sprintf('step %d/%d', $this->stepIndex, $this->totalSteps);
    }
}
