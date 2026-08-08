<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * check()/uncheck() must drive a visually-hidden real checkbox that is only
 * associated with its label by adjacency — the custom checkbox/toggle pattern
 * that TodoMVC and most of the real web use (#138, #139). These verbs use a
 * synthetic click, so opacity:0 / pointer-events:none must not block them.
 */
final class CustomCheckboxTest extends StaticSiteTestCase
{
    public function test_checks_an_opacity_hidden_checkbox_by_its_sibling_label(): void
    {
        $this->browser()
            ->visit('/custom-checkbox.html')
            ->check('Subscribe')
            ->assertChecked('.toggle')
            ->assertSee('toggle=true')
            ->uncheck('Subscribe')
            ->assertNotChecked('.toggle')
            ->assertSee('toggle=false');
    }

    public function test_checks_a_pointer_events_none_checkbox(): void
    {
        $this->browser()
            ->visit('/custom-checkbox.html')
            ->check('Notifications')
            ->assertChecked('.pe-toggle')
            ->assertSee('pe-toggle=true');
    }
}
