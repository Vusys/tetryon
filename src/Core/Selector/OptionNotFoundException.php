<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

use RuntimeException;

/**
 * `select()` resolved a `<select>` but none of its `<option>`s matched the given
 * label or value. Thrown rather than silently setting `value` to a string that
 * matches no option (a no-op that surfaces later at an unrelated assertion).
 */
final class OptionNotFoundException extends RuntimeException
{
    public static function for(string $field, string $value, bool $byValueOnly): self
    {
        $by = $byValueOnly ? 'value' : 'label or value';

        return new self(sprintf('select("%s") has no <option> with %s "%s".', $field, $by, $value));
    }
}
