<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

/**
 * The trait form of {@see ReuseBrowser}: one browser for the whole test class,
 * restored to the snapshot taken after `authenticate()` before every test.
 * Override {@see snapshotsBrowserStorage()} to also snapshot localStorage and
 * sessionStorage.
 */
trait ReusesBrowser
{
    protected function snapshotsBrowserStorage(): bool
    {
        return false;
    }
}
