<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Config;

use InvalidArgumentException;

/**
 * Immutable browser viewport size in CSS pixels.
 */
final readonly class Viewport
{
    public function __construct(
        public int $width = 1280,
        public int $height = 720,
    ) {
        $this->assertPositive('width', $width);
        $this->assertPositive('height', $height);
    }

    /**
     * @param  array{width?: int, height?: int}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            $config['width'] ?? 1280,
            $config['height'] ?? 720,
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
