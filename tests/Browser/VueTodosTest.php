<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Drives a reactive Vue todo app: adding grows the list, the Add button is
 * disabled until there's a draft (so pressing it exercises the actionability
 * wait), completing and removing update a computed count.
 */
final class VueTodosTest extends StaticSiteTestCase
{
    public function test_add_complete_and_remove(): void
    {
        $this->browser()
            ->visit('/vue-app/todos.html')
            ->assertSee('0 remaining')
            ->fill('New todo', 'Write docs')
            ->press('Add')                  // disabled until the draft is non-empty
            ->assertSee('Write docs')
            ->assertSee('1 remaining')
            ->fill('New todo', 'Ship v0.4')
            ->press('Add')
            ->assertSee('2 remaining')
            ->check('@done-0')              // complete the first todo
            ->assertSee('1 remaining')
            ->click('@remove-1')            // remove the second
            ->assertDontSee('Ship v0.4')
            ->assertSee('0 remaining');
    }
}
