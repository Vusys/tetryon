<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Attributes\After;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\LaunchOptions;

/**
 * Gives any PHPUnit test a fluent {@see Browser} via `$this->browser()`, and
 * tears the browser down after each test. The escape hatch for tests that
 * cannot extend {@see BrowserTestCase}.
 *
 * Override {@see browserConfiguration()} to point at a different base URL or
 * Firefox binary.
 */
trait InteractsWithBrowser
{
    private ?FirefoxBiDiDriver $tetryonDriver = null;

    private ?Browser $tetryonBrowser = null;

    protected function browser(): Browser
    {
        if ($this->tetryonBrowser instanceof Browser) {
            return $this->tetryonBrowser;
        }

        $configuration = $this->browserConfiguration();
        $this->tetryonDriver = new FirefoxBiDiDriver(new LaunchOptions(
            headless: $configuration->headless,
            binary: $configuration->firefoxBinary,
        ));
        $this->tetryonDriver->start();

        return $this->tetryonBrowser = new Browser($this->tetryonDriver, $configuration);
    }

    protected function browserConfiguration(): Configuration
    {
        return Configuration::fromEnvironment();
    }

    #[After]
    protected function stopTetryonBrowser(): void
    {
        $this->tetryonDriver?->stop();
        $this->tetryonDriver = null;
        $this->tetryonBrowser = null;
    }
}
