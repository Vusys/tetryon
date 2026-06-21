<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

use Closure;

/**
 * Polls a condition until it holds or a timeout elapses. The clock and sleep
 * are injectable so the retry behaviour is deterministically unit-testable
 * without real time passing.
 */
final readonly class Waiter
{
    /** @var Closure(): float  current time in milliseconds */
    private Closure $clock;

    /** @var Closure(int): void  sleep for N microseconds */
    private Closure $sleeper;

    /**
     * @param  (callable(): float)|null  $clock
     * @param  (callable(int): void)|null  $sleeper
     */
    public function __construct(
        private int $timeoutMs,
        private int $intervalMs = 100,
        ?callable $clock = null,
        ?callable $sleeper = null,
    ) {
        $this->clock = $clock !== null
            ? Closure::fromCallable($clock)
            : static fn (): float => microtime(true) * 1000;
        $this->sleeper = $sleeper !== null
            ? Closure::fromCallable($sleeper)
            : static function (int $microseconds): void {
                usleep($microseconds);
            };
    }

    /**
     * @param  callable(): bool  $condition
     * @return bool whether the condition became true within the timeout
     */
    public function until(callable $condition): bool
    {
        $deadline = ($this->clock)() + $this->timeoutMs;

        while (true) {
            if ($condition()) {
                return true;
            }
            if (($this->clock)() >= $deadline) {
                return false;
            }
            ($this->sleeper)($this->intervalMs * 1000);
        }
    }
}
