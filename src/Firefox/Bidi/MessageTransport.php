<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * The byte/message transport {@see BiDiConnection} talks over — a text-message
 * WebSocket in production ({@see WebSocketClient}), or a fake in tests. Keeping
 * this seam lets the BiDi protocol logic be exercised without a real browser.
 */
interface MessageTransport
{
    public function sendText(string $payload): void;

    public function receiveMessage(): string;

    public function poll(float $seconds): bool;

    public function close(): void;
}
