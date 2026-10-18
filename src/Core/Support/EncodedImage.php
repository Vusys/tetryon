<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

/**
 * The result of {@see ImageEncoder::encode()} — the bytes to write and the
 * extension they were encoded as, so a caller can name the file correctly
 * without re-deriving what format it got.
 */
final readonly class EncodedImage
{
    /**
     * @param  'webp'|'png'  $extension
     */
    public function __construct(
        public string $bytes,
        public string $extension,
    ) {}
}
