<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\PHPUnit\Browser;

/**
 * Scoped groups: within() limits both element resolution and text assertions to
 * a container, so identical elements in sibling cards are disambiguated.
 */
final class WithinTest extends StaticSiteTestCase
{
    public function test_within_scopes_text_assertions(): void
    {
        $this->browser()
            ->visit('/cards.html')
            ->within('@card-ada', function (Browser $browser): void {
                $browser->assertSee('Ada Lovelace')->assertDontSee('Alan Turing');
            })
            ->within('@card-alan', function (Browser $browser): void {
                $browser->assertSee('Alan Turing')->assertDontSee('Ada Lovelace');
            });
    }

    public function test_within_disambiguates_an_action(): void
    {
        $this->browser()
            ->visit('/cards.html')
            ->within('@card-alan', function (Browser $browser): void {
                $browser->click('Edit');   // both cards have an "Edit" button
            })
            ->assertSee('editing alan');
    }
}
