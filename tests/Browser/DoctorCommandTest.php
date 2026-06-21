<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Console\Application;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;

/**
 * Runs the real `doctor` command (which launches headless Firefox) and asserts
 * it reports a ready environment. Skipped when Firefox is absent.
 */
final class DoctorCommandTest extends TestCase
{
    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping doctor command test.');
        }
    }

    public function test_doctor_reports_ready_with_firefox_available(): void
    {
        $stream = fopen('php://memory', 'rw+');
        self::assertIsResource($stream);

        $code = new Application($stream)->run(['tetryon', 'doctor']);

        rewind($stream);
        $output = (string) stream_get_contents($stream);
        fclose($stream);

        self::assertSame(0, $code, $output);
        self::assertStringContainsString('Headless launch   OK', $output);
        self::assertStringContainsString('Ready.', $output);
    }
}
