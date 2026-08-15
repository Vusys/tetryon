<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\PHPUnit\Recording;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\PHPUnit\Recording\ConcatScript;

#[CoversClass(ConcatScript::class)]
final class ConcatScriptTest extends TestCase
{
    public function test_it_pairs_each_frame_with_its_duration_in_seconds(): void
    {
        $script = ConcatScript::build(['a.png', 'b.png'], [1200, 800]);

        self::assertSame(
            "file 'a.png'\nduration 1.200\nfile 'b.png'\nduration 0.800\nfile 'b.png'\n",
            $script,
        );
    }

    public function test_it_repeats_the_last_frame_so_its_duration_is_not_dropped(): void
    {
        // The concat demuxer ignores the final entry's `duration` directive;
        // repeating the file is ffmpeg's own documented workaround.
        $script = ConcatScript::build(['only.png'], [500]);

        self::assertSame("file 'only.png'\nduration 0.500\nfile 'only.png'\n", $script);
    }

    public function test_it_escapes_single_quotes_in_frame_paths(): void
    {
        $script = ConcatScript::build(["it's.png"], [100]);

        self::assertStringContainsString("file 'it'\\''s.png'", $script);
    }

    public function test_it_rejects_an_empty_frame_list(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConcatScript::build([], []);
    }

    public function test_it_rejects_mismatched_frame_and_duration_counts(): void
    {
        $this->expectException(InvalidArgumentException::class);

        ConcatScript::build(['a.png', 'b.png'], [100]);
    }
}
