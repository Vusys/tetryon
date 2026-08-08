<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * jQuery (2023 rewrite): Handlebars templates, director.js hash routing, and
 * jQuery delegation that blows away and re-renders `.todo-list` wholesale on
 * every change — the classic source of stale-element failures, exercising our
 * element re-resolution in a way the reactive frameworks don't. The only app
 * whose "All" filter links to `#/all`, and it removes `.clear-completed` from
 * the DOM entirely (rather than hiding it) when nothing is completed.
 */
#[Group('todomvc')]
final class JqueryAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'jquery',
            path: 'examples/jquery/dist/',
            allFilterHref: '#/all',
            knownIssues: [
                // Every jQuery edit commit runs through `focusout` — Enter and
                // Escape both just call `blur()` — and headless Firefox never
                // fires blur/focusout (#143), so all four edit flows are blocked.
                'test_blur_saves_an_edit' => 'edit commits on focusout, which headless Firefox does not fire (#143)',
                'test_enter_saves_an_edit' => 'Enter commits via focusout (it calls blur()), which headless Firefox does not fire (#143)',
                'test_escape_discards_an_edit' => 'Escape discards via focusout (it calls blur()), which headless Firefox does not fire (#143)',
                'test_editing_to_empty_destroys_the_item' => 'edit-to-empty destroys via focusout, which headless Firefox does not fire (#143)',
            ],
        );
    }
}
