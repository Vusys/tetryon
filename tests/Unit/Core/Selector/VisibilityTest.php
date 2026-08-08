<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Selector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Selector\Visibility;

#[CoversClass(Visibility::class)]
final class VisibilityTest extends TestCase
{
    public function test_it_reads_each_probe_result(): void
    {
        self::assertSame(Visibility::Clickable, Visibility::fromProbe('clickable'));
        self::assertSame(Visibility::Rendered, Visibility::fromProbe('rendered'));
        self::assertSame(Visibility::Hidden, Visibility::fromProbe('hidden'));
    }

    public function test_an_unusable_probe_result_reads_as_hidden(): void
    {
        // A probe that threw, returned nothing, or answered with something the
        // enum doesn't know must never be treated as a preferred match.
        self::assertSame(Visibility::Hidden, Visibility::fromProbe(null));
        self::assertSame(Visibility::Hidden, Visibility::fromProbe(true));
        self::assertSame(Visibility::Hidden, Visibility::fromProbe('nonsense'));
    }
}
