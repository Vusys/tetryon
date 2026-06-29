<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Selector resolution prefers a visible, hit-testable match over an occluded or
 * hidden duplicate when several elements share the target (issue #101). Without
 * the preference the first DOM match — here a control fully covered by an
 * overlay — is chosen and the click times out as occluded, even though an
 * identical, clickable control exists.
 */
final class ResolutionTest extends StaticSiteTestCase
{
    public function test_click_prefers_the_visible_control_over_an_occluded_duplicate(): void
    {
        $this->browser()
            ->visit('/resolution-occluded-duplicate.html')
            ->click('Pick me')
            ->assertSee('picked');
    }
}
