<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Covers the remaining interaction verbs against real Firefox: pointer
 * variants (double/right click, hover), keyboard (pressKey), radios (choose),
 * and file inputs (upload).
 */
final class ActionsTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping actions test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        return new Configuration(baseUrl: $baseUrl);
    }

    public function test_pointer_and_keyboard_verbs(): void
    {
        $this->browser()
            ->visit('/actions.html')
            ->doubleClick('Double me')->assertSee('double')
            ->rightClick('Right click me')->assertSee('context')
            ->hover('Hover me')->assertSee('hovered')
            ->click('@keyin')->pressKey('Enter')->assertSee('enter');
    }

    public function test_choose_radio_and_upload_file(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'tetryon-upload').'.txt';
        file_put_contents($file, 'avatar');

        try {
            $this->browser()
                ->visit('/actions.html')
                ->choose('plan', 'pro')
                ->press('Show plan')
                ->assertSee('plan:pro')
                ->upload('@avatar', $file)
                ->assertSee('file:'.basename($file));
        } finally {
            @unlink($file);
        }
    }
}
