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
use Vusys\Tetryon\PHPUnit\Report\TestRecording;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Verifies a failing gesture or assertion captures a diagnostic moment (with
 * the selector trace, when available) in the recording's report, and that
 * the beat it happened in doesn't also get a redundant neutral "after"
 * moment on top of the failure — driven against a real Firefox, since
 * {@see FirefoxBiDiDriver} is final and cannot be doubled.
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
        $configuration = $this->startBrowser();
        $browser = new Browser($this->driver ?? self::fail('Driver did not start.'), $configuration)
            ->recording('A failing step');

        try {
            $browser->beat('Click "Does not exist"', fn (Browser $b): Browser => $b->click('Does not exist'));
            self::fail('Expected an ElementNotFoundException.');
        } catch (ElementNotFoundException) {
            // expected — the recording must still see this and rethrow it.
        }

        $recording = $browser->finishedRecording('BrowserRecordingTest::test_a_failing_gesture', false, null, 'A failing step')
            ?? self::fail('Expected a recording.');

        self::assertFalse($recording->passed);
        // before-frame, failure-frame, closing "Failed" frame — no redundant
        // neutral "· Nms" moment sandwiched in after the failure.
        self::assertCount(3, $recording->moments);

        $failureMoment = $recording->moments[1];
        self::assertSame('failed', $failureMoment->outcome);
        self::assertNotNull($failureMoment->selectorFailure);
        self::assertSame('Does not exist', $failureMoment->selectorFailure->target);
        self::assertNotEmpty($failureMoment->selectorFailure->attempts);

        $closingMoment = $recording->moments[2];
        self::assertSame('Failed', $closingMoment->caption);
        self::assertSame('failed', $closingMoment->outcome);

        $indexPath = ReportRenderer::render([$recording], $this->reportPath, $configuration)
            ?? self::fail('Expected the report to render.');
        $html = (string) file_get_contents($indexPath);
        self::assertStringContainsString('"target":"Does not exist"', $html);
    }

    public function test_a_failing_assertion_captures_its_own_moment_and_rethrows(): void
    {
        $configuration = $this->startBrowser();
        $browser = new Browser($this->driver ?? self::fail('Driver did not start.'), $configuration)
            ->recording('A failing assertion');

        try {
            $browser->beat(
                'Look for text that is not on the page',
                fn (Browser $b): Browser => $b->assertSee('This text does not appear anywhere on the page'),
            );
            self::fail('Expected an ExpectationFailedException.');
        } catch (ExpectationFailedException) {
            // expected — the recording must still see this and rethrow it.
        }

        $recording = $browser->finishedRecording('BrowserRecordingTest::test_a_failing_assertion', false, null, 'A failing assertion')
            ?? self::fail('Expected a recording.');

        self::assertFalse($recording->passed);
        // before-frame, failure-frame, closing "Failed" frame — same shape as
        // a failing gesture: no redundant neutral moment after the failure.
        self::assertCount(3, $recording->moments);

        $failureMoment = $recording->moments[1];
        self::assertSame('failed', $failureMoment->outcome);
        self::assertSame(
            'assertSee("This text does not appear anywhere on the page")',
            $failureMoment->caption,
        );
        self::assertNull($failureMoment->selectorFailure);

        $closingMoment = $recording->moments[2];
        self::assertSame('Failed', $closingMoment->caption);
        self::assertSame('failed', $closingMoment->outcome);
    }

    public function test_a_beat_runs_its_gesture_even_when_recording_was_never_turned_on(): void
    {
        $configuration = $this->startBrowser();
        $browser = new Browser($this->driver ?? self::fail('Driver did not start.'), $configuration);

        // No ->recording() call at all — beat() must still run its body;
        // recording only ever changes whether the gesture is photographed,
        // never whether it happens.
        $browser->beat('Follow the link', fn (Browser $b): Browser => $b->click('Next page'))
            ->assertSee('The second page');

        self::assertNull(
            $browser->finishedRecording('BrowserRecordingTest::test_a_beat_runs_without_recording', true, null, 'unused'),
        );
    }

    public function test_a_slow_step_is_recorded_as_the_frames_it_went_through(): void
    {
        $browser = $this->recordingBrowser('/recording.html');

        $browser->beat('Save', fn (Browser $b): Browser => $b->click('Save')->waitForText('Saved'));

        // Idle → "Saving…" → "Saved": the in-between state only exists
        // while waitForText() is still polling, so it is in the recording
        // only because a wait photographs the page each time it comes up empty.
        self::assertGreaterThanOrEqual(3, count($this->distinctImages($browser)));
    }

    public function test_a_beat_closes_on_a_frame_that_has_stopped_changing(): void
    {
        $browser = $this->recordingBrowser('/recording.html');

        // No wait and no assertion in the body: click() returns before the
        // panel's fade has begun, so nothing but the beat's own settling
        // stands between this and a closing frame identical to the opening one.
        $browser->beat('Reveal the panel', fn (Browser $b): Browser => $b->click('Reveal'));

        // Long enough for the fade to finish, so the recording's last moment
        // (taken as it is finished) is the fully revealed panel.
        usleep(600_000);
        $moments = $this->finished($browser)->moments;
        $closing = $moments[count($moments) - 2];

        self::assertStringStartsWith('Reveal the panel · ', $closing->caption);
        self::assertNotSame($moments[0]->screenshot, $closing->screenshot);
        self::assertSame($moments[count($moments) - 1]->screenshot, $closing->screenshot);
    }

    public function test_choosing_a_select_option_records_the_options_and_the_choice(): void
    {
        $browser = $this->recordingBrowser('/recording.html');

        $browser->beat('Pick a colour', fn (Browser $b): Browser => $b->select('Colour', 'Green'));

        $overlays = array_values(array_filter(array_map(
            static fn (Moment $moment): ?array => $moment->overlay,
            $this->finished($browser)->moments,
        )));

        // The native option list never opens — select() sets the value by
        // script — so the report draws it from these: once as the gesture
        // lands, once more with the choice made.
        self::assertCount(2, $overlays);
        self::assertSame(['Red', 'Green', 'Blue'], $overlays[0]['options']);
        self::assertSame('Red', $overlays[0]['selected']);
        self::assertSame('Green', $overlays[1]['selected']);
        self::assertGreaterThan(0, $overlays[0]['w']);
        self::assertGreaterThan(0, $overlays[0]['vw']);
    }

    public function test_a_gesture_outside_a_beat_is_not_measured(): void
    {
        $browser = $this->recordingBrowser('/recording.html');

        $browser->select('Colour', 'Blue');

        self::assertCount(1, $this->finished($browser)->moments);
    }

    private function recordingBrowser(string $path): Browser
    {
        $configuration = $this->startBrowser(timeout: 2000);
        $this->driver?->navigate($configuration->baseUrl.$path);

        return new Browser($this->driver ?? self::fail('Driver did not start.'), $configuration)->recording();
    }

    private function finished(Browser $browser): TestRecording
    {
        return $browser->finishedRecording('BrowserRecordingTest::test', true, null, 'A recording')
            ?? self::fail('Expected a recording.');
    }

    /**
     * @return list<string>
     */
    private function distinctImages(Browser $browser): array
    {
        return array_values(array_unique(array_map(
            static fn (Moment $moment): string => md5($moment->screenshot),
            $this->finished($browser)->moments,
        )));
    }

    private function startBrowser(int $timeout = 200): Configuration
    {
        $server = $this->server ?? self::fail('Static-site server did not start.');

        $this->driver = new FirefoxBiDiDriver(new LaunchOptions(headless: true));
        $this->driver->start();
        $this->driver->navigate($server->baseUrl.'/index.html');

        return new Configuration(
            baseUrl: $server->baseUrl,
            timeouts: new Timeouts(default: $timeout, navigation: 5000, assertion: $timeout),
        );
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
