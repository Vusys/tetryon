<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Config;

use InvalidArgumentException;

/**
 * Immutable timeout budget (milliseconds) for browser operations.
 *
 * Mirrors the `timeout` block of the published config. Auto-waiting
 * reads these to decide how long an action or assertion may retry
 * before it gives up.
 */
final readonly class Timeouts
{
    public function __construct(
        public int $default = 5000,
        public int $navigation = 15000,
        public int $assertion = 5000,
    ) {
        $this->assertPositive('default', $default);
        $this->assertPositive('navigation', $navigation);
        $this->assertPositive('assertion', $assertion);
    }

    /**
     * @param  array{default?: int, navigation?: int, assertion?: int}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['default'] ?? 5000,
            $config['navigation'] ?? 15000,
            $config['assertion'] ?? 5000,
        );
    }

    private function assertPositive(string $name, int $milliseconds): void
    {
        if ($milliseconds <= 0) {
            throw new InvalidArgumentException(
                "Timeout \"{$name}\" must be a positive number of milliseconds, got {$milliseconds}.",
            );
        }
    }
}
