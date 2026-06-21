<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Support\Firefox;

use RuntimeException;
use Vusys\Tetryon\Firefox\Bidi\MessageTransport;

/**
 * An in-memory {@see MessageTransport} for exercising the BiDi protocol logic
 * without a browser: queue the messages Firefox would send, inspect what was
 * sent back.
 */
final class FakeTransport implements MessageTransport
{
    /** @var list<string> raw payloads passed to sendText() */
    public array $sent = [];

    /** @var list<string> queued raw incoming messages */
    private array $incoming = [];

    /**
     * @param  array<string, mixed>  $message
     */
    public function queue(array $message): void
    {
        $this->incoming[] = json_encode($message, JSON_THROW_ON_ERROR);
    }

    public function queueRaw(string $raw): void
    {
        $this->incoming[] = $raw;
    }

    public function sendText(string $payload): void
    {
        $this->sent[] = $payload;
    }

    public function receiveMessage(): string
    {
        if ($this->incoming === []) {
            throw new RuntimeException('FakeTransport has no more queued messages.');
        }

        return array_shift($this->incoming);
    }

    public function poll(float $seconds): bool
    {
        return $this->incoming !== [];
    }

    public function close(): void {}
}
