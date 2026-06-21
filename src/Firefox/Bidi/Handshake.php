<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

use Vusys\Tetryon\Firefox\Exception\BiDiException;

/**
 * The RFC 6455 opening handshake, as pure string operations so the upgrade
 * logic is testable without a socket.
 */
final class Handshake
{
    /** RFC 6455 magic GUID concatenated with the client key to derive Accept. */
    private const string ACCEPT_GUID = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public static function generateKey(): string
    {
        return base64_encode(random_bytes(16));
    }

    public static function request(string $host, int $port, string $path, string $key): string
    {
        return "GET {$path} HTTP/1.1\r\n"
            ."Host: {$host}:{$port}\r\n"
            ."Upgrade: websocket\r\n"
            ."Connection: Upgrade\r\n"
            ."Sec-WebSocket-Key: {$key}\r\n"
            ."Sec-WebSocket-Version: 13\r\n\r\n";
    }

    public static function expectedAccept(string $key): string
    {
        return base64_encode(sha1($key.self::ACCEPT_GUID, true));
    }

    /**
     * @throws BiDiException when the response is not a valid 101 upgrade for the key
     */
    public static function verify(string $responseHead, string $key): void
    {
        $statusLine = self::firstLine($responseHead);
        if (preg_match('#^HTTP/\d\.\d 101#', $statusLine) !== 1) {
            throw new BiDiException(
                "WebSocket upgrade rejected by Firefox (expected HTTP 101): \"{$statusLine}\".",
            );
        }

        if (stripos($responseHead, self::expectedAccept($key)) === false) {
            throw new BiDiException('WebSocket upgrade failed: Sec-WebSocket-Accept did not match the sent key.');
        }
    }

    private static function firstLine(string $text): string
    {
        $end = strpos($text, "\r\n");

        return $end === false ? $text : substr($text, 0, $end);
    }
}
