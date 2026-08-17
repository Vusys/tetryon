<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Vusys\Tetryon\Core\Selector\ElementNotFoundException;

/**
 * One captured moment in a recorded test: a screenshot plus everything
 * {@see ReportRenderer} needs to place it on the timeline and caption it.
 *
 * @see RecordingSession::advanceBeat(), ::captureAssertion(), ::finish() —
 * the calls that produce a Moment.
 */
final readonly class Moment
{
    /**
     * @param  string  $screenshotPng  raw PNG bytes, as returned by FirefoxBiDiDriver::screenshot()
     * @param  string  $caption  what this moment shows
     * @param  int  $stepIndex  the beat this moment belongs to; 0 for a moment before the first beat
     * @param  float  $progress  timeline position in whole-beat units (2.6 means beat 3 is 60% done)
     * @param  int  $durationMs  how long this moment is held for during playback
     * @param  'passed'|'failed'|null  $outcome  set only on a closing or failing moment
     * @param  bool  $verified  set on an assertion's proof moment
     * @param  ElementNotFoundException|null  $selectorFailure  set when this moment's action failed to resolve a target
     * @param  list<string>  $assertions  the Browser assertion calls this moment proved, e.g. `assertSee("1 item left")`
     */
    public function __construct(
        public string $screenshotPng,
        public string $caption,
        public int $stepIndex,
        public float $progress,
        public int $durationMs,
        public ?string $outcome = null,
        public bool $verified = false,
        public ?ElementNotFoundException $selectorFailure = null,
        public array $assertions = [],
    ) {}
}
