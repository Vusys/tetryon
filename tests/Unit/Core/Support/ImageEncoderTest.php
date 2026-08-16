<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Support\ImageEncoder;

#[CoversClass(ImageEncoder::class)]
final class ImageEncoderTest extends TestCase
{
    public function test_it_encodes_a_valid_png_as_webp(): void
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            self::markTestSkipped('ext-gd with WebP support is not available.');
        }

        $encoded = ImageEncoder::encode($this->onePixelPng());

        self::assertSame('webp', $encoded->extension);
        self::assertNotSame('', $encoded->bytes);
        self::assertNotSame($this->onePixelPng(), $encoded->bytes);
    }

    public function test_it_falls_back_to_png_for_corrupt_bytes(): void
    {
        $corrupt = 'not a real image';

        $encoded = ImageEncoder::encode($corrupt);

        self::assertSame('png', $encoded->extension);
        self::assertSame($corrupt, $encoded->bytes);
    }

    public function test_it_falls_back_to_png_for_empty_bytes(): void
    {
        $encoded = ImageEncoder::encode('');

        self::assertSame('png', $encoded->extension);
        self::assertSame('', $encoded->bytes);
    }

    private function onePixelPng(): string
    {
        $image = imagecreatetruecolor(1, 1);
        self::assertNotFalse($image);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 30, 90, 200));

        ob_start();
        imagepng($image);
        $png = ob_get_clean();

        self::assertIsString($png);

        return $png;
    }
}
