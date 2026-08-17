<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\TestCase;
use PHPUnit\Logging\TestDox\NamePrettifier;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Support\StreamLogger;
use Vusys\Tetryon\Firefox\Exception\FirefoxException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\LaunchOptions;
use Vusys\Tetryon\PHPUnit\Report\SuiteReport;
use Vusys\Tetryon\PHPUnit\Report\TestRecording;

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

    /**
     * The underlying Firefox driver, for the rare advanced case the fluent
     * {@see Browser} API doesn't model. Boots the browser if it hasn't started.
     * Prefer {@see Browser::evaluate()} for in-page JavaScript — reach for this
     * only when a subclass needs the driver primitives directly.
     */
    protected function driver(): FirefoxBiDiDriver
    {
        $this->browser();

        return $this->tetryonDriver ?? throw new FirefoxException('Browser driver failed to start.');
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
        $failed = $this->browserTestFailed();

        $bag = ($driver instanceof FirefoxBiDiDriver && $failed) ? FailureArtifacts::captureBag($driver) : null;

        $browser = $this->tetryonBrowser;
        if ($browser instanceof Browser) {
            $fallbackTitle = new NamePrettifier()->prettifyTestCase($this, false);
            $recording = $browser->finishedRecording(static::class.'::'.$this->name(), ! $failed, $bag, $fallbackTitle);
            if ($recording instanceof TestRecording) {
                SuiteReport::append($recording);
            }
        }

        if ($driver instanceof FirefoxBiDiDriver && $configuration instanceof Configuration && $bag instanceof ArtifactBag) {
            $directory = FailureArtifacts::directoryFor($configuration->artifactsPath, static::class.'::'.$this->name());
            $report = FailureArtifacts::write($bag, $directory, $configuration);
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
