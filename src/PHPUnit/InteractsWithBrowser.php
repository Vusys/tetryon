<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Support\StreamLogger;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\LaunchOptions;

/**
 * Gives any PHPUnit test a fluent {@see Browser} via `$this->browser()`, and
 * tears the browser down after each test — capturing failure diagnostics first
 * if the test did not pass. The escape hatch for tests that cannot extend
 * {@see BrowserTestCase}.
 *
 * Override {@see browserConfiguration()} to point at a different base URL,
 * Firefox binary, or artifact path.
 *
 * @phpstan-require-extends TestCase
 */
trait InteractsWithBrowser
{
    private ?FirefoxBiDiDriver $tetryonDriver = null;

    private ?Browser $tetryonBrowser = null;

    private ?Configuration $tetryonConfiguration = null;

    protected function browser(): Browser
    {
        if ($this->tetryonBrowser instanceof Browser) {
            return $this->tetryonBrowser;
        }

        $configuration = $this->tetryonConfiguration = $this->browserConfiguration();
        $this->tetryonDriver = new FirefoxBiDiDriver(
            new LaunchOptions(headless: $configuration->headless, binary: $configuration->firefoxBinary),
            $this->browserLogger(),
        );
        $this->tetryonDriver->start();

        return $this->tetryonBrowser = new Browser($this->tetryonDriver, $configuration);
    }

    protected function scenario(): Scenario
    {
        return new Scenario($this->browser());
    }

    protected function browserConfiguration(): Configuration
    {
        return Configuration::fromEnvironment();
    }

    /**
     * The PSR-3 logger the browser logs BiDi traffic to. Silent by default;
     * set TETRYON_DEBUG to stream the command log to stderr, or override.
     */
    protected function browserLogger(): LoggerInterface
    {
        $debug = getenv('TETRYON_DEBUG');

        return in_array($debug, [false, '', '0'], true)
            ? new NullLogger
            : new StreamLogger(STDERR);
    }

    #[After]
    protected function stopTetryonBrowser(): void
    {
        $driver = $this->tetryonDriver;
        $configuration = $this->tetryonConfiguration;

        if ($driver instanceof FirefoxBiDiDriver && $configuration instanceof Configuration && $this->browserTestFailed()) {
            $report = new FailureArtifacts($configuration->artifactsPath)
                ->capture($driver, $configuration, static::class.'::'.$this->name());
            fwrite(STDERR, $report."\n");
        }

        $driver?->stop();
        $this->tetryonDriver = null;
        $this->tetryonBrowser = null;
        $this->tetryonConfiguration = null;
    }

    private function browserTestFailed(): bool
    {
        $status = $this->status();
        if ($status->isError()) {
            return true;
        }

        return (bool) $status->isFailure();
    }
}
