<?php

declare(strict_types=1);

/*
 * Background encoder for FrameSpool: reads one PNG path per line on stdin and
 * writes a WebP beside each. Niced, so it never competes with the browser it
 * is recording. Loads only the two classes it needs — no autoloader.
 */

require __DIR__.'/../../Core/Support/EncodedImage.php';
require __DIR__.'/../../Core/Support/ImageEncoder.php';

use Vusys\Tetryon\Core\Support\ImageEncoder;

if (function_exists('proc_nice')) {
    @proc_nice(10);
}

while (($line = fgets(STDIN)) !== false) {
    $png = trim($line);
    $bytes = @file_get_contents($png);
    if ($bytes === false) {
        continue;
    }

    $encoded = ImageEncoder::encode($bytes);
    if ($encoded->extension !== 'webp') {
        continue;
    }

    // Written aside then renamed, so a half-written file is never read.
    $target = substr($png, 0, -4).'.webp';
    if (@file_put_contents($target.'.tmp', $encoded->bytes) !== false) {
        @rename($target.'.tmp', $target);
    }
}
