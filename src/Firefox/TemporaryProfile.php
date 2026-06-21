<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Vusys\Tetryon\Firefox\Exception\FirefoxException;

/**
 * A throwaway Firefox profile directory. Each browser launch gets its own,
 * giving fresh cookies / local storage / session storage — the default
 * per-test isolation. Deleted on teardown unless explicitly preserved for
 * debugging.
 */
final readonly class TemporaryProfile
{
    private function __construct(public string $path) {}

    public static function create(?string $baseDir = null): self
    {
        $base = rtrim($baseDir ?? sys_get_temp_dir(), '/');
        $path = $base.'/tetryon-ff-'.bin2hex(random_bytes(8));

        if (! @mkdir($path, 0700, true) && ! is_dir($path)) {
            throw new FirefoxException("Could not create a temporary Firefox profile at \"{$path}\".");
        }

        return new self($path);
    }

    public function delete(): void
    {
        if (! is_dir($this->path)) {
            return;
        }

        /** @var iterable<SplFileInfo> $entries */
        $entries = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($entries as $entry) {
            if ($entry->isDir()) {
                @rmdir($entry->getPathname());
            } else {
                @unlink($entry->getPathname());
            }
        }

        @rmdir($this->path);
    }
}
