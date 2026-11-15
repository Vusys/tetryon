<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

use Vusys\Tetryon\PHPUnit\FailureArtifacts;

/**
 * Turns a test id (or any free-form label) into a filesystem-safe path
 * segment — shared by {@see FailureArtifacts} and the
 * report renderer so a test's diagnostics and its report assets land under
 * the same name.
 */
final class Slug
{
    public static function forTestId(string $testId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $testId);
        $safe = $safe === null ? '' : trim($safe, '_');

        return $safe === '' ? 'test' : $safe;
    }
}
