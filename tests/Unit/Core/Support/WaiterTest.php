<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Support\Waiter;

#[CoversClass(Waiter::class)]
final class WaiterTest extends TestCase
{
    public function test_it_returns_true_as_soon_as_the_condition_holds(): void
    {
        $waiter = $this->fakeClockWaiter(timeoutMs: 1000);

        $calls = 0;
        $met = $waiter->until(static function () use (&$calls): bool {
            $calls++;

            return $calls >= 3;
        });

        self::assertTrue($met);
        self::assertSame(3, $calls);
    }

    public function test_it_returns_false_after_the_timeout(): void
    {
        $waiter = $this->fakeClockWaiter(timeoutMs: 250);

        $attempts = 0;
        $met = $waiter->until(static function () use (&$attempts): bool {
            $attempts++;

            return false;
        });

        self::assertFalse($met);
        // 100ms interval over a 250ms budget: checks at 0, 100, 200, then 300 > deadline.
        self::assertSame(4, $attempts);
    }

    public function test_a_condition_true_on_the_first_check_never_sleeps(): void
    {
        $slept = false;
        $waiter = new Waiter(
            timeoutMs: 1000,
            clock: static fn (): float => 0.0,
            sleeper: static function (int $microseconds) use (&$slept): void {
                $slept = true;
            },
        );

        self::assertTrue($waiter->until(static fn (): bool => true));
        self::assertFalse($slept);
    }

    private function fakeClockWaiter(int $timeoutMs): Waiter
    {
        $now = 0.0;

        return new Waiter(
            timeoutMs: $timeoutMs,
            intervalMs: 100,
            clock: static function () use (&$now): float {
                return $now;
            },
            sleeper: static function (int $microseconds) use (&$now): void {
                $now += $microseconds / 1000;
            },
        );
    }
}
