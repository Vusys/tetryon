<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

use InvalidArgumentException;

/**
 * Builds an ffmpeg concat-demuxer script with an explicit duration per frame,
 * so playback timing reflects how long each step actually took rather than a
 * fixed frame rate.
 */
final class ConcatScript
{
    /**
     * @param  list<string>  $framePaths
     * @param  list<int>  $durationsMs
     */
    public static function build(array $framePaths, array $durationsMs): string
    {
        if ($framePaths === [] || count($framePaths) !== count($durationsMs)) {
            throw new InvalidArgumentException('ConcatScript needs one duration per frame, and at least one frame.');
        }

        $lines = [];
        foreach ($framePaths as $index => $path) {
            $lines[] = "file '".self::escape($path)."'";
            $lines[] = sprintf('duration %.3f', $durationsMs[$index] / 1000);
        }

        // The concat demuxer ignores the last entry's `duration` — ffmpeg's own
        // docs work around this by repeating the final file with no duration,
        // which is what keeps the last frame held rather than flashed for 0s.
        $lines[] = "file '".self::escape($framePaths[array_key_last($framePaths)])."'";

        return implode("\n", $lines)."\n";
    }

    private static function escape(string $path): string
    {
        return str_replace("'", "'\\''", $path);
    }
}
