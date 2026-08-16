<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\PHPUnit\Recorder;

/**
 * One test's whole recording, handed off by {@see Recorder::recording()}
 * once the test finishes — its captured {@see Moment}s plus, for a failing
 * test, the {@see ArtifactBag} diagnostics to surface alongside them.
 */
final readonly class TestRecording
{
    /**
     * @param  string  $testId  e.g. "App\Tests\LoginTest::test_it_logs_in"
     * @param  list<Moment>  $moments
     */
    public function __construct(
        public string $testId,
        public string $title,
        public int $totalSteps,
        public bool $passed,
        public array $moments,
        public ?ArtifactBag $diagnostics = null,
    ) {}
}
