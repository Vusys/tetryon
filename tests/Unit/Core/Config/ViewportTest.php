<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Config\Viewport;

#[CoversClass(Viewport::class)]
final class ViewportTest extends TestCase
{
    public function test_it_defaults_to_720p(): void
    {
        $viewport = new Viewport;

        self::assertSame(1280, $viewport->width);
        self::assertSame(720, $viewport->height);
    }

    public function test_from_array_overrides_only_supplied_keys(): void
    {
        $viewport = Viewport::fromArray(['width' => 1920]);

        self::assertSame(1920, $viewport->width);
        self::assertSame(720, $viewport->height);
    }

    public function test_it_computes_aspect_ratio(): void
    {
        self::assertEqualsWithDelta(16 / 9, new Viewport(1280, 720)->aspectRatio(), 0.0001);
    }

    public function test_it_takes_a_device_pixel_ratio_defaulting_to_one(): void
    {
        self::assertSame(1.0, new Viewport()->devicePixelRatio);
        self::assertSame(2.0, Viewport::fromArray(['device_pixel_ratio' => 2])->devicePixelRatio);
    }

    public function test_it_rejects_a_device_pixel_ratio_out_of_range(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Viewport "device_pixel_ratio" must be above 0 and at most 4, got 0.');

        new Viewport(devicePixelRatio: 0);
    }

    public function test_it_rejects_non_positive_dimensions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Viewport "height" must be a positive number of pixels, got 0.');

        new Viewport(height: 0);
    }
}
