<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Diagnostics;

/**
 * Formats an {@see ArtifactBag} plus where it was written into the
 * human-readable failure report — the plain-text block printed to stderr on
 * a failing test, and the same text inlined into an HTML report's
 * diagnostics panel for at-a-glance reading.
 */
final class FailureReport
{
    /**
     * @param  array<string, string>  $paths
     */
    public static function render(ArtifactBag $bag, array $paths): string
    {
        $lines = ['', 'Tetryon browser diagnostics', '', 'Current URL:', "  {$bag->url}", '', 'Artifacts:'];
        foreach ($paths as $label => $path) {
            $lines[] = "  {$label}: {$path}";
        }

        return implode("\n", $lines);
    }
}
