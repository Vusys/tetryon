<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * A bounded, in-memory ring of recent {@see BiDiTraceEntry} records. Old
 * entries fall off once the limit is reached so a long-running driver does not
 * grow without bound; the most recent activity — the part that matters for a
 * failure — is always retained.
 */
final class BiDiTrace implements \Stringable
{
    /** @var list<BiDiTraceEntry> */
    private array $entries = [];

    public function __construct(private readonly int $limit = 200)
    {
        if ($limit < 1) {
            throw new \InvalidArgumentException('Trace limit must be at least 1.');
        }
    }

    public function record(BiDiTraceEntry $entry): void
    {
        $this->entries[] = $entry;
        if (count($this->entries) > $this->limit) {
            array_shift($this->entries);
        }
    }

    /**
     * @return list<BiDiTraceEntry>
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function __toString(): string
    {
        return implode("\n", array_map(
            static fn (BiDiTraceEntry $entry): string => (string) $entry,
            $this->entries,
        ));
    }
}
