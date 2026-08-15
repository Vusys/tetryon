<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use InvalidArgumentException;
use Throwable;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\PHPUnit\Recording\ConcatScript;
use Vusys\Tetryon\PHPUnit\Recording\Exception\RecordingException;
use Vusys\Tetryon\PHPUnit\Recording\ExternalTool;
use Vusys\Tetryon\PHPUnit\Recording\ProcessRunner;
use Vusys\Tetryon\PHPUnit\Recording\Slide;
use Vusys\Tetryon\PHPUnit\Recording\SlideCompositor;

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
 */
final class SlideshowRecorder
{
    private const int STEP_START_HOLD_MS = 500;

    private const int TYPING_FRAME_MS = 70;

    private const int NOTE_HOLD_MS = 1200;

    private const int MIN_STEP_HOLD_MS = 600;

    private const int SWEEP_FRAMES = 6;

    private const int MIN_FRAME_MS = 40;

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
            return $this->composeAndEncode($magick, $ffmpeg, $outputPath);
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

    private function composeAndEncode(string $magick, string $ffmpeg, string $outputPath): string
    {
        $workingDirectory = rtrim($this->artifactDirectory, '/').'/slideshow-'.substr(sha1($outputPath), 0, 8);
        if (! is_dir($workingDirectory) && ! @mkdir($workingDirectory, 0o777, true) && ! is_dir($workingDirectory)) {
            throw new RecordingException("Could not create the working directory \"{$workingDirectory}\".");
        }

        // ffmpeg's concat demuxer resolves relative paths in the list file
        // against the list file's own directory, not the process cwd — an
        // absolute working directory sidesteps that ambiguity entirely.
        $workingDirectory = realpath($workingDirectory) ?: $workingDirectory;

        $compositor = new SlideCompositor($magick, $this->title, $this->totalSteps);

        $framePaths = [];
        $durationsMs = [];
        foreach ($this->slides as $index => $slide) {
            $framePath = "{$workingDirectory}/frame-{$index}.png";
            $compositor->compose($slide, $workingDirectory, $framePath);
            $framePaths[] = $framePath;
            $durationsMs[] = $slide->durationMs;
        }

        $concatPath = "{$workingDirectory}/concat.txt";
        file_put_contents($concatPath, ConcatScript::build($framePaths, $durationsMs));

        ProcessRunner::run([
            $ffmpeg, '-y', '-f', 'concat', '-safe', '0', '-i', $concatPath,
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', '-pix_fmt', 'yuv420p', $outputPath,
        ]);

        return $outputPath;
    }

    private function captureFrame(string $caption, int $durationMs): void
    {
        $this->slides[] = new Slide(
            screenshotPng: $this->driver->screenshot(),
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
