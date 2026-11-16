<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\AfterClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use ReflectionClass;
use ReflectionMethod;
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

    private bool $tetryonReusing = false;

    /**
     * One browser per test class that opted into reuse (#78), with the state
     * snapshotted after authenticate().
     *
     * @var array<class-string, array{driver: FirefoxBiDiDriver, configuration: Configuration, cookies: list<array<string, mixed>>, storage: ?string}>
     */
    private static array $tetryonReused = [];

    protected function browser(): Browser
    {
        if ($this->tetryonBrowser instanceof Browser) {
            return $this->tetryonBrowser;
        }

        $reuse = $this->browserReuse();
        if ($reuse instanceof ReuseBrowser) {
            $this->startReusedBrowser($reuse);
        } else {
            $configuration = $this->tetryonConfiguration = $this->browserConfiguration();
            $this->tetryonDriver = $this->launchBrowser($configuration);
        }

        $driver = $this->tetryonDriver ?? throw new FirefoxException('Browser driver failed to start.');
        $configuration = $this->tetryonConfiguration ?? throw new FirefoxException('Browser configuration was not resolved.');
        $browser = $this->tetryonBrowser = new Browser($driver, $configuration);
        $record = $this->recordAttribute();

        return $record instanceof Record ? $browser->recording($record->title) : $browser;
    }

    /**
     * Runs once per class when the browser is reused, after launch: log in or
     * set whatever state every test should start from. The browser's cookies
     * (and storage, if asked for) are snapshotted the moment it returns.
     */
    protected function authenticate(Browser $browser): void {}

    /** The class's #[ReuseBrowser] (or a parent's), or the ReusesBrowser trait's equivalent. */
    protected function browserReuse(): ?ReuseBrowser
    {
        for ($class = new ReflectionClass($this); $class instanceof ReflectionClass; $class = $class->getParentClass() ?: null) {
            $attributes = $class->getAttributes(ReuseBrowser::class);
            if ($attributes !== []) {
                return $attributes[0]->newInstance();
            }
        }

        if (method_exists($this, 'snapshotsBrowserStorage')) {
            $storage = $this->snapshotsBrowserStorage();

            return new ReuseBrowser(storage: $storage === true);
        }

        return null;
    }

    private function launchBrowser(Configuration $configuration): FirefoxBiDiDriver
    {
        $driver = new FirefoxBiDiDriver(
            new LaunchOptions(headless: $configuration->headless, binary: $configuration->firefoxBinary),
            $this->browserLogger(),
        );
        $driver->start();
        $viewport = $configuration->viewport;
        $driver->setViewport($viewport->width, $viewport->height, $viewport->devicePixelRatio);

        return $driver;
    }

    /**
     * Take the class's shared browser and restore its snapshot, or launch one,
     * authenticate it and take the snapshot. A browser that stopped answering
     * is replaced the same way.
     */
    private function startReusedBrowser(ReuseBrowser $reuse): void
    {
        $this->tetryonReusing = true;
        $shared = self::$tetryonReused[static::class] ?? null;

        if ($shared !== null && ! $shared['driver']->isResponsive()) {
            $shared['driver']->stop();
            $shared = null;
        }

        if ($shared !== null) {
            $this->tetryonDriver = $shared['driver'];
            $this->tetryonConfiguration = $shared['configuration'];
            $origin = self::tetryonOrigin($shared['configuration']);

            $shared['driver']->restoreCookies($shared['cookies'], $origin);
            if ($shared['storage'] !== null) {
                $shared['driver']->navigate($shared['configuration']->baseUrl);
                $shared['driver']->evaluateScript(self::restoreStorageScript($shared['storage']));
            }
            $shared['driver']->navigate('about:blank');
            $shared['driver']->resetDiagnostics();

            return;
        }

        $configuration = $this->tetryonConfiguration = $this->browserConfiguration();
        $driver = $this->tetryonDriver = $this->launchBrowser($configuration);
        $origin = self::tetryonOrigin($configuration);

        // authenticate() may call browser() itself (loginAs() does), so it gets this one.
        $this->tetryonBrowser = new Browser($driver, $configuration);
        $this->authenticate($this->tetryonBrowser);
        $this->tetryonBrowser = null;

        $storage = null;
        if ($reuse->storage) {
            if (! str_starts_with($driver->currentUrl(), $origin)) {
                $driver->navigate($configuration->baseUrl);
            }
            $snapshot = $driver->evaluateScript('JSON.stringify({local: Object.assign({}, localStorage), session: Object.assign({}, sessionStorage)})');
            $storage = is_string($snapshot) ? $snapshot : null;
        }

        self::$tetryonReused[static::class] = [
            'driver' => $driver,
            'configuration' => $configuration,
            'cookies' => $driver->cookies($origin),
            'storage' => $storage,
        ];

        $driver->navigate('about:blank');
        $driver->resetDiagnostics();
    }

    private static function tetryonOrigin(Configuration $configuration): string
    {
        $parts = parse_url($configuration->baseUrl);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? 'http') : 'http';
        $host = is_array($parts) ? ($parts['host'] ?? '127.0.0.1') : '127.0.0.1';
        $port = is_array($parts) && isset($parts['port']) ? ':'.$parts['port'] : '';

        return $scheme.'://'.$host.$port;
    }

    private static function restoreStorageScript(string $snapshot): string
    {
        return '(() => { const s = '.$snapshot.'; '
            .'localStorage.clear(); sessionStorage.clear(); '
            .'for (const [k, v] of Object.entries(s.local)) localStorage.setItem(k, v); '
            .'for (const [k, v] of Object.entries(s.session)) sessionStorage.setItem(k, v); })()';
    }

    /** Stop the class's reused browser, if it had one. */
    #[AfterClass]
    public static function stopReusedTetryonBrowser(): void
    {
        $shared = self::$tetryonReused[static::class] ?? null;
        unset(self::$tetryonReused[static::class]);
        if ($shared !== null) {
            $shared['driver']->stop();
        }
    }

    /** The test method's #[Record], else its class's (or a parent's). */
    private function recordAttribute(): ?Record
    {
        $method = new ReflectionMethod($this, $this->name());
        $attributes = $method->getAttributes(Record::class);

        for ($class = new ReflectionClass($this); $attributes === [] && $class instanceof ReflectionClass; $class = $class->getParentClass() ?: null) {
            $attributes = $class->getAttributes(Record::class);
        }

        return $attributes === [] ? null : $attributes[0]->newInstance();
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

        // A failure before any page loaded has no browser story to tell; the
        // test's own error says it all, without a block of blank artifacts.
        if ($bag instanceof ArtifactBag && $bag->isBlank()) {
            fwrite(STDERR, "Tetryon: the browser had not loaded a page when the test failed, so there are no browser diagnostics.\n");
            $bag = null;
        }

        $browser = $this->tetryonBrowser;
        if ($browser instanceof Browser) {
            // "test_guest_can_log_in" / "testGuestCanLogIn" → "Guest can log in".
            // Done by hand: PHPUnit's own NamePrettifier is internal, and its
            // constructor changed within the 13.x line.
            $words = preg_replace(['/^test_?/i', '/_+|(?<=.)(?=[A-Z])/'], ['', ' '], $this->name());
            $fallbackTitle = ucfirst(strtolower(trim((string) $words)));
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

        // A reused browser stays up for the class's next test; #[AfterClass] stops it.
        if (! $this->tetryonReusing) {
            $driver?->stop();
        }
        $this->tetryonDriver = null;
        $this->tetryonBrowser = null;
        $this->tetryonConfiguration = null;
        $this->tetryonReusing = false;
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
