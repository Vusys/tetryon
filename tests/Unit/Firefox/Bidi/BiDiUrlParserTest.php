<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\BiDiUrlParser;

#[CoversClass(BiDiUrlParser::class)]
final class BiDiUrlParserTest extends TestCase
{
    public function test_it_extracts_the_url_from_the_real_startup_line(): void
    {
        $line = 'WebDriver BiDi listening on ws://127.0.0.1:41433';

        self::assertSame('ws://127.0.0.1:41433', BiDiUrlParser::fromStartupLine($line));
    }

    public function test_it_ignores_unrelated_output(): void
    {
        self::assertNull(BiDiUrlParser::fromStartupLine('*** You are running in headless mode.'));
    }

    public function test_it_finds_the_url_anywhere_in_a_multiline_log(): void
    {
        $log = "*** You are running in headless mode.\n"
            ."WebDriver BiDi listening on ws://127.0.0.1:55001\n"
            ."some later noise\n";

        self::assertSame('ws://127.0.0.1:55001', BiDiUrlParser::fromStartupLog($log));
    }

    public function test_it_returns_null_when_no_url_is_present(): void
    {
        self::assertNull(BiDiUrlParser::fromStartupLog("line one\nline two\n"));
    }
}
