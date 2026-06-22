<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Observing the network (#70): synchronise on a request/response instead of
 * polling the DOM, and assert which requests did or didn't fire.
 */
final class NetworkTest extends StaticSiteTestCase
{
    public function test_wait_for_response_synchronises_on_the_fetch(): void
    {
        $this->browser()
            ->visit('/network.html')
            ->click('Load')
            ->waitForResponse('/api-data.json')
            ->assertSee('loaded from api');
    }

    public function test_wait_for_request_matches_a_glob(): void
    {
        $this->browser()
            ->visit('/network.html')
            ->click('Load')
            ->waitForRequest('*/api-data*')
            ->assertRequested('*/api-data*');
    }

    public function test_assert_requested_and_not_requested(): void
    {
        $this->browser()
            ->visit('/network.html')
            ->click('Load')
            ->waitForResponse('/api-data.json')
            ->assertRequested('/api-data.json')
            ->assertNotRequested('/telemetry');
    }
}
