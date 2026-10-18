<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Support;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Support\Slug;

#[CoversClass(Slug::class)]
final class SlugTest extends TestCase
{
    public function test_it_sanitises_the_id(): void
    {
        self::assertSame(
            'Vusys_Tetryon_Tests_LoginTest_test_guest_can_log_in',
            Slug::forTestId('Vusys\\Tetryon\\Tests\\LoginTest::test_guest_can_log_in'),
        );
    }

    public function test_it_trims_leading_and_trailing_separators(): void
    {
        self::assertSame('FooTest_test_bar', Slug::forTestId('::FooTest::test_bar::'));
    }

    public function test_it_falls_back_when_the_id_is_all_separators(): void
    {
        self::assertSame('test', Slug::forTestId('::'));
    }

    public function test_it_falls_back_when_the_id_is_empty(): void
    {
        self::assertSame('test', Slug::forTestId(''));
    }
}
