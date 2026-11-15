<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit\Report;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\PHPUnit\Report\FrameSpool;

#[CoversClass(FrameSpool::class)]
final class FrameSpoolTest extends TestCase
{
    public function test_a_frame_is_stored_once_and_encoded_in_the_background(): void
    {
        if (! function_exists('imagewebp')) {
            self::markTestSkipped('ext-gd without WebP: frames stay PNG.');
        }

        $spool = FrameSpool::shared();
        $png = $this->png(4, 3);

        $path = $spool->put($png);

        self::assertSame($path, $spool->put($png), 'The same picture is one frame.');
        self::assertTrue($spool->owns($path));
        self::assertFalse($spool->owns($png), 'Image bytes are not a spooled frame.');
        self::assertFileExists($path);

        $encoded = $spool->encoded($path);

        self::assertSame('webp', $encoded->extension);
        self::assertFileExists(substr($path, 0, -4).'.webp', 'The worker, not the caller, encoded it.');
    }

    public function test_a_frame_the_worker_never_reached_is_encoded_on_demand(): void
    {
        $spool = FrameSpool::shared();
        $path = $spool->put($this->png(5, 2));
        $spool->drain();
        @unlink(substr($path, 0, -4).'.webp');

        $encoded = $spool->encoded($path);

        self::assertContains($encoded->extension, ['webp', 'png']);
        self::assertNotSame('', $encoded->bytes);
    }

    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    private function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
