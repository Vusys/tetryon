<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\Firefox\LaunchOptions;
use Vusys\Tetryon\PHPUnit\FailureArtifacts;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Verifies the failure diagnostics bundle is written and reported, driving the
 * capture directly against a real Firefox (no contrived test failure needed).
 */
final class DiagnosticsTest extends TestCase
{
    private ?StaticSiteServer $server = null;

    private ?FirefoxBiDiDriver $driver = null;

    private string $artifactsPath = '';

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping diagnostics test.');
        }

        $this->server = StaticSiteServer::start(__DIR__.'/../Fixtures/static-site');
        $this->artifactsPath = sys_get_temp_dir().'/tetryon-artifacts-'.bin2hex(random_bytes(4));
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->driver?->stop();
        $this->server?->stop();
        self::deleteTree($this->artifactsPath);
    }

    public function test_it_writes_and_reports_the_diagnostic_bundle(): void
    {
        $server = $this->server ?? self::fail('Static-site server did not start.');

        $this->driver = new FirefoxBiDiDriver(new LaunchOptions(headless: true));
        $this->driver->start();
        $this->driver->navigate($server->baseUrl.'/index.html');

        $report = new FailureArtifacts($this->artifactsPath)
            ->capture($this->driver, new Configuration(baseUrl: $server->baseUrl), 'SettingsTest::test_save');

        $directory = $this->artifactsPath.'/SettingsTest_test_save';
        self::assertFileExists($directory.'/screenshot.png');
        self::assertFileExists($directory.'/page.html');
        self::assertFileExists($directory.'/console.log');
        self::assertFileExists($directory.'/trace.log');
        self::assertFileExists($directory.'/info.txt');

        self::assertStringContainsString('Hello, Tetryon', (string) file_get_contents($directory.'/page.html'));
        self::assertStringContainsString('spike-console', (string) file_get_contents($directory.'/console.log'));
        self::assertStringContainsString('Tetryon browser diagnostics', $report);
        self::assertStringContainsString('/index.html', $report);
        self::assertStringContainsString('screenshot.png', $report);
    }

    private static function deleteTree(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }
        foreach ((array) scandir($path) as $entry) {
            if ($entry === '.') {
                continue;
            }
            if ($entry === '..') {
                continue;
            }
            if (! is_string($entry)) {
                continue;
            }
            $full = $path.'/'.$entry;
            is_dir($full) ? self::deleteTree($full) : @unlink($full);
        }
        @rmdir($path);
    }
}
