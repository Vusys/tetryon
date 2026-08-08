<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Lit — a time-boxed spike, not a port. The whole app is a `<todo-app>` custom
 * element rendering into nested shadow roots, so every light-DOM selector
 * strategy misses it (even `.new-todo` is unreachable). `usesShadowDom` marks
 * the app so the base skips every behavioural scenario with one reason; only the
 * framework-marker smoke test (which reads the light-DOM `<html>`) runs. The real
 * fix — piercing shadow DOM — is tracked in #151 and is out of scope for v1.
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
