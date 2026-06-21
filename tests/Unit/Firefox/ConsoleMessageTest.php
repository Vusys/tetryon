<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\ConsoleMessage;

#[CoversClass(ConsoleMessage::class)]
final class ConsoleMessageTest extends TestCase
{
    public function test_it_prefers_the_rendered_text(): void
    {
        $message = ConsoleMessage::fromLogEntry([
            'level' => 'warn',
            'method' => 'warn',
            'text' => 'Something happened',
        ]);

        self::assertSame('warn', $message->level);
        self::assertSame('warn', $message->source);
        self::assertSame('Something happened', $message->text);
    }

    public function test_it_falls_back_to_joining_scalar_args(): void
    {
        $message = ConsoleMessage::fromLogEntry([
            'level' => 'info',
            'args' => [
                ['type' => 'string', 'value' => 'count is'],
                ['type' => 'number', 'value' => 3],
            ],
        ]);

        self::assertSame('count is 3', $message->text);
    }

    public function test_it_defaults_sanely_for_an_empty_entry(): void
    {
        $message = ConsoleMessage::fromLogEntry(null);

        self::assertSame('info', $message->level);
        self::assertSame('console', $message->source);
        self::assertSame('', $message->text);
    }
}
