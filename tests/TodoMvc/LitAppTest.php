<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Lit — the whole app is a `<todo-app>` custom element rendering into nested
 * shadow roots. Resolution pierces shadow roots for both CSS (#151) and
 * text/label (#162) strategies, so the app is drivable by placeholder, link
 * text, button text, or sibling label. What still blocks a full run is that
 * these scenarios read state via `document.querySelectorAll` in `evaluate()`,
 * which doesn't pierce (#165). So `usesShadowDom` keeps the behavioural scenarios
 * skipped with one reason; only the framework-marker smoke test runs.
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
            usesShadowDom: true,
        );
    }
}
