<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\Handshake;
use Vusys\Tetryon\Firefox\Exception\BiDiException;

#[CoversClass(Handshake::class)]
final class HandshakeTest extends TestCase
{
    // RFC 6455 §1.3 worked example.
    private const string SAMPLE_KEY = 'dGhlIHNhbXBsZSBub25jZQ==';

    private const string SAMPLE_ACCEPT = 's3pPLMBiTxaQ9kYGzzhZRbK+xOo=';

    public function test_request_contains_the_required_upgrade_headers(): void
    {
        $request = Handshake::request('127.0.0.1', 9222, '/session', self::SAMPLE_KEY);

        self::assertStringStartsWith('GET /session HTTP/1.1', $request);
        self::assertStringContainsString('Host: 127.0.0.1:9222', $request);
        self::assertStringContainsString('Upgrade: websocket', $request);
        self::assertStringContainsString('Sec-WebSocket-Key: '.self::SAMPLE_KEY, $request);
        self::assertStringEndsWith("\r\n\r\n", $request);
    }

    public function test_expected_accept_matches_the_rfc_vector(): void
    {
        self::assertSame(self::SAMPLE_ACCEPT, Handshake::expectedAccept(self::SAMPLE_KEY));
    }

    public function test_verify_accepts_a_valid_upgrade_response(): void
    {
        $response = "HTTP/1.1 101 Switching Protocols\r\n"
            ."Upgrade: websocket\r\nConnection: Upgrade\r\n"
            .'Sec-WebSocket-Accept: '.self::SAMPLE_ACCEPT."\r\n\r\n";

        Handshake::verify($response, self::SAMPLE_KEY);
        $this->expectNotToPerformAssertions();
    }

    public function test_verify_rejects_a_non_101_status(): void
    {
        $this->expectException(BiDiException::class);
        $this->expectExceptionMessage('expected HTTP 101');

        Handshake::verify("HTTP/1.1 200 OK\r\n\r\n", self::SAMPLE_KEY);
    }

    public function test_verify_rejects_a_mismatched_accept(): void
    {
        $response = "HTTP/1.1 101 Switching Protocols\r\nSec-WebSocket-Accept: wrong\r\n\r\n";

        $this->expectException(BiDiException::class);
        $this->expectExceptionMessage('Sec-WebSocket-Accept');

        Handshake::verify($response, self::SAMPLE_KEY);
    }
}
