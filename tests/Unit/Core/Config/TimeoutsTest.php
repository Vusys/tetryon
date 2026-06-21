<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Config\Timeouts;

#[CoversClass(Timeouts::class)]
final class TimeoutsTest extends TestCase
{
    public function test_it_exposes_default_budgets(): void
    {
        $timeouts = new Timeouts;

        self::assertSame(5000, $timeouts->default);
        self::assertSame(15000, $timeouts->navigation);
        self::assertSame(5000, $timeouts->assertion);
    }

    public function test_from_array_overrides_only_supplied_keys(): void
    {
        $timeouts = Timeouts::fromArray(['navigation' => 30000]);

        self::assertSame(5000, $timeouts->default);
        self::assertSame(30000, $timeouts->navigation);
        self::assertSame(5000, $timeouts->assertion);
    }

    public function test_it_rejects_non_positive_values(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Timeout "default" must be a positive number of milliseconds, got 0.');

        new Timeouts(default: 0);
    }
}
