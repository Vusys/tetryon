<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

use RuntimeException;

/**
 * A form verb resolved an element it cannot drive — e.g. fill() landing on a
 * plain `<div>`, or select() on a custom dropdown that isn't a native
 * `<select>`. Thrown instead of silently no-opping, so the failure points at the
 * real cause rather than surfacing later at an unrelated assertion.
 */
final class UndrivableElementException extends RuntimeException
{
    /**
     * @param  'value'|'select'|'checkable'  $kind  the control the verb needs
     */
    public static function for(string $verb, string $target, string $resolved, string $kind): self
    {
        $needs = match ($kind) {
            'value' => 'has no text to set — fill()/type()/clear() need an <input>, <textarea>,'
                .' or contenteditable element; drive a custom widget with click()/type() or evaluate()',
            'select' => 'is not a <select> — open a custom dropdown with click() and click the option',
            'checkable' => 'is not a checkbox or radio — check()/uncheck() need an <input type="checkbox">',
        };

        return new self(sprintf('%s("%s") resolved a <%s>, which %s.', $verb, $target, $resolved, $needs));
    }

    /**
     * A picker input (date, time, colour…) sanitised the value to something
     * else — usually a format it doesn't take, like a localised date.
     */
    public static function rejectedValue(string $verb, string $target, string $type, string $value): self
    {
        return new self(sprintf(
            '%s("%s", "%s") was rejected by an <input type="%s">, which takes its value in the format the HTML spec gives it (e.g. 2025-03-01 for a date, 14:30 for a time, #ff8800 for a colour).',
            $verb, $target, $value, $type,
        ));
    }
}
