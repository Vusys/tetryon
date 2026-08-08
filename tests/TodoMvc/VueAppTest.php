<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;

/**
 * Vue 3.5. Renames toggle-all to `id="toggle-all-input"` and carries an upstream
 * bug — it writes React's `htmlFor` instead of `for`, so the toggle-all label is
 * genuinely not associated with its input. Driving the checkbox by id sidesteps
 * that, which is exactly the point: we degrade sensibly when a label only looks
 * associated.
 */
#[Group('todomvc')]
final class VueAppTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        return new TodoMvcApp(
            name: 'vue',
            path: 'examples/vue/dist/',
            toggleAllId: 'toggle-all-input',
            knownIssues: [
                'test_blur_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
            ],
        );
    }
}
