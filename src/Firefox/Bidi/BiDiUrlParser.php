<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * Extracts the WebDriver BiDi WebSocket URL that Firefox prints to stderr at
 * startup, e.g.:
 *
 *     WebDriver BiDi listening on ws://127.0.0.1:41433
 *
 * Firefox advertises the base URL only; the session endpoint is that URL with
 * a `/session` path (see {@see BiDiConnection}).
 */
final class BiDiUrlParser
{
    public static function fromStartupLine(string $line): ?string
    {
        if (preg_match('#WebDriver BiDi listening on (ws://\S+)#', $line, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }

    public static function fromStartupLog(string $log): ?string
    {
        foreach (preg_split('/\R/', $log) ?: [] as $line) {
            $url = self::fromStartupLine($line);
            if ($url !== null) {
                return $url;
            }
        }

        return null;
    }
}
