<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\NaturalLanguage;

/**
 * A parsed natural-language step: the fluent action it maps to and the
 * arguments pulled from the sentence. The mapping to a real browser call lives
 * in the PHPUnit layer; this stays a pure value object.
 */
final readonly class Step
{
    /**
     * @param  list<string>  $arguments
     */
    public function __construct(
        public string $action,
        public array $arguments,
    ) {}
}
