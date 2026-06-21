<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LogLevel;
use Vusys\Tetryon\Core\Support\StreamLogger;

#[CoversClass(StreamLogger::class)]
final class StreamLoggerTest extends TestCase
{
    public function test_it_interpolates_context_placeholders(): void
    {
        self::assertSame(
            "[debug] BiDi -> session.new #1\n",
            $this->capture(LogLevel::DEBUG, static function (StreamLogger $logger): void {
                $logger->debug('BiDi -> {method} #{id}', ['method' => 'session.new', 'id' => 1]);
            }),
        );
    }

    public function test_it_filters_below_the_minimum_level(): void
    {
        $output = $this->capture(LogLevel::WARNING, static function (StreamLogger $logger): void {
            $logger->debug('quiet');
            $logger->error('loud');
        });

        self::assertStringNotContainsString('quiet', $output);
        self::assertStringContainsString('[error] loud', $output);
    }

    /**
     * @param  callable(StreamLogger): void  $use
     */
    private function capture(string $minLevel, callable $use): string
    {
        $stream = fopen('php://memory', 'rw+');
        self::assertIsResource($stream);

        $use(new StreamLogger($stream, $minLevel));

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return is_string($output) ? $output : '';
    }
}
