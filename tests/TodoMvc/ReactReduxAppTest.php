<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * React Redux 9 + Redux Toolkit 2. Same view layer as React, but every
 * interaction round-trips through a Redux store — the app most likely to expose
 * an auto-wait race. Carries React's `data-testid` set, so it should resolve
 * identically; a divergence would point at something real.
 */
#[Group('todomvc')]
final class ReactReduxAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'react-redux',
            path: 'examples/react-redux/dist/',
            knownIssues: [
                'test_blur_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_editing_to_empty_destroys_the_item' => 'react-redux exits edit on Enter but does not destroy an emptied todo (upstream deviation)',
            ],
        );
    }
}
