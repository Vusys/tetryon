<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * The outcome of trying one {@see Locator} while resolving a target — kept so a
 * failure can show the user exactly what was tried and what matched.
 */
final readonly class ResolutionAttempt implements \Stringable
{
    public function __construct(
        public string $description,
        public int $matchCount,
    ) {}

    public function __toString(): string
    {
        $matches = $this->matchCount === 0
            ? 'no matches'
            : $this->matchCount.' match'.($this->matchCount === 1 ? '' : 'es');

        return "{$this->description}: {$matches}";
    }
}
