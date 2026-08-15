<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit\Recording;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\PHPUnit\Recording\ExternalTool;

#[CoversClass(ExternalTool::class)]
final class ExternalToolTest extends TestCase
{
    public function test_it_finds_an_executable_on_the_given_path(): void
    {
        $bin = sys_get_temp_dir().'/tetryon-external-tool-test-'.bin2hex(random_bytes(4));
        mkdir($bin);
        $tool = $bin.'/my-tool';
        file_put_contents($tool, "#!/bin/sh\n");
        chmod($tool, 0o755);

        try {
            self::assertSame($tool, ExternalTool::locate('my-tool', $bin));
        } finally {
            unlink($tool);
            rmdir($bin);
        }
    }

    public function test_it_returns_null_when_the_tool_is_not_on_the_path(): void
    {
        self::assertNull(ExternalTool::locate('definitely-not-a-real-tool', '/usr/bin'));
    }

    public function test_it_returns_null_for_an_empty_path(): void
    {
        self::assertNull(ExternalTool::locate('magick', ''));
    }

    public function test_it_ignores_a_file_that_is_not_executable(): void
    {
        $bin = sys_get_temp_dir().'/tetryon-external-tool-test-'.bin2hex(random_bytes(4));
        mkdir($bin);
        $tool = $bin.'/not-executable';
        file_put_contents($tool, "#!/bin/sh\n");
        chmod($tool, 0o644);

        try {
            self::assertNull(ExternalTool::locate('not-executable', $bin));
        } finally {
            unlink($tool);
            rmdir($bin);
        }
    }
}
