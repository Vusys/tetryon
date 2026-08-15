<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

/**
 * Locates an external binary on `PATH` without shelling out to `which` (which
 * is itself not guaranteed to be present) — a bare directory scan is enough
 * and stays testable by injecting a `PATH` string.
 */
final class ExternalTool
{
    public static function locate(string $name, ?string $pathEnvironment = null): ?string
    {
        $pathEnvironment ??= getenv('PATH');
        if (! is_string($pathEnvironment) || $pathEnvironment === '') {
            return null;
        }

        foreach (explode(PATH_SEPARATOR, $pathEnvironment) as $directory) {
            if ($directory === '') {
                continue;
            }

            $candidate = rtrim($directory, '/').'/'.$name;
            if (is_file($candidate) && is_executable($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
