<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Config\Viewport;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * The configured viewport is what the page and its screenshots get — it used
 * to be parsed and then never sent to the browser.
 */
final class ViewportTest extends StaticSiteTestCase
{
    #[Override]
    protected function browserConfiguration(): Configuration
    {
        return new Configuration(
            baseUrl: $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000',
            viewport: new Viewport(800, 600, 2.0),
        );
    }

    public function test_the_page_gets_the_configured_viewport_and_pixel_ratio(): void
    {
        $browser = $this->browser()->visit('/index.html');

        self::assertSame('[800,600,2]', $browser->evaluate('JSON.stringify([innerWidth, innerHeight, devicePixelRatio])'));

        $size = getimagesizefromstring($this->driver()->screenshot());
        self::assertSame([1600, 1200], [$size[0] ?? null, $size[1] ?? null]);
    }
}
