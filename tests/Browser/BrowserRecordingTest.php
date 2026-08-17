<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use PHPUnit\Framework\ExpectationFailedException;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Config\Timeouts;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\Firefox\LaunchOptions;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\Report\Moment;
use Vusys\Tetryon\PHPUnit\Report\ReportRenderer;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Verifies a failing gesture or assertion captures a diagnostic moment (with
 * the selector trace, when available) in the recording's report — driven
 * against a real Firefox, since {@see FirefoxBiDiDriver} is final and cannot
 * be doubled.
 */
final class BrowserRecordingTest extends TestCase
{
    private ?StaticSiteServer $server = null;

    private ?FirefoxBiDiDriver $driver = null;

    private string $reportPath = '';

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping recording test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
        $this->reportPath = sys_get_temp_dir().'/tetryon-recording-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->driver?->stop();
        $this->server?->stop();
        self::deleteTree($this->reportPath);
    }

    public function test_a_failing_gesture_captures_the_selector_trace_and_rethrows(): void
    {
        $server = $this->server ?? self::fail('Static-site server did not start.');

        $this->driver = new FirefoxBiDiDriver(new LaunchOptions(headless: true));
        $this->driver->start();
        $this->driver->navigate($server->baseUrl.'/index.html');

        $configuration = new Configuration(
            baseUrl: $server->baseUrl,
            timeouts: new Timeouts(default: 200, navigation: 5000, assertion: 200),
        );
        $browser = new Browser($this->driver, $configuration)->recording('A failing step');

        try {
            $browser->beat('Click "Does not exist"')->click('Does not exist');
            self::fail('Expected an ElementNotFoundException.');
        } catch (ElementNotFoundException) {
            // expected — the recording must still see this and rethrow it.
        }

        $recording = $browser->finishedRecording('BrowserRecordingTest::test_a_failing_gesture', false, null, 'A failing step')
            ?? self::fail('Expected a recording.');

        self::assertFalse($recording->passed);
        $failureMoment = $recording->moments[1] ?? self::fail('Expected a failure moment.');
        self::assertSame('failed', $failureMoment->outcome);
        self::assertNotNull($failureMoment->selectorFailure);
        self::assertSame('Does not exist', $failureMoment->selectorFailure->target);
        self::assertNotEmpty($failureMoment->selectorFailure->attempts);

        $indexPath = ReportRenderer::render([$recording], $this->reportPath, $configuration)
            ?? self::fail('Expected the report to render.');
        $html = (string) file_get_contents($indexPath);
        self::assertStringContainsString('"target":"Does not exist"', $html);
    }

    public function test_a_failing_assertion_captures_its_own_moment_and_rethrows(): void
    {
        $server = $this->server ?? self::fail('Static-site server did not start.');

        $this->driver = new FirefoxBiDiDriver(new LaunchOptions(headless: true));
        $this->driver->start();
        $this->driver->navigate($server->baseUrl.'/index.html');

        $configuration = new Configuration(
            baseUrl: $server->baseUrl,
            timeouts: new Timeouts(default: 200, navigation: 5000, assertion: 200),
        );
        $browser = new Browser($this->driver, $configuration)->recording('A failing assertion');

        try {
            $browser->beat('Look for text that is not on the page')
                ->assertSee('This text does not appear anywhere on the page');
            self::fail('Expected an ExpectationFailedException.');
        } catch (ExpectationFailedException) {
            // expected — the recording must still see this and rethrow it.
        }

        $recording = $browser->finishedRecording('BrowserRecordingTest::test_a_failing_assertion', false, null, 'A failing assertion')
            ?? self::fail('Expected a recording.');

        self::assertFalse($recording->passed);

        $failureMoment = null;
        foreach ($recording->moments as $moment) {
            if ($moment->outcome === 'failed' && str_starts_with($moment->caption, 'assertSee(')) {
                $failureMoment = $moment;
            }
        }

        self::assertInstanceOf(Moment::class, $failureMoment, 'Expected a failure moment captioned with the assertion itself.');
        self::assertSame(
            'assertSee("This text does not appear anywhere on the page")',
            $failureMoment->caption,
        );
        self::assertNull($failureMoment->selectorFailure);
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
