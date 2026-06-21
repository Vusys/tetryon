<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\TestCase;

/**
 * The recommended base class for browser tests: a normal PHPUnit test case
 * with `$this->browser()` wired in. Use {@see InteractsWithBrowser} directly
 * if you must extend a different base class.
 */
abstract class BrowserTestCase extends TestCase
{
    use InteractsWithBrowser;
}
