<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * currentPath() — and therefore assertPathIs()/waitForPath() — must include the
 * URL fragment so hash routing is assertable (#140). A fragment-free URL is
 * unchanged.
 */
final class HashRouteTest extends StaticSiteTestCase
{
    public function test_current_path_and_waits_track_the_hash_fragment(): void
    {
        $this->browser()
            ->visit('/hash-route.html')
            ->assertPathIs('/hash-route.html')
            ->click('Active')
            ->assertPathIs('/hash-route.html#/active')
            ->assertSee('route:#/active')
            ->click('Completed')
            ->waitForPath('/hash-route.html#/completed')
            ->assertSee('route:#/completed');
    }
}
