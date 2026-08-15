<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording\Exception;

use RuntimeException;
use Vusys\Tetryon\PHPUnit\SlideshowRecorder;

/**
 * Anything that goes wrong compositing or encoding a slideshow. Always caught
 * by {@see SlideshowRecorder::render()} — recording is soft-optional, so this
 * is never allowed to fail the test it was attached to.
 */
class RecordingException extends RuntimeException {}
