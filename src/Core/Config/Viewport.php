<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Config;

use InvalidArgumentException;

/**
 * Immutable browser viewport size in CSS pixels, and how many device pixels
 * each CSS pixel is drawn with — 2 gives screenshots (and so recordings) the
 * sharpness of a high-density screen, at four times the pixels.
 */
final readonly class Viewport
{
    public function __construct(
        public int $width = 1280,
        public int $height = 720,
        public float $devicePixelRatio = 1.0,
    ) {
        $this->assertPositive('width', $width);
        $this->assertPositive('height', $height);

        if ($devicePixelRatio <= 0 || $devicePixelRatio > 4) {
            throw new InvalidArgumentException(
                "Viewport \"device_pixel_ratio\" must be above 0 and at most 4, got {$devicePixelRatio}.",
            );
        }
    }

    /**
     * @param  array{width?: int, height?: int, device_pixel_ratio?: float|int}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['width'] ?? 1280,
            $config['height'] ?? 720,
            (float) ($config['device_pixel_ratio'] ?? 1.0),
        );
    }

    public function aspectRatio(): float
    {
        return $this->width / $this->height;
    }

    private function assertPositive(string $name, int $pixels): void
    {
        if ($pixels <= 0) {
            throw new InvalidArgumentException(
                "Viewport \"{$name}\" must be a positive number of pixels, got {$pixels}.",
            );
        }
    }
}
