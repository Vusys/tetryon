<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\TemporaryProfile;

#[CoversClass(TemporaryProfile::class)]
final class TemporaryProfileTest extends TestCase
{
    public function test_create_makes_a_unique_directory(): void
    {
        $first = TemporaryProfile::create();
        $second = TemporaryProfile::create();

        try {
            self::assertDirectoryExists($first->path);
            self::assertStringContainsString('tetryon-ff-', $first->path);
            self::assertNotSame($first->path, $second->path);
        } finally {
            $first->delete();
            $second->delete();
        }
    }

    public function test_delete_removes_the_tree_recursively(): void
    {
        $profile = TemporaryProfile::create();
        self::assertTrue(mkdir($profile->path.'/sub'));
        file_put_contents($profile->path.'/sub/prefs.js', 'x');

        $profile->delete();

        self::assertDirectoryDoesNotExist($profile->path);
    }

    public function test_delete_is_idempotent(): void
    {
        $profile = TemporaryProfile::create();

        $profile->delete();
        $profile->delete();

        self::assertDirectoryDoesNotExist($profile->path);
    }
}
