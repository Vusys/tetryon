<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Exception;

/**
 * A BiDi command returned an error response (`type: "error"`), e.g.
 * `invalid argument` or `no such element`.
 */
final class BiDiCommandException extends BiDiException
{
    public function __construct(
        public readonly string $method,
        public readonly string $errorCode,
        string $bidiMessage,
    ) {
        parent::__construct("BiDi command \"{$method}\" failed: {$errorCode} — {$bidiMessage}");
    }
}
