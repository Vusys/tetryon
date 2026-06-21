<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Console\Application;

#[CoversClass(Application::class)]
final class ApplicationTest extends TestCase
{
    public function test_help_is_shown_by_default(): void
    {
        [$code, $output] = $this->invoke(['tetryon']);

        self::assertSame(0, $code);
        self::assertStringContainsString('tetryon doctor', $output);
        self::assertStringContainsString('--testsuite Browser', $output);
    }

    public function test_unknown_commands_show_help(): void
    {
        [$code, $output] = $this->invoke(['tetryon', 'wibble']);

        self::assertSame(0, $code);
        self::assertStringContainsString('Usage:', $output);
    }

    /**
     * @param  list<string>  $argv
     * @return array{0: int, 1: string}
     */
    private function invoke(array $argv): array
    {
        $stream = fopen('php://memory', 'rw+');
        self::assertIsResource($stream);

        $code = new Application($stream)->run($argv);

        rewind($stream);
        $output = stream_get_contents($stream);
        fclose($stream);

        return [$code, is_string($output) ? $output : ''];
    }
}
