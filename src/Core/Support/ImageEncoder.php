<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

use GdImage;
use Throwable;

/**
 * Encodes a screenshot as WebP to keep report assets small, using PHP's
 * bundled `ext-gd` — no external binary. Soft-optional: a missing GD, missing
 * WebP support, or any failure along the way falls back to the original PNG
 * bytes untouched. A report must never be why a test fails over an image
 * format.
 */
final class ImageEncoder
{
    public static function encode(string $pngBytes): EncodedImage
    {
        if (! extension_loaded('gd') || ! function_exists('imagewebp')) {
            return new EncodedImage($pngBytes, 'png');
        }

        try {
            $image = @imagecreatefromstring($pngBytes);
            if (! $image instanceof GdImage) {
                return new EncodedImage($pngBytes, 'png');
            }

            $webp = self::toWebp($image);

            return $webp !== null ? new EncodedImage($webp, 'webp') : new EncodedImage($pngBytes, 'png');
        } catch (Throwable) {
            return new EncodedImage($pngBytes, 'png');
        }
    }

    private static function toWebp(GdImage $image): ?string
    {
        ob_start();
        $ok = imagewebp($image, quality: 82);
        $webp = ob_get_clean();

        return $ok && is_string($webp) && $webp !== '' ? $webp : null;
    }
}
