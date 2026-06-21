<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * Converts a BiDi "remote value" (the serialised result of `script.evaluate`)
 * into a plain PHP value where it maps cleanly. Primitives come back as
 * scalars/null; richer types (objects, arrays, nodes) are returned as their
 * structured BiDi shape rather than guessing at a lossy conversion.
 */
final class RemoteValue
{
    public static function toPhp(mixed $value): mixed
    {
        if (! is_array($value)) {
            return null;
        }

        return match ($value['type'] ?? null) {
            'string', 'boolean', 'number', 'bigint' => $value['value'] ?? null,
            'null', 'undefined' => null,
            default => $value['value'] ?? $value,
        };
    }
}
