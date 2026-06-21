<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Console;

/**
 * The outcome of one doctor check: a label, pass/fail, an optional detail
 * shown alongside, and — when it failed — a fix hint.
 */
final readonly class Check
{
    public function __construct(
        public string $label,
        public bool $passed,
        public string $detail = '',
        public ?string $fix = null,
    ) {}

    public static function pass(string $label, string $detail = ''): self
    {
        return new self($label, true, $detail);
    }

    public static function fail(string $label, string $detail, string $fix): self
    {
        return new self($label, false, $detail, $fix);
    }
}
