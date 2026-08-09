<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Lit — the whole app is a `<todo-app>` custom element rendering into nested
 * shadow roots. Resolution now pierces shadow roots for CSS-based targets (#151),
 * so a web-component app is drivable by test-id / placeholder / CSS — but this
 * suite drives behaviourally by text and label, which resolve via XPath and don't
 * pierce yet (#162). So `usesShadowDom` keeps the behavioural scenarios skipped
 * with one reason; only the framework-marker smoke test runs.
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
