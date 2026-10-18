<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Diagnostics\FailureReport;

#[CoversClass(FailureReport::class)]
final class FailureReportTest extends TestCase
{
    public function test_it_renders_the_url_and_artifact_paths(): void
    {
        $bag = new ArtifactBag(
            url: 'http://127.0.0.1:8000/settings',
            screenshotPng: 'bytes',
            html: '<html></html>',
            consoleLines: [],
            networkLines: [],
            trace: '',
            browserStderr: '',
        );

        $report = FailureReport::render($bag, [
            'Screenshot' => 'artifacts/screenshot.png',
            'HTML' => 'artifacts/page.html',
        ]);

        self::assertStringContainsString('Tetryon browser diagnostics', $report);
        self::assertStringContainsString('http://127.0.0.1:8000/settings', $report);
        self::assertStringContainsString('Screenshot: artifacts/screenshot.png', $report);
        self::assertStringContainsString('HTML: artifacts/page.html', $report);
    }

    public function test_it_renders_with_no_artifact_paths(): void
    {
        $bag = new ArtifactBag(
            url: '(unknown)',
            screenshotPng: null,
            html: null,
            consoleLines: [],
            networkLines: [],
            trace: '',
            browserStderr: '',
        );

        $report = FailureReport::render($bag, []);

        self::assertStringContainsString('Artifacts:', $report);
        self::assertStringContainsString('(unknown)', $report);
    }
}
