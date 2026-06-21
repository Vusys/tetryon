<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

use InvalidArgumentException;

/**
 * RFC 6455 frame codec, limited to what a BiDi client needs: encode a single
 * final masked client frame, and parse a frame header off a byte string.
 *
 * Client→server frames must be masked; server→client frames must not be.
 */
final class Frame
{
    public const int OP_CONTINUATION = 0x0;

    public const int OP_TEXT = 0x1;

    public const int OP_BINARY = 0x2;

    public const int OP_CLOSE = 0x8;

    public const int OP_PING = 0x9;

    public const int OP_PONG = 0xA;

    /**
     * Encode one final (FIN=1) masked client frame.
     */
    public static function encodeMasked(int $opcode, string $payload, string $mask): string
    {
        if (strlen($mask) !== 4) {
            throw new InvalidArgumentException('A WebSocket mask must be exactly 4 bytes.');
        }

        $length = strlen($payload);
        $header = chr(0x80 | ($opcode & 0x0F));

        if ($length < 126) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 0xFFFF) {
            $header .= chr(0x80 | 126).pack('n', $length);
        } else {
            $header .= chr(0x80 | 127).pack('J', $length);
        }

        return $header.$mask.($payload ^ self::repeatMask($mask, $length));
    }

    private static function repeatMask(string $mask, int $length): string
    {
        if ($length === 0) {
            return '';
        }

        return substr(str_repeat($mask, intdiv($length, 4) + 1), 0, $length);
    }
}
