<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\NaturalLanguage;

use RuntimeException;

/**
 * A step sentence did not match the (deliberately small) grammar.
 */
final class UnknownStepException extends RuntimeException
{
    public static function for(string $step): self
    {
        return new self(
            "Tetryon could not understand the step: \"{$step}\".\n"
            .'See docs/natural-language.md for the supported sentences.',
        );
    }
}
