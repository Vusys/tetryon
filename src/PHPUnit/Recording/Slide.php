<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

/**
 * One frame of the rendered slideshow: a screenshot plus everything
 * {@see SlideCompositor} needs to draw the header, caption, and timeline
 * around it.
 */
final readonly class Slide
{
    /**
     * @param  string  $screenshotPng  raw PNG bytes, as returned by FirefoxBiDiDriver::screenshot()
     * @param  string  $stepLabel  e.g. "step 2/4", shown top-right
     * @param  float  $progress  timeline fill, in units of whole steps (2.6 means step 3 is 60% filled)
     * @param  int  $durationMs  how long this frame is held for during playback
     */
    public function __construct(
        public string $screenshotPng,
        public string $stepLabel,
        public string $caption,
        public float $progress,
        public int $durationMs,
    ) {}
}
