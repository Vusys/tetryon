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
            new Moment(screenshot: $this->onePixelPng(), caption: 'Passed', stepIndex: 1, progress: 1, durationMs: 0, outcome: 'passed'),
        ]);

        $indexPath = ReportRenderer::render([$recording], $this->outputDirectory, new Configuration);

        self::assertSame("{$this->outputDirectory}/index.html", $indexPath);
        self::assertFileExists($indexPath);
        self::assertStringContainsString('"title":"Test one"', (string) file_get_contents($indexPath));

        $screenshots = glob("{$this->outputDirectory}/screenshots/*");
        self::assertNotFalse($screenshots);
        self::assertCount(1, $screenshots);
    }

    public function test_it_writes_an_image_shared_by_several_moments_once(): void
    {
        $still = $this->onePixelPng();
        $moment = static fn (string $caption): Moment => new Moment(screenshot: $still, caption: $caption, stepIndex: 1, progress: 1, durationMs: 0);

        $indexPath = ReportRenderer::render([
            new TestRecording('A::test_one', 'Test one', true, [$moment('Before'), $moment('After')]),
            new TestRecording('A::test_two', 'Test two', true, [$moment('Passed')]),
        ], $this->outputDirectory, new Configuration);

        self::assertNotNull($indexPath);
        $screenshots = glob("{$this->outputDirectory}/screenshots/*");
        self::assertNotFalse($screenshots);
        self::assertCount(1, $screenshots);
        self::assertSame(3, substr_count((string) file_get_contents($indexPath), '"src":"screenshots/'));
    }

    public function test_it_clears_images_left_by_an_earlier_render(): void
    {
        $recording = new TestRecording('A::test_one', 'Test one', true, [
            new Moment(screenshot: $this->onePixelPng(), caption: 'Passed', stepIndex: 1, progress: 1, durationMs: 0),
        ]);
        mkdir("{$this->outputDirectory}/screenshots", recursive: true);
        file_put_contents("{$this->outputDirectory}/screenshots/stale.webp", 'left over');

        ReportRenderer::render([$recording], $this->outputDirectory, new Configuration);

        self::assertFileDoesNotExist("{$this->outputDirectory}/screenshots/stale.webp");
        self::assertCount(1, (array) glob("{$this->outputDirectory}/screenshots/*"));
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
            new Moment(screenshot: $this->onePixelPng(), caption: 'Failed', stepIndex: 1, progress: 1, durationMs: 0, outcome: 'failed'),
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
            new Moment(screenshot: $this->onePixelPng(), caption: 'Passed', stepIndex: 1, progress: 1, durationMs: 0),
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

    public function test_parallel_workers_combine_into_one_report(): void
    {
        $red = $this->onePixelPng(255, 0, 0);
        $blue = $this->onePixelPng(0, 0, 255);

        ReportRenderer::renderPart([
            new TestRecording('Tests\\Browser\\ChartTest::test_one', 'One', true, [new Moment(screenshot: $red, caption: 'A', stepIndex: 1, progress: 1, durationMs: 0)]),
        ], $this->outputDirectory, new Configuration, 'run-1', '1');
        $indexPath = ReportRenderer::renderPart([
            new TestRecording('Tests\\Browser\\PersonTest::test_two', 'Two', false, [new Moment(screenshot: $blue, caption: 'B', stepIndex: 1, progress: 1, durationMs: 0)]),
        ], $this->outputDirectory, new Configuration, 'run-1', '2');

        self::assertSame("{$this->outputDirectory}/index.html", $indexPath);
        $manifest = $this->manifest();
        self::assertSame(['total' => 2, 'passed' => 1, 'failed' => 1], $manifest['summary']);
        self::assertSame(['One', 'Two'], array_column($manifest['tests'], 'title'));
        self::assertSame([1, 2], array_column($manifest['tests'], 'index'));
        self::assertSame([['Chart'], ['Person']], array_column($manifest['tests'], 'group'));
        self::assertCount(2, (array) glob("{$this->outputDirectory}/screenshots/*"), "worker 2 kept worker 1's image");
    }

    public function test_a_worker_prunes_only_images_and_parts_from_earlier_runs(): void
    {
        mkdir("{$this->outputDirectory}/screenshots", recursive: true);
        mkdir("{$this->outputDirectory}/parts/run-0", recursive: true);
        file_put_contents("{$this->outputDirectory}/screenshots/old.webp", 'earlier run');
        touch("{$this->outputDirectory}/screenshots/old.webp", time() - 3600);
        file_put_contents("{$this->outputDirectory}/parts/run-0/1.json", '[]');
        touch("{$this->outputDirectory}/parts/run-0", time() - 3600);
        // a worker still running wrote this, but hasn't listed it in a part yet
        file_put_contents("{$this->outputDirectory}/screenshots/in-flight.webp", 'this run');

        ReportRenderer::renderPart([
            new TestRecording('A::test_one', 'One', true, [new Moment(screenshot: $this->onePixelPng(), caption: 'A', stepIndex: 1, progress: 1, durationMs: 0)]),
        ], $this->outputDirectory, new Configuration, 'run-1', '1');

        self::assertFileDoesNotExist("{$this->outputDirectory}/screenshots/old.webp");
        self::assertFileExists("{$this->outputDirectory}/screenshots/in-flight.webp");
        self::assertDirectoryDoesNotExist("{$this->outputDirectory}/parts/run-0");
        self::assertCount(1, $this->manifest()['tests']);
    }

    /**
     * @return array{summary: array<string, int>, tests: list<array<string, mixed>>}
     */
    private function manifest(): array
    {
        $html = (string) file_get_contents("{$this->outputDirectory}/index.html");
        preg_match('#<script[^>]*type="application/json"[^>]*>(.*?)</script>#s', $html, $match);
        $manifest = json_decode($match[1] ?? self::fail('index.html has no manifest'), true, flags: JSON_THROW_ON_ERROR);
        self::assertIsArray($manifest);

        /** @var array{summary: array<string, int>, tests: list<array<string, mixed>>} $manifest */
        return $manifest;
    }

    /**
     * @param  int<0, 255>  $red
     * @param  int<0, 255>  $green
     * @param  int<0, 255>  $blue
     */
    private function onePixelPng(int $red = 0, int $green = 0, int $blue = 0): string
    {
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        $colour = imagecolorallocate($image, $red, $green, $blue);
        self::assertNotFalse($colour);
        imagesetpixel($image, 0, 0, $colour);
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
