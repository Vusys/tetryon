<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\Frame;
use Vusys\Tetryon\Firefox\Bidi\WebSocketClient;
use Vusys\Tetryon\Firefox\Exception\BiDiException;

#[CoversClass(WebSocketClient::class)]
final class WebSocketClientTest extends TestCase
{
    private const string ZERO_MASK = "\x00\x00\x00\x00";

    public function test_send_text_writes_a_masked_frame(): void
    {
        [$client, $peer] = $this->connectedPair(static fn (): string => "\x01\x02\x03\x04");

        $client->sendText('hello');

        self::assertSame(
            Frame::encodeMasked(Frame::OP_TEXT, 'hello', "\x01\x02\x03\x04"),
            (string) fread($peer, 4096),
        );
    }

    public function test_receive_message_decodes_a_server_text_frame(): void
    {
        [$client, $peer] = $this->connectedPair();
        fwrite($peer, $this->serverFrame(Frame::OP_TEXT, 'world'));

        self::assertSame('world', $client->receiveMessage());
    }

    public function test_receive_message_reassembles_fragments(): void
    {
        [$client, $peer] = $this->connectedPair();
        fwrite($peer, $this->serverFrame(Frame::OP_TEXT, 'Hel', fin: false));
        fwrite($peer, $this->serverFrame(Frame::OP_CONTINUATION, 'lo', fin: true));

        self::assertSame('Hello', $client->receiveMessage());
    }

    public function test_receive_message_answers_a_ping_with_a_pong(): void
    {
        [$client, $peer] = $this->connectedPair(static fn (): string => self::ZERO_MASK);
        fwrite($peer, $this->serverFrame(Frame::OP_PING, 'beat'));
        fwrite($peer, $this->serverFrame(Frame::OP_TEXT, 'ok'));

        self::assertSame('ok', $client->receiveMessage());
        self::assertSame(
            Frame::encodeMasked(Frame::OP_PONG, 'beat', self::ZERO_MASK),
            (string) fread($peer, 4096),
        );
    }

    public function test_receive_message_rejects_a_masked_server_frame(): void
    {
        [$client, $peer] = $this->connectedPair();
        // Mask bit set on a server frame — a protocol violation.
        fwrite($peer, "\x81\x82\x00\x00\x00\x00hi");

        $this->expectException(BiDiException::class);
        $this->expectExceptionMessage('masked frame');

        $client->receiveMessage();
    }

    /**
     * @param  (callable(): string)|null  $maskProvider
     * @return array{0: WebSocketClient, 1: resource}
     */
    private function connectedPair(?callable $maskProvider = null): array
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        if ($pair === false) {
            self::fail('Could not create a socket pair.');
        }
        [$clientStream, $peer] = $pair;
        stream_set_timeout($clientStream, 2);

        return [new WebSocketClient($clientStream, $maskProvider), $peer];
    }

    private function serverFrame(int $opcode, string $payload, bool $fin = true): string
    {
        $header = chr((($fin ? 0x80 : 0x00) | $opcode) & 0xFF);
        $length = strlen($payload);

        if ($length < 126) {
            $header .= chr($length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(126).pack('n', $length);
        } else {
            $header .= chr(127).pack('J', $length);
        }

        return $header.$payload;
    }
}
