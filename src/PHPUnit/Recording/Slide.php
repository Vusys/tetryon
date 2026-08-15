<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

/**
 * One frame of a rendered slideshow: a screenshot plus everything
 * {@see SlideCompositor} needs to draw the header, caption, and timeline
 * around it. Self-describing (carries its own title/step count) rather than
 * depending on render-time context, so slides from different tests — with
 * different titles and step counts — can be concatenated into one combined
 * video by {@see SuiteRecording}.
 */
final readonly class Slide
{
    /**
     * @param  string  $screenshotPng  raw PNG bytes, as returned by FirefoxBiDiDriver::screenshot()
     * @param  string  $title  the test's title, shown top-left
     * @param  int  $totalSteps  how many segments the timeline is chaptered into
     * @param  string  $stepLabel  e.g. "step 2/4", shown top-right
     * @param  float  $progress  timeline fill, in units of whole steps (2.6 means step 3 is 60% filled)
     * @param  int  $durationMs  how long this frame is held for during playback
     * @param  'passed'|'failed'|null  $outcome  set only on the closing frame of a test
     */
    public function __construct(
        public string $screenshotPng,
        public string $title,
        public int $totalSteps,
        public string $stepLabel,
        public string $caption,
        public float $progress,
        public int $durationMs,
        public ?string $outcome = null,
    ) {}
}
