<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Svelte 5 (runes). The cleanest of the modern set structurally — it keeps the
 * template's `id="toggle-all"` and "Mark all as complete" label. It does commit
 * inline edits on blur rather than Enter, so its edit scenarios are blocked by
 * the headless-blur limitation (#143).
 */
#[Group('todomvc')]
final class SvelteAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'svelte',
            path: 'examples/svelte/dist/',
            knownIssues: [
                'test_blur_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_enter_saves_an_edit' => 'this app commits edits on blur only; Enter does not commit, and headless Firefox does not fire blur (#143)',
                'test_editing_to_empty_destroys_the_item' => 'this app commits edits on blur only; Enter does not commit, and headless Firefox does not fire blur (#143)',
            ],
        );
    }
}
