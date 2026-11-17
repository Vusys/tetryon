<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\Core\Selector\ElementNotFoundException;

/**
 * press() presses buttons only (#199): a field labelled with the button's
 * text, or a heading, can't take the press.
 */
final class PressTest extends StaticSiteTestCase
{
    public function test_a_field_sharing_the_buttons_label_does_not_take_the_press(): void
    {
        $this->browser()->visit('/press.html')
            ->fill('Search', 'support')
            ->press('Search')
            ->assertSee('searched for support');
    }

    public function test_it_presses_a_button_by_its_accessible_name(): void
    {
        $this->browser()->visit('/press.html')->press('Close dialog')->assertSee('closed');
    }

    public function test_it_presses_a_role_button(): void
    {
        $this->browser()->visit('/press.html')->press('Custom')->assertSee('custom pressed');
    }

    public function test_text_that_is_not_a_button_is_not_found(): void
    {
        $this->expectException(ElementNotFoundException::class);

        $this->browser()->visit('/press.html')->press('Archive');
    }
}
