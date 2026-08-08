<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * The plainest of the ten — vanilla ES6, no framework runtime. This is the app
 * the shared scenario suite is designed against, so it should pass every
 * scenario the others might not.
 */
#[Group('todomvc')]
final class JavascriptEs6Test extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'javascript-es6',
            path: 'examples/javascript-es6/dist/',
            toggleAllId: '', // the input has no id, only the .toggle-all class
            knownIssues: [
                // This app commits an inline edit on blur (Enter calls blur()),
                // and headless Firefox never fires blur/focusout (#143).
                'test_enter_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_blur_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_editing_to_empty_destroys_the_item' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                // Unlike every other app, toggle-all binds its handler to the
                // label's click (which then clicks the input), not to the input's
                // change — so a synthetic click on the input doesn't complete all.
                'test_toggle_all_completes_and_uncompletes_everything' => 'toggle-all handler is on the label click, not the input change; a synthetic input click does not trigger it',
            ],
        );
    }
}
