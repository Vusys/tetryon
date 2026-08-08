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
        );
    }
}
