<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Lit — the whole app is a `<todo-app>` custom element, and each todo is a
 * `<todo-item>` with its **own** shadow root. Resolution pierces shadow roots
 * for CSS (#151) and text/label (#162) strategies, actionability and `assertSee()`
 * are shadow-aware, and the scenarios read state through `pierce()` (#165) — so
 * Lit now runs the behavioural suite like any other app. Selectors stay
 * single-root (`.editing .edit`, not `.todo-list li.editing .edit`), since a CSS
 * descendant combinator can't span the boundary between the list's shadow and an
 * item's shadow.
 *
 * The three knownIssues below are genuine Lit behaviours, not selector gaps.
 */
#[Group('todomvc')]
final class LitAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'lit',
            path: 'examples/lit/dist/',
            knownIssues: [
                // Lit commits an inline edit on blur, not on Enter (Enter blurs
                // the input), and headless Firefox never fires that blur (#143).
                'test_enter_saves_an_edit' => 'commits edits on blur, not Enter; headless Firefox does not fire the blur (#143)',
                'test_editing_to_empty_destroys_the_item' => 'commits edits on blur, not Enter; headless Firefox does not fire the blur (#143)',
                // This build neither trims the new-todo value nor rejects a
                // whitespace-only one — an upstream deviation from app-spec.md.
                'test_input_is_trimmed_and_whitespace_only_is_rejected' => 'this build does not trim or reject whitespace-only input (upstream deviation)',
                // Edit mode is entered fine, but Lit auto-focuses the edit input
                // via the autofocus attribute, which headless Firefox doesn't
                // honor (the window is never focused), so nothing ends up focused.
                'test_double_click_enters_edit_mode_and_focuses_the_input' => 'edit input uses the autofocus attribute, which headless Firefox does not honor (#143-adjacent)',
            ],
        );
    }
}
