<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\PHPUnit\Browser;

/**
 * Exercises the navigation API + text/URL/title assertions and the tap()
 * grouping helper against real Firefox and the static-site fixture.
 */
final class NavigationTest extends StaticSiteTestCase
{
    public function test_guest_can_read_pages(): void
    {
        $this->browser()
            ->visit('/index.html')
            ->assertTitleIs('Tetryon Spike')
            ->assertSee('Hello, Tetryon')
            ->assertDontSee('Goodbye, cruel world')
            ->assertPathIs('/index.html');
    }

    public function test_history_navigation(): void
    {
        $browser = $this->browser()->visit('/index.html');

        $browser->visit('/page-two.html')->assertTitleIs('Page Two');
        $browser->back()->assertTitleIs('Tetryon Spike');
        $browser->forward()->assertTitleIs('Page Two');
    }

    public function test_tap_runs_a_grouped_assertion_block(): void
    {
        $ran = 0;

        $this->browser()
            ->visit('/index.html')
            ->tap(function (Browser $browser) use (&$ran): void {
                $ran++;
                $browser->assertTitleIs('Tetryon Spike')->assertSee('Hello, Tetryon');
            })
            ->assertPathIs('/index.html');

        self::assertSame(1, $ran);
    }
}
