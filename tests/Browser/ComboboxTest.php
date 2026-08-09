<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * chooseFromDropdown() drives a custom (non-native) WAI-ARIA combobox — a
 * role="combobox" input revealing a role="listbox" of role="option"s — which
 * select() (native <select> only) can't (#81).
 */
final class ComboboxTest extends StaticSiteTestCase
{
    public function test_chooses_an_option_from_a_custom_dropdown(): void
    {
        $this->browser()
            ->visit('/combobox.html')
            ->chooseFromDropdown('Fruit', 'Banana')
            ->assertSee('chosen:Banana')
            ->assertValue('#fruit', 'Banana');
    }

    public function test_type_to_filter_then_choose(): void
    {
        $this->browser()
            ->visit('/combobox.html')
            ->fill('Fruit', 'Cher')
            ->chooseFromDropdown('Fruit', 'Cherry')
            ->assertSee('chosen:Cherry');
    }

    public function test_select_still_fails_loudly_on_a_non_native_dropdown(): void
    {
        $this->expectExceptionMessage('is not a <select>');

        $this->browser()
            ->visit('/combobox.html')
            ->select('Fruit', 'Banana');
    }
}
