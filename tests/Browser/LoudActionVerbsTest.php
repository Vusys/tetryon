<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\Core\Selector\UndrivableElementException;

/**
 * Action verbs must not silently succeed against the wrong element: click/press
 * prefer the interactive candidate (#72), and the form verbs fail loudly when
 * they resolve a control they can't drive (#77).
 */
final class LoudActionVerbsTest extends StaticSiteTestCase
{
    public function test_press_clicks_the_button_not_a_heading_with_the_same_text(): void
    {
        $this->browser()
            ->visit('/loud.html')
            ->press('Log in')
            ->assertSee('submitted');
    }

    public function test_fill_throws_on_a_contenteditable_element(): void
    {
        $this->expectException(UndrivableElementException::class);
        $this->expectExceptionMessage('contenteditable');

        $this->browser()->visit('/loud.html')->fill('@editor', 'hello');
    }

    public function test_select_throws_on_a_non_select(): void
    {
        $this->expectException(UndrivableElementException::class);
        $this->expectExceptionMessage('is not a <select>');

        $this->browser()->visit('/loud.html')->select('@fakeselect', 'whatever');
    }

    public function test_check_throws_on_a_non_checkbox(): void
    {
        $this->expectException(UndrivableElementException::class);

        $this->browser()->visit('/loud.html')->check('@editor');
    }
}
