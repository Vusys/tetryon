<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Exercises the element-state assertions and their query counterparts —
 * checked/selected (#75) and enabled/disabled/attribute (#76) — against real
 * Firefox and the state fixture.
 */
final class StateAssertionsTest extends StaticSiteTestCase
{
    public function test_checkbox_state(): void
    {
        $browser = $this->browser()->visit('/state.html');

        $browser->assertChecked('Subscribe')->assertNotChecked('Accept terms');

        self::assertTrue($browser->isChecked('Subscribe'));
        self::assertFalse($browser->isChecked('Accept terms'));
    }

    public function test_radio_state(): void
    {
        $this->browser()
            ->visit('/state.html')
            ->assertRadioSelected('plan', 'pro')
            ->assertRadioNotSelected('plan', 'free');
    }

    public function test_select_state(): void
    {
        $browser = $this->browser()->visit('/state.html');

        $browser->assertSelected('Country', 'us')->assertNotSelected('Country', 'uk');

        self::assertSame('us', $browser->selected('Country'));
    }

    public function test_enabled_and_disabled(): void
    {
        $this->browser()
            ->visit('/state.html')
            ->assertDisabled('#save')
            ->assertEnabled('#cancel');
    }

    public function test_attribute_query_and_assertions(): void
    {
        $browser = $this->browser()->visit('/state.html');

        $browser
            ->assertAttribute('#home', 'href', '/dashboard')
            ->assertAttributeContains('#home', 'class', 'is-active');

        self::assertSame('active', $browser->attribute('#home', 'data-state'));
        self::assertNull($browser->attribute('#home', 'data-missing'));
    }
}
