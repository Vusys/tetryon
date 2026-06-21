<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\Frame;

#[CoversClass(Frame::class)]
final class FrameTest extends TestCase
{
    public function test_it_encodes_a_short_masked_text_frame(): void
    {
        $frame = Frame::encodeMasked(Frame::OP_TEXT, 'hi', "\x00\x00\x00\x00");

        // FIN+text (0x81), mask bit + length 2 (0x82), 4-byte mask, payload xor 0 = "hi".
        self::assertSame("\x81\x82\x00\x00\x00\x00hi", $frame);
    }

    public function test_it_masks_the_payload(): void
    {
        $mask = "\x12\x34\x56\x78";
        $frame = Frame::encodeMasked(Frame::OP_TEXT, 'AB', $mask);

        $masked = substr($frame, 6);
        self::assertSame('AB', $masked ^ $mask);
        self::assertNotSame('AB', $masked);
    }

    public function test_it_uses_a_16_bit_length_for_medium_payloads(): void
    {
        $frame = Frame::encodeMasked(Frame::OP_TEXT, str_repeat('x', 200), "\x00\x00\x00\x00");

        self::assertSame(0x80 | 126, ord($frame[1]));
        $length = unpack('n', substr($frame, 2, 2));
        self::assertIsArray($length);
        self::assertSame(200, $length[1]);
    }

    public function test_it_uses_a_64_bit_length_for_large_payloads(): void
    {
        $frame = Frame::encodeMasked(Frame::OP_TEXT, str_repeat('x', 70_000), "\x00\x00\x00\x00");

        self::assertSame(0x80 | 127, ord($frame[1]));
        $length = unpack('J', substr($frame, 2, 8));
        self::assertIsArray($length);
        self::assertSame(70_000, $length[1]);
    }

    public function test_it_rejects_a_mask_that_is_not_four_bytes(): void
    {
        $this->expectException(InvalidArgumentException::class);

        Frame::encodeMasked(Frame::OP_TEXT, 'x', "\x00\x00");
    }
}
