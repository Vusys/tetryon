<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\Core\Selector\UndrivableElementException;

/**
 * fill() on a date, time or colour input. Firefox draws these as pickers that
 * drop typed text, so typing used to leave them silently empty.
 */
final class PickerInputTest extends StaticSiteTestCase
{
    public function test_fill_sets_a_date_and_fires_change(): void
    {
        $this->browser()->visit('/pickers.html')
            ->fill('Start date', '2025-03-01')
            ->assertValue('Start date', '2025-03-01')
            ->assertSee('start changed to 2025-03-01');
    }

    public function test_fill_sets_a_time_and_a_colour(): void
    {
        $this->browser()->visit('/pickers.html')
            ->fill('Alarm', '14:30')
            ->assertValue('Alarm', '14:30')
            ->fill('Accent', '#ff8800')
            ->assertValue('Accent', '#ff8800');
    }

    public function test_a_value_the_input_rejects_fails_loudly(): void
    {
        $this->expectException(UndrivableElementException::class);
        $this->expectExceptionMessage('fill("Start date", "01/03/2025") was rejected by an <input type="date">');

        $this->browser()->visit('/pickers.html')->fill('Start date', '01/03/2025');
    }
}
