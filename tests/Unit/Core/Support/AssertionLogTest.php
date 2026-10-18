<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Support\AssertionLog;

#[CoversClass(AssertionLog::class)]
final class AssertionLogTest extends TestCase
{
    public function test_it_drains_entries_in_call_order(): void
    {
        $log = new AssertionLog;
        $log->record('assertSee("1 item left")');
        $log->record('assertValue(".new-todo", "")');

        self::assertSame([
            'assertSee("1 item left")',
            'assertValue(".new-todo", "")',
        ], $log->drain());
    }

    public function test_drain_empties_the_log(): void
    {
        $log = new AssertionLog;
        $log->record('assertSee("1 item left")');
        $log->drain();

        self::assertSame([], $log->drain());
    }

    public function test_drain_on_an_empty_log_returns_an_empty_list(): void
    {
        self::assertSame([], new AssertionLog()->drain());
    }
}
