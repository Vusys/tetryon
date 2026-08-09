<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * assertFocused() / assertNotFocused() track document.activeElement, which is
 * set on focus even in headless (where the window itself is never focused) (#141).
 */
final class FocusTest extends StaticSiteTestCase
{
    public function test_asserts_which_element_is_focused(): void
    {
        $this->browser()
            ->visit('/form.html')
            ->assertNotFocused('@name')
            ->click('@name')
            ->assertFocused('@name')
            ->assertNotFocused('@save');
    }
}
