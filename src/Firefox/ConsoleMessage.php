<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

/**
 * A single browser console entry captured from a BiDi `log.entryAdded` event.
 */
final readonly class ConsoleMessage
{
    public function __construct(
        public string $level,
        public string $text,
        public string $source,
    ) {}

    /**
     * @param  mixed  $entry  the `params` of a log.entryAdded event
     */
    public static function fromLogEntry(mixed $entry): self
    {
        $entry = is_array($entry) ? $entry : [];

        $level = is_string($entry['level'] ?? null) ? $entry['level'] : 'info';
        $source = is_string($entry['method'] ?? null)
            ? $entry['method']
            : (is_string($entry['type'] ?? null) ? $entry['type'] : 'console');

        $text = is_string($entry['text'] ?? null) && $entry['text'] !== ''
            ? $entry['text']
            : self::renderArguments($entry['args'] ?? null);

        return new self($level, $text, $source);
    }

    private static function renderArguments(mixed $arguments): string
    {
        if (! is_array($arguments)) {
            return '';
        }

        $parts = [];
        foreach ($arguments as $argument) {
            if (! is_array($argument)) {
                continue;
            }
            if (! array_key_exists('value', $argument)) {
                continue;
            }
            $value = $argument['value'];
            if (is_scalar($value)) {
                $parts[] = (string) $value;
            } else {
                $encoded = json_encode($value);
                $parts[] = $encoded === false ? '' : $encoded;
            }
        }

        return implode(' ', $parts);
    }
}
