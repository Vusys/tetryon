<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Drag-and-drop via synthetic pointer sequences (#83): a press-move-release with
 * intermediate moves, the gesture pointer-drag libraries recognise.
 */
final class DragTest extends StaticSiteTestCase
{
    public function test_drag_source_onto_target(): void
    {
        $this->browser()
            ->visit('/drag.html')
            ->drag('@handle', '@zone')
            ->assertSee('dropped after');
    }

    public function test_drag_down_by_offset_lands_on_the_zone(): void
    {
        $this->browser()
            ->visit('/drag.html')
            ->dragDown('@handle', 190)
            ->assertSee('dropped after');
    }
}
