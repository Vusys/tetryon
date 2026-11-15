<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Report;

use Vusys\Tetryon\Core\Support\EncodedImage;
use Vusys\Tetryon\Core\Support\ImageEncoder;

/**
 * Where a recording's frames wait to be encoded. Each distinct screenshot is
 * written once as a PNG and handed to a background worker that encodes it to
 * WebP on another core, so the test keeps going instead of spending ~200ms
 * per frame encoding — time in which the page it is recording carries on
 * without it. A frame is referred to by its PNG's path, which keeps a whole
 * suite's recordings out of memory until the report is written.
 *
 * Soft-optional like {@see ImageEncoder}: if the worker can't start or falls
 * behind, the report encodes what is missing itself.
 */
final class FrameSpool
{
    private static ?self $shared = null;

    /** @var resource|null */
    private $worker;

    /** @var resource|null */
    private $queue;

    private function __construct(private readonly string $directory) {}

    public static function shared(): self
    {
        return self::$shared ??= new self(sys_get_temp_dir().'/tetryon-frames-'.getmypid());
    }

    /** Store a screenshot, queue it for encoding, and return its path. */
    public function put(string $png): string
    {
        if (! is_dir($this->directory)) {
            @mkdir($this->directory, 0777, true);
        }

        $path = $this->directory.'/'.md5($png).'.png';
        if (! is_file($path) && @file_put_contents($path, $png) !== false) {
            $this->queue($path);
        }

        return $path;
    }

    /** Whether $frame is one of this spool's paths rather than image bytes. */
    public function owns(string $frame): bool
    {
        return str_starts_with($frame, $this->directory.'/');
    }

    /** The frame as report-ready bytes, once the worker has finished. */
    public function encoded(string $path): EncodedImage
    {
        $this->drain();

        $webp = substr($path, 0, -4).'.webp';
        if (is_file($webp)) {
            return new EncodedImage((string) file_get_contents($webp), 'webp');
        }

        return ImageEncoder::encode((string) @file_get_contents($path));
    }

    /** Let the worker finish what it has queued, then stop it. */
    public function drain(): void
    {
        if (is_resource($this->queue)) {
            fclose($this->queue);
        }

        if (is_resource($this->worker)) {
            proc_close($this->worker);
        }

        $this->queue = null;
        $this->worker = null;
    }

    public function __destruct()
    {
        $this->drain();

        foreach (glob($this->directory.'/*') ?: [] as $file) {
            @unlink($file);
        }

        @rmdir($this->directory);
    }

    private function queue(string $path): void
    {
        if (! is_resource($this->queue)) {
            $pipes = [];
            $worker = @proc_open(
                [PHP_BINARY, __DIR__.'/encode-worker.php'],
                [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']],
                $pipes,
            );
            if (! is_resource($worker)) {
                return;
            }

            $this->worker = $worker;
            $this->queue = $pipes[0];
        }

        @fwrite($this->queue, $path."\n");
    }
}
