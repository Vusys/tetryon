<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * One record in the BiDi command trace — a command we sent, a result or error
 * we got back, or an event Firefox pushed. The trace is the raw material for
 * failure diagnostics ("what was the browser doing when this test failed?").
 */
final readonly class BiDiTraceEntry implements \Stringable
{
    public const string KIND_COMMAND = 'command';

    public const string KIND_RESULT = 'result';

    public const string KIND_ERROR = 'error';

    public const string KIND_EVENT = 'event';

    public function __construct(
        public string $kind,
        public string $label,
        public ?int $id,
        public string $detail,
    ) {}

    public function __toString(): string
    {
        $id = $this->id !== null ? " #{$this->id}" : '';
        $detail = $this->detail !== '' ? "  {$this->detail}" : '';

        return strtoupper($this->kind)."{$id} {$this->label}{$detail}";
    }
}
