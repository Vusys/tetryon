<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use PHPUnit\Framework\Attributes\Depends;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\ReusesBrowser;

/**
 * The ReusesBrowser trait (#78), with storage snapshotting turned on.
 */
final class ReusesBrowserTraitTest extends ReusedBrowserTestCase
{
    use ReusesBrowser;

    protected function snapshotsBrowserStorage(): bool
    {
        return true;
    }

    protected function authenticate(Browser $browser): void
    {
        $this->countAuthentication();
        $browser->visit('/index.html')->evaluate("localStorage.setItem('token', 'abc'); sessionStorage.setItem('tab', 'one')");
    }

    public function test_storage_from_authenticate_is_there(): void
    {
        $browser = $this->browser()->visit('/index.html');

        self::assertSame('abc', $browser->evaluate("localStorage.getItem('token')"));
        self::assertSame('one', $browser->evaluate("sessionStorage.getItem('tab')"));

        $browser->evaluate("localStorage.setItem('token', 'changed'); localStorage.setItem('extra', 'x')");
    }

    #[Depends('test_storage_from_authenticate_is_there')]
    public function test_storage_is_restored_before_the_next_test(): void
    {
        $browser = $this->browser()->visit('/index.html');

        self::assertSame('abc', $browser->evaluate("localStorage.getItem('token')"));
        self::assertNull($browser->evaluate("localStorage.getItem('extra')"));
        self::assertSame(1, self::$authentications[self::class] ?? 0);
    }
}
