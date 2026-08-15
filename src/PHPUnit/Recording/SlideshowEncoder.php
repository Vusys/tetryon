<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

use Vusys\Tetryon\PHPUnit\Recording\Exception\RecordingException;
use Vusys\Tetryon\PHPUnit\SlideshowRecorder;

/**
 * The compose-then-concatenate pipeline shared by a single test's
 * {@see SlideshowRecorder} and the whole-run {@see SuiteRecording}:
 * composite every slide to a PNG with {@see SlideCompositor}, then hand the
 * sequence to ffmpeg's concat demuxer with true per-frame durations.
 */
final class SlideshowEncoder
{
    /**
     * @param  list<Slide>  $slides
     */
    public static function encode(string $magickBinary, string $ffmpegBinary, array $slides, string $workingDirectory, string $outputPath): string
    {
        if (! is_dir($workingDirectory) && ! @mkdir($workingDirectory, 0o777, true) && ! is_dir($workingDirectory)) {
            throw new RecordingException("Could not create the working directory \"{$workingDirectory}\".");
        }

        // ffmpeg's concat demuxer resolves relative paths in the list file
        // against the list file's own directory, not the process cwd — an
        // absolute working directory sidesteps that ambiguity entirely.
        $workingDirectory = realpath($workingDirectory) ?: $workingDirectory;

        $compositor = new SlideCompositor($magickBinary);

        $framePaths = [];
        $durationsMs = [];
        foreach ($slides as $index => $slide) {
            $framePath = "{$workingDirectory}/frame-{$index}.png";
            $compositor->compose($slide, $workingDirectory, $framePath);
            $framePaths[] = $framePath;
            $durationsMs[] = $slide->durationMs;
        }

        $concatPath = "{$workingDirectory}/concat.txt";
        file_put_contents($concatPath, ConcatScript::build($framePaths, $durationsMs));

        ProcessRunner::run([
            $ffmpegBinary, '-y', '-f', 'concat', '-safe', '0', '-i', $concatPath,
            '-vf', 'scale=trunc(iw/2)*2:trunc(ih/2)*2', '-pix_fmt', 'yuv420p', $outputPath,
        ]);

        return $outputPath;
    }
}
