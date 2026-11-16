<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use PHPUnit\Framework\Attributes\Depends;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\ReuseBrowser;

/**
 * #[ReuseBrowser] (#78): one browser for the class, authenticated once, and
 * restored to the post-authenticate cookies before every test.
 */
#[ReuseBrowser]
final class ReuseBrowserTest extends ReusedBrowserTestCase
{
    protected function authenticate(Browser $browser): void
    {
        $this->countAuthentication();
        $browser->visit('/index.html')->setCookie('session', 'signed-in');
    }

    public function test_a_test_starts_from_the_authenticated_snapshot(): void
    {
        $this->noteDriver();
        $browser = $this->browser();

        self::assertSame('about:blank', $this->driver()->currentUrl());
        self::assertSame('signed-in', $browser->visit('/index.html')->cookie('session'));

        $browser->setCookie('session', 'tampered')->setCookie('leftover', '1');
    }

    #[Depends('test_a_test_starts_from_the_authenticated_snapshot')]
    public function test_the_next_test_gets_the_snapshot_back_not_the_last_tests_state(): void
    {
        $this->noteDriver();
        $browser = $this->browser()->visit('/index.html');

        self::assertSame('signed-in', $browser->cookie('session'));
        self::assertNull($browser->cookie('leftover'));
        // index.html logs once per load: only this test's visit, not the earlier ones
        self::assertCount(1, $this->driver()->consoleMessages());
    }

    #[Depends('test_the_next_test_gets_the_snapshot_back_not_the_last_tests_state')]
    public function test_the_browser_launched_and_authenticated_once(): void
    {
        $this->noteDriver();
        $this->browser();

        self::assertSame(1, self::$authentications[self::class] ?? 0);
        self::assertCount(1, array_unique(self::$drivers[self::class]));
    }

    #[Depends('test_the_browser_launched_and_authenticated_once')]
    public function test_a_dead_browser_is_relaunched_and_authenticated_again(): void
    {
        $this->driver()->stop();
        $this->tearDownBrowserForTest();

        $browser = $this->browser()->visit('/index.html');

        self::assertSame('signed-in', $browser->cookie('session'));
        self::assertSame(2, self::$authentications[self::class] ?? 0);
    }

    /** Simulate the next test's start: drop this test's handle on the browser. */
    private function tearDownBrowserForTest(): void
    {
        $this->stopTetryonBrowser();
    }
}
