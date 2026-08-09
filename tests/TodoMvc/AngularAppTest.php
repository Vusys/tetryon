<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Angular 21. Renders into `<app-root>` (light DOM, all reachable) and builds to
 * `dist/browser/` — the only app served from a subpath. Its toggle-all input has
 * no id at all, so it resolves by the `.toggle-all` class.
 */
#[Group('todomvc')]
final class AngularAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'angular',
            path: 'examples/angular/dist/browser/',
            toggleAllId: '',
        );
    }
}
