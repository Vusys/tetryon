<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Exercises the cookie API against real Firefox and the static-site fixture:
 * round-trip, set-before-navigation, delete, and clear.
 */
final class CookieTest extends StaticSiteTestCase
{
    public function test_it_sets_and_reads_a_cookie(): void
    {
        $browser = $this->browser()->visit('/index.html');
        $browser->setCookie('feature_flags', 'beta');

        self::assertSame('beta', $browser->cookie('feature_flags'));
    }

    public function test_unset_cookie_reads_as_null(): void
    {
        $browser = $this->browser()->visit('/index.html');

        self::assertNull($browser->cookie('never_set'));
    }

    public function test_cookie_set_before_navigation_is_carried_by_the_first_request(): void
    {
        $browser = $this->browser();
        $browser->setCookie('locale', 'cy')->visit('/index.html');

        $documentCookie = $browser->evaluate('document.cookie');
        self::assertIsString($documentCookie);
        self::assertStringContainsString('locale=cy', $documentCookie);
    }

    public function test_it_deletes_a_cookie(): void
    {
        $browser = $this->browser()->visit('/index.html');
        $browser->setCookie('temp', '1');
        $browser->deleteCookie('temp');

        self::assertNull($browser->cookie('temp'));
    }

    public function test_it_clears_all_cookies(): void
    {
        $browser = $this->browser()->visit('/index.html');
        $browser->setCookie('a', '1')->setCookie('b', '2');
        $browser->clearCookies();

        self::assertNull($browser->cookie('a'));
        self::assertNull($browser->cookie('b'));
    }

    public function test_it_sets_an_httponly_cookie_invisible_to_document_cookie(): void
    {
        $browser = $this->browser()->visit('/index.html');
        $browser->setCookie('session', 'secret', ['httpOnly' => true]);

        self::assertSame('secret', $browser->cookie('session'));
        $documentCookie = $browser->evaluate('document.cookie');
        self::assertIsString($documentCookie);
        self::assertStringNotContainsString('session=', $documentCookie);
    }
}
