<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

#[Group('todomvc')]
final class ReactAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'react',
            path: 'examples/react/dist/',
            knownIssues: [
                'test_blur_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_escape_discards_an_edit' => 'React does not handle Escape to cancel an inline edit (upstream deviation)',
            ],
        );
    }
}
