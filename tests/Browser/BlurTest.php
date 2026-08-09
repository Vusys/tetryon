<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * blur() drives "commit on blur" flows, and must work in headless Firefox — which
 * never fires blur/focusout on its own — by synthesising the events when the
 * browser doesn't (#142, #143).
 */
final class BlurTest extends StaticSiteTestCase
{
    public function test_blur_a_target_fires_focusout_and_commits(): void
    {
        $this->browser()
            ->visit('/blur.html')
            ->fill('@editor', 'final')
            ->assertSee('unsaved')
            ->blur('@editor')
            ->assertSee('saved:final');
    }

    public function test_blur_with_no_target_blurs_the_active_element(): void
    {
        $this->browser()
            ->visit('/blur.html')
            ->fill('@editor', 'from-active')
            ->blur()
            ->assertSee('saved:from-active');
    }
}
