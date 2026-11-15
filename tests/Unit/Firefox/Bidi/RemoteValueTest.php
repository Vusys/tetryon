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

    public function test_it_unwraps_arrays_and_plain_objects_recursively(): void
    {
        $remote = ['type' => 'array', 'value' => [
            ['type' => 'number', 'value' => 800],
            ['type' => 'object', 'value' => [
                ['name', ['type' => 'string', 'value' => 'Ada']],
                ['tags', ['type' => 'array', 'value' => [['type' => 'string', 'value' => 'lead']]]],
                ['manager', ['type' => 'null']],
            ]],
        ]];

        self::assertSame([800, ['name' => 'Ada', 'tags' => ['lead'], 'manager' => null]], RemoteValue::toPhp($remote));
    }

    public function test_it_keys_a_map_by_its_primitive_keys(): void
    {
        $map = ['type' => 'map', 'value' => [[['type' => 'number', 'value' => 1], ['type' => 'string', 'value' => 'one']]]];

        self::assertSame([1 => 'one'], RemoteValue::toPhp($map));
    }

    public function test_it_keeps_the_shape_of_a_map_keyed_by_objects(): void
    {
        $map = ['type' => 'map', 'value' => [[['type' => 'object', 'value' => []], ['type' => 'string', 'value' => 'x']]]];

        self::assertSame($map, RemoteValue::toPhp($map));
    }

    public function test_it_keeps_the_shape_of_a_value_serialised_without_its_contents(): void
    {
        $deep = ['type' => 'object', 'handle' => 'h1'];

        self::assertSame($deep, RemoteValue::toPhp($deep));
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
