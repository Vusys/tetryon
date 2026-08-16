<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Core\Selector\ResolutionAttempt;
use Vusys\Tetryon\PHPUnit\Report\Moment;
use Vusys\Tetryon\PHPUnit\Report\ReportManifest;
use Vusys\Tetryon\PHPUnit\Report\TestRecording;

#[CoversClass(ReportManifest::class)]
final class ReportManifestTest extends TestCase
{
    public function test_it_summarises_pass_fail_counts(): void
    {
        $manifest = ReportManifest::build([
            $this->recording('A::test_one', passed: true),
            $this->recording('A::test_two', passed: false),
            $this->recording('A::test_three', passed: true),
        ], [['a.webp'], ['b.webp'], ['c.webp']], [null, 'diagnostics/a-test-two', null]);

        self::assertSame(['total' => 3, 'passed' => 2, 'failed' => 1], $manifest['summary']);
    }

    public function test_it_stamps_suite_position_from_array_order(): void
    {
        $manifest = ReportManifest::build([
            $this->recording('A::test_one'),
            $this->recording('A::test_two'),
        ], [['a.webp'], ['b.webp']], [null, null]);

        self::assertSame(1, $this->test($manifest, 0)['index']);
        self::assertSame(2, $this->test($manifest, 1)['index']);
        self::assertSame(2, $this->test($manifest, 0)['total']);
    }

    public function test_it_attaches_the_screenshot_path_per_moment(): void
    {
        $manifest = ReportManifest::build(
            [$this->recording('A::test_one')],
            [['screenshots/a/000.webp']],
            [null],
        );

        self::assertSame('screenshots/a/000.webp', $this->moment($manifest, 0, 0)['src']);
    }

    public function test_it_carries_the_selector_trace_when_present(): void
    {
        $failure = new ElementNotFoundException('Save changes', [
            new ResolutionAttempt('label text', 0),
            new ResolutionAttempt('ARIA name', 0),
        ]);
        $moment = new Moment(
            screenshotPng: 'bytes',
            caption: 'Click "Save changes"',
            stepIndex: 2,
            totalSteps: 3,
            progress: 1,
            durationMs: 0,
            outcome: 'failed',
            selectorFailure: $failure,
        );
        $recording = new TestRecording('A::test_one', 'Test one', 3, false, [$moment]);

        $manifest = ReportManifest::build([$recording], [['a.webp']], [null]);

        $trace = $this->moment($manifest, 0, 0)['selectorTrace'];
        self::assertIsArray($trace);
        self::assertSame('Save changes', $trace['target']);
        self::assertSame([
            ['description' => 'label text', 'matchCount' => 0],
            ['description' => 'ARIA name', 'matchCount' => 0],
        ], $trace['attempts']);
    }

    public function test_it_carries_no_selector_trace_when_absent(): void
    {
        $manifest = ReportManifest::build([$this->recording('A::test_one')], [['a.webp']], [null]);

        self::assertNull($this->moment($manifest, 0, 0)['selectorTrace']);
    }

    public function test_it_carries_the_assertion_calls_a_moment_proved(): void
    {
        $moment = new Moment(
            screenshotPng: 'bytes',
            caption: 'Sees "1 item left"',
            stepIndex: 2,
            totalSteps: 2,
            progress: 2,
            durationMs: 0,
            verified: true,
            assertions: ['assertSee("1 item left")', 'assertValue(".new-todo", "")'],
        );
        $recording = new TestRecording('A::test_one', 'Test one', 2, true, [$moment]);

        $manifest = ReportManifest::build([$recording], [['a.webp']], [null]);

        self::assertSame(
            ['assertSee("1 item left")', 'assertValue(".new-todo", "")'],
            $this->moment($manifest, 0, 0)['assertions'],
        );
    }

    public function test_it_carries_an_empty_assertion_list_by_default(): void
    {
        $manifest = ReportManifest::build([$this->recording('A::test_one')], [['a.webp']], [null]);

        self::assertSame([], $this->moment($manifest, 0, 0)['assertions']);
    }

    private function recording(string $testId, bool $passed = true): TestRecording
    {
        $moment = new Moment(
            screenshotPng: 'bytes',
            caption: 'A step',
            stepIndex: 1,
            totalSteps: 1,
            progress: 1,
            durationMs: 10,
        );

        return new TestRecording($testId, $testId, 1, $passed, [$moment]);
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function test(array $manifest, int $index): array
    {
        $tests = $manifest['tests'];
        self::assertIsArray($tests);
        $test = $tests[$index];
        self::assertIsArray($test);

        return $test;
    }

    /**
     * @param  array<string, mixed>  $manifest
     * @return array<string, mixed>
     */
    private function moment(array $manifest, int $testIndex, int $momentIndex): array
    {
        $moments = $this->test($manifest, $testIndex)['moments'];
        self::assertIsArray($moments);
        $moment = $moments[$momentIndex];
        self::assertIsArray($moment);

        return $moment;
    }
}
