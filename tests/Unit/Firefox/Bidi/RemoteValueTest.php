<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\RemoteValue;

#[CoversClass(RemoteValue::class)]
final class RemoteValueTest extends TestCase
{
    public function test_it_unwraps_primitive_values(): void
    {
        self::assertSame('hello', RemoteValue::toPhp(['type' => 'string', 'value' => 'hello']));
        self::assertSame(42, RemoteValue::toPhp(['type' => 'number', 'value' => 42]));
        self::assertTrue(RemoteValue::toPhp(['type' => 'boolean', 'value' => true]));
    }

    public function test_it_maps_null_and_undefined_to_null(): void
    {
        self::assertNull(RemoteValue::toPhp(['type' => 'null']));
        self::assertNull(RemoteValue::toPhp(['type' => 'undefined']));
    }

    public function test_it_returns_structured_shape_for_complex_types(): void
    {
        $node = ['type' => 'node', 'sharedId' => 'abc'];

        self::assertSame($node, RemoteValue::toPhp($node));
    }

    public function test_it_returns_null_for_a_non_array(): void
    {
        self::assertNull(RemoteValue::toPhp('not a remote value'));
    }
}
