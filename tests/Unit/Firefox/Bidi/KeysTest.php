<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\Keys;

#[CoversClass(Keys::class)]
final class KeysTest extends TestCase
{
    public function test_named_keys_map_to_webdriver_codepoints(): void
    {
        self::assertSame("\u{E007}", Keys::resolve('Enter'));
        self::assertSame("\u{E004}", Keys::resolve('Tab'));
        self::assertSame("\u{E00C}", Keys::resolve('Escape'));
        self::assertSame("\u{E015}", Keys::resolve('ArrowDown'));
    }

    public function test_unknown_keys_pass_through_unchanged(): void
    {
        self::assertSame('a', Keys::resolve('a'));
        self::assertSame('Z', Keys::resolve('Z'));
    }
}
