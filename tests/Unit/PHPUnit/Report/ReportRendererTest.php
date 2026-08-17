<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\PHPUnit\Report\Moment;
use Vusys\Tetryon\PHPUnit\Report\ReportRenderer;
use Vusys\Tetryon\PHPUnit\Report\TestRecording;

#[CoversClass(ReportRenderer::class)]
final class ReportRendererTest extends TestCase
{
    private string $outputDirectory;

    protected function setUp(): void
    {
        $this->outputDirectory = sys_get_temp_dir().'/tetryon-report-'.bin2hex(random_bytes(4));
    }

    protected function tearDown(): void
    {
        self::deleteTree($this->outputDirectory);
    }

    public function test_it_writes_screenshots_and_an_index_html(): void
    {
        $recording = new TestRecording('A::test_one', 'Test one', true, [
            new Moment(screenshotPng: $this->onePixelPng(), caption: 'Passed', stepIndex: 1, progress: 1, durationMs: 0, outcome: 'passed'),
        ]);

        $indexPath = ReportRenderer::render([$recording], $this->outputDirectory, new Configuration);

        self::assertSame("{$this->outputDirectory}/index.html", $indexPath);
        self::assertFileExists($indexPath);
        self::assertStringContainsString('"title":"Test one"', (string) file_get_contents($indexPath));

        $screenshots = glob("{$this->outputDirectory}/screenshots/*/*");
        self::assertNotFalse($screenshots);
        self::assertCount(1, $screenshots);
    }

    public function test_it_writes_diagnostics_for_a_failing_test(): void
    {
        $bag = new ArtifactBag(
            url: 'http://127.0.0.1:8000',
            screenshotPng: $this->onePixelPng(),
            html: '<html></html>',
            consoleLines: ['[error] boom'],
            networkLines: [],
            trace: '',
            browserStderr: '',
        );
        $recording = new TestRecording('A::test_two', 'Test two', false, [
            new Moment(screenshotPng: $this->onePixelPng(), caption: 'Failed', stepIndex: 1, progress: 1, durationMs: 0, outcome: 'failed'),
        ], $bag);

        ReportRenderer::render([$recording], $this->outputDirectory, new Configuration);

        self::assertFileExists("{$this->outputDirectory}/diagnostics/A_test_two/console.log");
        self::assertStringContainsString('boom', (string) file_get_contents("{$this->outputDirectory}/diagnostics/A_test_two/console.log"));
    }

    public function test_it_returns_null_for_no_recordings(): void
    {
        self::assertNull(ReportRenderer::render([], $this->outputDirectory, new Configuration));
    }

    public function test_it_returns_null_when_the_output_directory_cannot_be_created(): void
    {
        $blocker = sys_get_temp_dir().'/tetryon-report-blocker-'.bin2hex(random_bytes(4));
        file_put_contents($blocker, 'not a directory');

        $recording = new TestRecording('A::test_one', 'Test one', true, [
            new Moment(screenshotPng: $this->onePixelPng(), caption: 'Passed', stepIndex: 1, progress: 1, durationMs: 0),
        ]);

        try {
            self::assertNull(ReportRenderer::render([$recording], $blocker, new Configuration));
        } finally {
            @unlink($blocker);
        }
    }

    public function test_render_html_neutralises_a_script_breakout_in_a_caption(): void
    {
        $hostile = '</script><script>alert(1)</script>';
        $manifest = [
            'summary' => ['total' => 1, 'passed' => 1, 'failed' => 0],
            'tests' => [[
                'id' => 't', 'title' => $hostile, 'totalSteps' => 1, 'passed' => true,
                'index' => 1, 'total' => 1, 'diagnosticsDir' => null, 'diagnosticsReport' => null,
                'moments' => [],
            ]],
        ];

        $html = ReportRenderer::renderHtml($manifest);

        self::assertStringNotContainsString($hostile, $html);
        self::assertSame(2, substr_count($html, '</script>'));
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
