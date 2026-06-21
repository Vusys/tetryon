<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\PHPUnit\FailureArtifacts;

#[CoversClass(FailureArtifacts::class)]
final class FailureArtifactsTest extends TestCase
{
    public function test_directory_for_sanitises_the_test_id(): void
    {
        self::assertSame(
            'artifacts/Vusys_Tetryon_Tests_LoginTest_test_guest_can_log_in',
            FailureArtifacts::directoryFor('artifacts', 'Vusys\\Tetryon\\Tests\\LoginTest::test_guest_can_log_in'),
        );
    }

    public function test_directory_for_trims_the_trailing_base_slash(): void
    {
        self::assertSame('out/FooTest_test_bar', FailureArtifacts::directoryFor('out/', 'FooTest::test_bar'));
    }

    public function test_directory_for_falls_back_when_the_id_is_all_separators(): void
    {
        self::assertSame('out/test', FailureArtifacts::directoryFor('out', '::'));
    }
}
