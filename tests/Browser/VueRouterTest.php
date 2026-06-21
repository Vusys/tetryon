<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Drives a client-side (hash) router in a real Vue SPA: the content and the URL
 * change as you click links, with no full-page navigation.
 */
final class VueRouterTest extends StaticSiteTestCase
{
    public function test_client_side_hash_routing(): void
    {
        $this->browser()
            ->visit('/vue-app/router.html')
            ->assertSee('Welcome home.')
            ->click('About')
            ->assertSee('About page.')
            ->assertDontSee('Welcome home.')
            ->assertUrlIs('/vue-app/router.html#/about')
            ->click('User 42')
            ->assertSee('Profile for user 42.')
            ->assertUrlIs('/vue-app/router.html#/users/42')
            ->back()                       // back through hash history, no reload
            ->assertSee('About page.')
            ->click('Home')
            ->assertSee('Welcome home.');
    }
}
