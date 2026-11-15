<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
use Vusys\Tetryon\PHPUnit\Report\Moment;
use Vusys\Tetryon\PHPUnit\Report\ReportManifest;
use Vusys\Tetryon\PHPUnit\Report\ReportRenderer;
use Vusys\Tetryon\PHPUnit\Report\TestRecording;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * The combined report's sidebar groups tests by folder, then class — driven
 * in a real Firefox, since the grouping, the remembered open state and the
 * filter all live in the report's own script.
 */
final class ReportSidebarTest extends BrowserTestCase
{
    private ?StaticSiteServer $server = null;

    private string $reportPath = '';

    #[Override]
    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping report sidebar test.');
        }

        $this->reportPath = sys_get_temp_dir().'/tetryon-sidebar-'.bin2hex(random_bytes(4));
        mkdir($this->reportPath);

        $recordings = array_map(
            static fn (array $test): TestRecording => new TestRecording($test[0], $test[1], $test[2], [new Moment('', 'Passed', 0, 0, 0)]),
            [
                ['Tests\Browser\Auth\LoginTest::test_login', 'A user logs in', true],
                ['Tests\Browser\Auth\LogoutTest::test_logout', 'A user logs out', true],
                ['Tests\Browser\Schema\SchemaEditorTest::test_add', 'A field is added', true],
                ['Tests\Browser\Schema\SchemaEditorTest::test_delete', 'A field is deleted', false],
            ],
        );
        $manifest = ReportManifest::build($recordings, [['a.png'], ['a.png'], ['a.png'], ['a.png']], [null, null, null, null]);
        file_put_contents($this->reportPath.'/index.html', ReportRenderer::renderHtml($manifest));

        $this->server = StaticSiteServer::start($this->reportPath);
    }

    #[Override]
    protected function tearDown(): void
    {
        $this->server?->stop();
        array_map(unlink(...), glob($this->reportPath.'/*') ?: []);
        @rmdir($this->reportPath);
    }

    #[Override]
    protected function browserConfiguration(): Configuration
    {
        return new Configuration(baseUrl: $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000');
    }

    public function test_tests_are_grouped_by_folder_then_class_and_open_on_the_failure(): void
    {
        $this->browser()->visit('/index.html')
            ->assertExpressionEquals($this->openGroups(), 'Schema,Schema/Schema editor')
            ->assertExpressionEquals("document.querySelector('[data-group=\"Schema\"] .group-count').textContent", '1 ✗ · 2')
            ->assertSee('test 4 of 4');
    }

    public function test_a_group_remembers_being_opened_and_the_filter_opens_every_match(): void
    {
        $this->browser()->visit('/index.html')
            ->click('[data-group="Auth"] > summary')
            ->click('[data-group="Auth/Login"] > summary')
            ->click('A user logs in')
            ->assertExpressionEquals($this->openGroups(), 'Auth,Auth/Login,Schema,Schema/Schema editor')
            ->fill('#filter', 'logs')
            ->assertExpressionEquals($this->openGroups(), 'Auth,Auth/Login,Auth/Logout')
            ->fill('#filter', '')
            ->assertExpressionEquals($this->openGroups(), 'Auth,Auth/Login,Schema,Schema/Schema editor');
    }

    private function openGroups(): string
    {
        return "Array.from(document.querySelectorAll('details[open]'), (d) => d.dataset.group).join(',')";
    }
}
