<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * Converts a BiDi "remote value" (the serialised result of `script.evaluate`)
 * into a plain PHP value where it maps cleanly. Primitives come back as
 * scalars/null, arrays and sets as lists, and plain objects and maps as
 * arrays keyed by their (string or number) keys — recursively. Anything that
 * doesn't map cleanly (a node, a window, a map keyed by objects, a value the
 * browser didn't serialise in full) is returned as its structured BiDi shape
 * rather than guessed at.
 */
final class RemoteValue
{
    public static function toPhp(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        $inner = $value['value'] ?? null;

        return match ($value['type'] ?? null) {
            'string', 'boolean', 'number', 'bigint' => $inner,
            'null', 'undefined' => null,
            'array', 'set' => is_array($inner) ? array_map(self::toPhp(...), array_values($inner)) : $value,
            'object', 'map' => is_array($inner) ? (self::entries($inner) ?? $value) : $value,
            default => $inner ?? $value,
        };
    }

    /**
     * An object's or map's [key, value] pairs as a keyed array, or null when a
     * key isn't a string or number and so has no PHP array equivalent.
     *
     * @param  array<mixed>  $pairs
     * @return array<array-key, mixed>|null
     */
    private static function entries(array $pairs): ?array
    {
        $entries = [];
        foreach ($pairs as $pair) {
            if (! is_array($pair) || ! array_key_exists(0, $pair) || ! array_key_exists(1, $pair)) {
                return null;
            }

            $key = is_string($pair[0]) ? $pair[0] : self::toPhp($pair[0]);
            if (! is_string($key) && ! is_int($key)) {
                return null;
            }

            $entries[$key] = self::toPhp($pair[1]);
        }

        return $entries;
    }
}
