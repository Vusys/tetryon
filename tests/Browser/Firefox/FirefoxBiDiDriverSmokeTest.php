<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser\Firefox;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\ConsoleMessage;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\Firefox\LaunchOptions;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * The v0.1 deliverable: prove the whole stack against a real Firefox over
 * WebDriver BiDi — launch, navigate, evaluate JS, screenshot, collect console
 * output, tear down. Opt-in (Browser suite); skipped when Firefox is absent.
 */
#[CoversNothing]
final class FirefoxBiDiDriverSmokeTest extends TestCase
{
    private ?StaticSiteServer $server = null;

    private ?FirefoxBiDiDriver $driver = null;

    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping the BiDi smoke test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../../Fixtures/static-site');
    }

    protected function tearDown(): void
    {
        $this->driver?->stop();
        $this->server?->stop();
    }

    public function test_it_drives_a_real_firefox_end_to_end(): void
    {
        $server = $this->server ?? self::fail('Static-site server did not start.');
        $base = $server->baseUrl;

        $this->driver = new FirefoxBiDiDriver(new LaunchOptions(headless: true));
        $this->driver->start();
        $this->driver->navigate($base.'/index.html');

        self::assertSame('Tetryon Spike', $this->driver->title());
        self::assertStringContainsString('/index.html', $this->driver->currentUrl());
        self::assertSame(
            'Hello, Tetryon',
            $this->driver->evaluateScript('document.querySelector("[data-testid=heading]").textContent'),
        );

        $png = $this->driver->screenshot();
        self::assertStringStartsWith("\x89PNG\r\n\x1a\n", $png);

        $logged = array_map(
            static fn (ConsoleMessage $message): string => $message->text,
            $this->driver->consoleMessages(),
        );
        self::assertNotEmpty(
            array_filter($logged, static fn (string $text): bool => str_contains($text, 'spike-console')),
            'Expected the page console.log to be captured.',
        );

        self::assertStringContainsString('browsingContext.navigate', (string) $this->driver->trace());
    }

    public function test_it_types_into_a_field_and_clicks_a_button(): void
    {
        $server = $this->server ?? self::fail('Static-site server did not start.');

        $this->driver = new FirefoxBiDiDriver(new LaunchOptions(headless: true));
        $this->driver->start();
        $this->driver->navigate($server->baseUrl.'/form.html');

        $this->driver->type('[data-testid=name]', 'Bryan');
        $this->driver->click('[data-testid=save]');

        // The DOM only updates if real input + click events fired.
        self::assertSame(
            'Saved: Bryan',
            $this->driver->evaluateScript('document.querySelector("[data-testid=result]").textContent'),
        );
    }
}
