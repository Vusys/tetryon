<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Covers the public scroll verbs against real Firefox (issue #117): reaching a
 * lazily-revealed row far down the page, a row inside a scrollable pane that no
 * page-level scroll can reach, and the page ends.
 */
final class ScrollTest extends StaticSiteTestCase
{
    public function test_scroll_to_reaches_a_row_below_the_fold(): void
    {
        $this->browser()
            ->visit('/scroll.html')
            ->assertDontSee('lazy-row')
            ->scrollTo('Lazy row')
            ->assertSee('lazy-row');
    }

    public function test_scroll_to_reaches_a_row_inside_a_scrollable_pane(): void
    {
        $this->browser()
            ->visit('/scroll.html')
            ->scrollTo('Pane row')
            ->assertSee('pane-row');
    }

    public function test_scroll_to_bottom_and_back_to_top(): void
    {
        $this->browser()
            ->visit('/scroll.html')
            ->scrollToBottom()
            ->assertExpression('window.scrollY > 0')
            ->scrollToTop()
            ->assertExpression('window.scrollY === 0');
    }

    public function test_a_natural_language_step_scrolls(): void
    {
        $this->browser()
            ->visit('/scroll.html')
            ->step('I scroll to "Lazy row"')
            ->assertSee('lazy-row');
    }
}
