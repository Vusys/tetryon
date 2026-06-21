<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

use Psr\Log\AbstractLogger;
use Psr\Log\LogLevel;
use Stringable;

/**
 * A minimal PSR-3 logger that writes interpolated lines to a stream at or above
 * a minimum level. Drives Tetryon's verbose output (e.g. the BiDi command log)
 * without pulling in a logging framework.
 */
final class StreamLogger extends AbstractLogger
{
    /** Severity rank: lower is more severe. */
    private const array RANK = [
        LogLevel::EMERGENCY => 0,
        LogLevel::ALERT => 1,
        LogLevel::CRITICAL => 2,
        LogLevel::ERROR => 3,
        LogLevel::WARNING => 4,
        LogLevel::NOTICE => 5,
        LogLevel::INFO => 6,
        LogLevel::DEBUG => 7,
    ];

    private readonly int $threshold;

    /**
     * @param  resource  $stream
     */
    public function __construct(private $stream, string $minLevel = LogLevel::DEBUG)
    {
        $this->threshold = self::RANK[$minLevel] ?? 7;
    }

    /**
     * @param  array<array-key, mixed>  $context
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
        $levelName = match (true) {
            is_string($level) => $level,
            is_scalar($level) || $level instanceof Stringable => (string) $level,
            default => 'log',
        };

        if ((self::RANK[$levelName] ?? 7) > $this->threshold) {
            return;
        }

        fwrite($this->stream, "[{$levelName}] ".$this->interpolate((string) $message, $context)."\n");
    }

    /**
     * @param  array<array-key, mixed>  $context
     */
    private function interpolate(string $message, array $context): string
    {
        $replacements = [];
        foreach ($context as $key => $value) {
            if (is_scalar($value) || $value instanceof Stringable) {
                $replacements['{'.$key.'}'] = (string) $value;
            }
        }

        return strtr($message, $replacements);
    }
}
