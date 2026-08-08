<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Base for the TodoMVC cross-framework compatibility suite. One `php -S` server
 * serves every fetched app from tests/Fixtures/todomvc/, and each concrete
 * subclass names the app it drives via {@see app()}. The shared behavioural
 * scenarios live here so they stay byte-identical across the ten frameworks;
 * per-app deviations are declared as `knownIssues` on the {@see TodoMvcApp}.
 *
 * Skips (never fails) when Firefox is absent or the fixtures have not been
 * fetched, so the opt-in `TodoMvc` suite is green on a machine that has run
 * neither.
 */
abstract class TodoMvcTestCase extends BrowserTestCase
{
    protected ?StaticSiteServer $server = null;

    private const string FIXTURES = __DIR__.'/../Fixtures/todomvc';

    abstract protected function app(): TodoMvcApp;

    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping TodoMVC compatibility test.');
        }

        if (! is_dir(self::FIXTURES)) {
            self::markTestSkipped('TodoMVC fixtures are missing; run `composer todomvc:fetch` first.');
        }

        $reason = $this->app()->knownIssue($this->name());
        if ($reason !== null) {
            self::markTestSkipped("Known issue on {$this->app()->name}: {$reason}");
        }

        $this->server = StaticSiteServer::start(self::FIXTURES);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    /**
     * Navigate to the app under test and return the browser, ready for the
     * scenario to act on.
     */
    protected function visitApp(): Browser
    {
        return $this->browser()->visit($this->app()->url());
    }

    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        return new Configuration(baseUrl: $baseUrl);
    }

    public function test_the_app_loads_with_its_framework_marker(): void
    {
        $this->visitApp()->assertExpressionEquals(
            'document.documentElement.dataset.framework',
            $this->app()->name,
            "Expected the {$this->app()->name} app to load with its data-framework marker.",
        );
    }
}
