<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

use Throwable;

/**
 * Accumulates slides handed off by every recorder-instrumented test in the
 * process into one combined video — "run the suite, get one video of it
 * running and turning green" (issue #102).
 *
 * Kept grouped by test (rather than flattened) until render time, so each
 * slide can be stamped with its test's position in the whole run — "test 42
 * of 160" plus an overall progress strip (see {@see Slide::withSuitePosition()}
 * and {@see SlideCompositor}) — which isn't knowable until every test has
 * finished and the total is known.
 *
 * Gated behind `TETRYON_RECORD_SUITE` so an ordinary `composer test:todomvc`
 * run pays neither the memory cost of holding every screenshot nor the
 * compositing/encoding cost — {@see append()} is a no-op unless the flag is
 * set. The compose+encode pipeline itself only ever runs once, at process
 * shutdown.
 */
final class SuiteRecording
{
    /** @var list<list<Slide>> one entry per test that handed off slides */
    private static array $testRecordings = [];

    private static bool $shutdownRegistered = false;

    public static function enabled(): bool
    {
        $flag = getenv('TETRYON_RECORD_SUITE');

        return is_string($flag) && ! in_array($flag, ['', '0', 'false'], true);
    }

    /**
     * @param  list<Slide>  $slides
     */
    public static function append(array $slides): void
    {
        if ($slides === [] || ! self::enabled()) {
            return;
        }

        self::$testRecordings[] = $slides;
        self::registerShutdownRender();
    }

    /**
     * Render every accumulated slide into one `.mp4`. Never throws — a
     * missing tool or a compositing/encoding failure is logged to stderr,
     * matching how a single test's SlideshowRecorder degrades (#102,
     * Decision 3), since there is no PHPUnit test left to report through by
     * the time this runs.
     */
    public static function render(string $outputPath): ?string
    {
        if (self::$testRecordings === []) {
            return null;
        }

        $magick = ExternalTool::locate('magick');
        $ffmpeg = ExternalTool::locate('ffmpeg');
        if ($magick === null || $ffmpeg === null) {
            fwrite(STDERR, "\nTetryon: skipping suite recording — magick and/or ffmpeg not found on PATH.\n");

            return null;
        }

        try {
            $slides = self::positionedSlides();
            $workingDirectory = dirname($outputPath).'/suite-recording';
            $result = SlideshowEncoder::encode($magick, $ffmpeg, $slides, $workingDirectory, $outputPath);
            fwrite(STDERR, sprintf(
                "\nTetryon: suite recording (%d tests, %d slides) written to %s\n",
                count(self::$testRecordings),
                count($slides),
                $result,
            ));

            return $result;
        } catch (Throwable $e) {
            fwrite(STDERR, "\nTetryon: suite recording failed ({$e->getMessage()}).\n");

            return null;
        }
    }

    /**
     * Test-only: drop accumulated state between scenarios.
     */
    public static function reset(): void
    {
        self::$testRecordings = [];
        self::$shutdownRegistered = false;
    }

    /**
     * @return list<Slide>
     */
    private static function positionedSlides(): array
    {
        $total = count(self::$testRecordings);

        $slides = [];
        foreach (self::$testRecordings as $testIndex => $testSlides) {
            foreach ($testSlides as $slide) {
                $slides[] = $slide->withSuitePosition($testIndex + 1, $total);
            }
        }

        return $slides;
    }

    private static function registerShutdownRender(): void
    {
        if (self::$shutdownRegistered || ! self::enabled()) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            $path = getenv('TETRYON_SUITE_RECORDING_PATH');
            self::render(is_string($path) && $path !== '' ? $path : 'tests/Browser/Artifacts/suite.mp4');
        });
    }
}
