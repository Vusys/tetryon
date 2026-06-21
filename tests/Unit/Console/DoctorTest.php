<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Console\Check;
use Vusys\Tetryon\Console\Doctor;

#[CoversClass(Doctor::class)]
#[CoversClass(Check::class)]
final class DoctorTest extends TestCase
{
    public function test_it_reports_ready_when_every_check_passes(): void
    {
        $doctor = new Doctor([
            Check::pass('PHP', '8.4.0'),
            Check::pass('Firefox', '/usr/bin/firefox'),
        ]);

        self::assertTrue($doctor->passed());

        $report = $doctor->report();
        self::assertStringContainsString('Tetryon Doctor', $report);
        self::assertStringContainsString('PHP', $report);
        self::assertStringContainsString('OK', $report);
        self::assertStringContainsString('Ready.', $report);
    }

    public function test_it_reports_failures_with_their_fix(): void
    {
        $doctor = new Doctor([
            Check::pass('PHP', '8.4.0'),
            Check::fail('Firefox', 'not found', 'Install Firefox from getfirefox.com.'),
        ]);

        self::assertFalse($doctor->passed());

        $report = $doctor->report();
        self::assertStringContainsString('FAIL', $report);
        self::assertStringContainsString('Install Firefox from getfirefox.com.', $report);
        self::assertStringContainsString('Not ready', $report);
    }
}
