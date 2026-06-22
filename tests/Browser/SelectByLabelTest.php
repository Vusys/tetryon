<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\Core\Selector\OptionNotFoundException;

/**
 * select() matches an option by its visible label as well as its value (#73),
 * keeps value-based selection via selectByValue(), and fails loudly when no
 * option matches.
 */
final class SelectByLabelTest extends StaticSiteTestCase
{
    public function test_select_matches_by_visible_label(): void
    {
        $this->browser()
            ->visit('/signup.html')
            ->select('Country', 'United Kingdom')
            ->assertValue('Country', 'uk');
    }

    public function test_select_still_matches_by_value(): void
    {
        $this->browser()
            ->visit('/signup.html')
            ->select('Country', 'us')
            ->assertValue('Country', 'us');
    }

    public function test_select_by_value_only(): void
    {
        $this->browser()
            ->visit('/signup.html')
            ->selectByValue('Country', 'uk')
            ->assertValue('Country', 'uk');
    }

    public function test_select_throws_on_an_unknown_option(): void
    {
        $this->expectException(OptionNotFoundException::class);
        $this->expectExceptionMessage('has no <option> with label or value "Atlantis"');

        $this->browser()->visit('/signup.html')->select('Country', 'Atlantis');
    }
}
