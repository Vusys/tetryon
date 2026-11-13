<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Vusys\Tetryon\Core\Selector\ElementNotFoundException;

/**
 * One captured moment in a recorded test: a screenshot plus everything
 * {@see ReportRenderer} needs to place it on the timeline and caption it.
 *
 * @see RecordingSession::runBeat(), ::sample(), ::captureAssertion(), ::finish() —
 * the calls that produce a Moment.
 */
final readonly class Moment
{
    /**
     * @param  string  $screenshot  image bytes — WebP when the recording could encode it, PNG otherwise
     * @param  string  $caption  what this moment shows
     * @param  int  $stepIndex  the beat this moment belongs to; 0 for a moment before the first beat
     * @param  float  $progress  timeline position in whole-beat units (2.6 means beat 3 is 60% done)
     * @param  int  $durationMs  how long the beat this moment closes took; 0 on any other moment
     * @param  'passed'|'failed'|null  $outcome  set only on a closing or failing moment
     * @param  bool  $verified  set on an assertion's proof moment
     * @param  ElementNotFoundException|null  $selectorFailure  set when this moment's action failed to resolve a target
     * @param  list<string>  $assertions  the Browser assertion calls this moment proved, e.g. `assertSee("1 item left")`
     * @param  int  $atMs  when this moment was captured, in ms since recording began — playback paces itself on the gaps
     * @param  array<string, mixed>|null  $overlay  the gesture about to act on this frame: its target's viewport rect
     *                                              (`x`,`y`,`w`,`h` within `vw`×`vh`) and, for a `<select>`, its `options` and `selected` label
     */
    public function __construct(
        public string $screenshot,
        public string $caption,
        public int $stepIndex,
        public float $progress,
        public int $durationMs,
        public ?string $outcome = null,
        public bool $verified = false,
        public ?ElementNotFoundException $selectorFailure = null,
        public array $assertions = [],
        public int $atMs = 0,
        public ?array $overlay = null,
    ) {}
}
