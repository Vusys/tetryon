<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\PHPUnit\Report\Moment;
use Vusys\Tetryon\PHPUnit\Report\SuiteReport;
use Vusys\Tetryon\PHPUnit\Report\TestRecording;

#[CoversClass(SuiteReport::class)]
final class SuiteReportTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir().'/tetryon-suite-report-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        SuiteReport::reset();
        putenv('TETRYON_SUITE_REPORT');
        self::deleteTree($this->outputDirectory);
    }

    public function test_enabled_reads_the_truthy_env_flag(): void
    {
        putenv('TETRYON_SUITE_REPORT=1');
        self::assertTrue(SuiteReport::enabled());

        putenv('TETRYON_SUITE_REPORT=0');
        self::assertFalse(SuiteReport::enabled());

        putenv('TETRYON_SUITE_REPORT');
        self::assertFalse(SuiteReport::enabled());
    }

    public function test_append_is_a_noop_when_disabled(): void
    {
        putenv('TETRYON_SUITE_REPORT');

        SuiteReport::append($this->recording('A::test_one'));

        self::assertNull(SuiteReport::render($this->outputDirectory));
    }

    public function test_render_returns_null_when_nothing_was_appended(): void
    {
        putenv('TETRYON_SUITE_REPORT=1');

        self::assertNull(SuiteReport::render($this->outputDirectory));
    }

    public function test_it_accumulates_and_renders_every_appended_recording(): void
    {
        putenv('TETRYON_SUITE_REPORT=1');

        SuiteReport::append($this->recording('A::test_one', 'Test one'));
        SuiteReport::append($this->recording('A::test_two', 'Test two'));

        $indexPath = SuiteReport::render($this->outputDirectory);

        self::assertNotNull($indexPath);
        $html = (string) file_get_contents($indexPath);
        self::assertStringContainsString('"title":"Test one"', $html);
        self::assertStringContainsString('"title":"Test two"', $html);
        self::assertStringContainsString('"total":2', $html);
    }

    private function recording(string $testId, string $title = 'A test'): TestRecording
    {
        $moment = new Moment(
            screenshotPng: $this->onePixelPng(),
            caption: 'A step',
            stepIndex: 1,
            totalSteps: 1,
            progress: 1,
            durationMs: 0,
        );

        return new TestRecording($testId, $title, 1, true, [$moment]);
    }

    private function onePixelPng(): string
    {
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        ob_start();
        imagepng($image);
        $png = ob_get_clean();
        self::assertIsString($png);

        return $png;
    }

    private static function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach ((array) scandir($path) as $entry) {
            if (! is_string($entry)) {
                continue;
            }
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            $full = "{$path}/{$entry}";
            is_dir($full) ? self::deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
