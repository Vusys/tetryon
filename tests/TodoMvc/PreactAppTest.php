<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Preact 10. React-shaped source but no `data-testid`, and (like Angular) a
 * toggle-all input with no id, resolved by the `.toggle-all` class.
 */
#[Group('todomvc')]
final class PreactAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'preact',
            path: 'examples/preact/dist/',
            toggleAllId: '',
            knownIssues: [
                'test_blur_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
            ],
        );
    }
}
