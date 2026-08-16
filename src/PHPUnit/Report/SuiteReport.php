<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\PHPUnit\Recorder;

/**
 * Accumulates recordings handed off by every recorder-instrumented test in
 * the process into one combined report — "run the suite, get one browsable
 * report of it running and turning green" (issue #102).
 *
 * Kept as a list of {@see TestRecording}s rather than a flat moment list, so
 * a test's position in the whole run ("test 42 of 160") is simply its index
 * — nothing needs to be stamped onto the data itself.
 *
 * Gated behind `TETRYON_SUITE_REPORT` so an ordinary `composer test:todomvc`
 * run pays neither the memory cost of holding every screenshot nor the
 * encoding cost — {@see append()} is a no-op unless the flag is set. The
 * render pipeline itself only ever runs once, at process shutdown.
 */
final class SuiteReport
{
    /** @var list<TestRecording> */
    private static array $recordings = [];

    private static bool $shutdownRegistered = false;

    public static function enabled(): bool
    {
        $flag = getenv('TETRYON_SUITE_REPORT');

        return is_string($flag) && ! in_array($flag, ['', '0', 'false'], true);
    }

    public static function append(TestRecording $recording): void
    {
        if (! self::enabled()) {
            return;
        }

        self::$recordings[] = $recording;
        self::registerShutdownRender();
    }

    /**
     * Render every accumulated recording into one report. Never throws —
     * {@see ReportRenderer::render()} already degrades to null on failure,
     * matching how a single test's {@see Recorder}
     * degrades (#102, Decision 3), since there is no PHPUnit test left to
     * report through by the time this runs.
     */
    public static function render(string $outputDirectory): ?string
    {
        if (self::$recordings === []) {
            return null;
        }

        $result = ReportRenderer::render(self::$recordings, $outputDirectory, Configuration::fromEnvironment());

        fwrite(STDERR, $result === null
            ? "\nTetryon: suite report failed to render.\n"
            : sprintf("\nTetryon: suite report (%d tests) written to %s\n", count(self::$recordings), $result));

        return $result;
    }

    /**
     * Test-only: drop accumulated state between scenarios.
     */
    public static function reset(): void
    {
        self::$recordings = [];
        self::$shutdownRegistered = false;
    }

    private static function registerShutdownRender(): void
    {
        if (self::$shutdownRegistered || ! self::enabled()) {
            return;
        }

        self::$shutdownRegistered = true;
        register_shutdown_function(static function (): void {
            $path = getenv('TETRYON_SUITE_REPORT_PATH');
            self::render(is_string($path) && $path !== '' ? $path : 'tests/Browser/Artifacts/suite-report');
        });
    }
}
