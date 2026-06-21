<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

use RuntimeException;

/**
 * No element could be resolved for a target. Carries the full list of selector
 * attempts so the failure report can show what was tried (the spec's
 * "Selector attempts" block).
 */
final class ElementNotFoundException extends RuntimeException
{
    /**
     * @param  list<ResolutionAttempt>  $attempts
     */
    public function __construct(
        public readonly string $target,
        public readonly array $attempts,
    ) {
        $lines = array_map(
            static fn (ResolutionAttempt $attempt): string => '  '.$attempt,
            $attempts,
        );

        parent::__construct(
            "Could not find an element for \"{$target}\".\nSelector attempts:\n".implode("\n", $lines),
        );
    }
}
