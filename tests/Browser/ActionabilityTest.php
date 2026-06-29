<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Config\Timeouts;
use Vusys\Tetryon\Core\Support\TimeoutException;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Proves the hardened actionability check against real Firefox: clicks wait out
 * in-progress transitions, transparency, disabled pointer events and overlay
 * occlusion instead of landing early or silently no-opping, and a permanently
 * occluded target fails with an error naming the interceptor (issue #94).
 */
final class ActionabilityTest extends StaticSiteTestCase
{
    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        // Comfortably clears the 300ms fixture transitions while keeping the
        // negative (never-actionable) case from sitting on the 5s default.
        return new Configuration(baseUrl: $baseUrl, timeouts: new Timeouts(default: 2000));
    }

    public function test_click_waits_for_a_sliding_panel_to_settle(): void
    {
        $this->browser()
            ->visit('/actionability-transform.html')
            ->press('Open')
            ->click('Pick')
            ->assertSee('settled');
    }

    public function test_click_waits_for_a_transparent_target_to_appear(): void
    {
        $this->browser()
            ->visit('/actionability-opacity.html')
            ->press('Reveal')
            ->click('Target')
            ->assertSee('clicked');
    }

    public function test_click_waits_for_a_fading_overlay_to_clear(): void
    {
        $this->browser()
            ->visit('/actionability-overlay.html')
            ->press('Open')
            ->click('Save')
            ->assertSee('saved');
    }

    public function test_click_waits_for_pointer_events_to_be_enabled(): void
    {
        $this->browser()
            ->visit('/actionability-pointer-events.html')
            ->press('Enable')
            ->click('Target')
            ->assertSee('clicked');
    }

    public function test_click_scrolls_a_below_the_fold_target_into_view(): void
    {
        $this->browser()
            ->visit('/actionability-below-fold.html')
            ->click('Target')
            ->assertSee('clicked');
    }

    public function test_click_targets_an_icon_only_control_in_a_scroll_container(): void
    {
        $this->browser()
            ->visit('/actionability-scroll-container.html')
            ->click('[data-action="delete-row"]')
            ->assertSee('deleted');
    }

    public function test_click_clears_a_fixed_bar_when_scrolling_into_view(): void
    {
        $this->browser()
            ->visit('/actionability-fixed-bar.html')
            ->click('Target')
            ->assertSee('clicked');
    }

    public function test_click_lands_under_smooth_scrolling(): void
    {
        $this->browser()
            ->visit('/actionability-smooth-scroll.html')
            ->click('Target')
            ->assertSee('clicked');
    }

    public function test_click_inside_a_scroll_dismissing_overlay_does_not_dismiss_it(): void
    {
        $this->browser()
            ->visit('/actionability-scroll-dismiss-overlay.html')
            ->press('Open')
            ->click('Pick')
            ->assertSee('picked');
    }

    public function test_click_on_a_permanently_occluded_target_reports_the_interceptor(): void
    {
        $this->expectException(TimeoutException::class);
        $this->expectExceptionMessageMatches('/occluded:#cover/');

        $this->browser()
            ->visit('/actionability-occluded.html')
            ->click('Target');
    }
}
