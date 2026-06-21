<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\BiDiTrace;
use Vusys\Tetryon\Firefox\Bidi\BiDiTraceEntry;

#[CoversClass(BiDiTrace::class)]
#[CoversClass(BiDiTraceEntry::class)]
final class BiDiTraceTest extends TestCase
{
    public function test_it_records_entries_in_order(): void
    {
        $trace = new BiDiTrace;
        $trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_COMMAND, 'session.new', 1, '{}'));
        $trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_RESULT, 'session.new', 1, '{"sessionId":"x"}'));

        self::assertCount(2, $trace->entries());
        self::assertSame('session.new', $trace->entries()[0]->label);
    }

    public function test_it_drops_the_oldest_entry_past_the_limit(): void
    {
        $trace = new BiDiTrace(limit: 2);
        foreach ([1, 2, 3] as $id) {
            $trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_COMMAND, "m{$id}", $id, ''));
        }

        $entries = $trace->entries();
        self::assertCount(2, $entries);
        self::assertSame('m2', $entries[0]->label);
        self::assertSame('m3', $entries[1]->label);
    }

    public function test_it_rejects_a_zero_limit(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new BiDiTrace(limit: 0);
    }

    public function test_entry_renders_a_readable_line(): void
    {
        $entry = new BiDiTraceEntry(BiDiTraceEntry::KIND_ERROR, 'script.evaluate', 5, 'invalid argument');

        self::assertSame('ERROR #5 script.evaluate  invalid argument', (string) $entry);
    }

    public function test_trace_stringifies_each_entry_on_its_own_line(): void
    {
        $trace = new BiDiTrace;
        $trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_COMMAND, 'a', 1, ''));
        $trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_EVENT, 'b', null, ''));

        self::assertSame("COMMAND #1 a\nEVENT b", (string) $trace);
    }
}
