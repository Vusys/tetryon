<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * Maps friendly key names ("Enter", "Tab", "ArrowDown") to the WebDriver
 * Unicode private-use codepoints Firefox expects in key input actions. Anything
 * not in the map (a literal character like "a") is sent as-is.
 */
final class Keys
{
    private const array MAP = [
        'Backspace' => "\u{E003}",
        'Tab' => "\u{E004}",
        'Enter' => "\u{E007}",
        'Return' => "\u{E006}",
        'Shift' => "\u{E008}",
        'Control' => "\u{E009}",
        'Ctrl' => "\u{E009}",
        'Alt' => "\u{E00A}",
        'Escape' => "\u{E00C}",
        'Esc' => "\u{E00C}",
        'Space' => "\u{E00D}",
        'PageUp' => "\u{E00E}",
        'PageDown' => "\u{E00F}",
        'End' => "\u{E010}",
        'Home' => "\u{E011}",
        'ArrowLeft' => "\u{E012}",
        'ArrowUp' => "\u{E013}",
        'ArrowRight' => "\u{E014}",
        'ArrowDown' => "\u{E015}",
        'Insert' => "\u{E016}",
        'Delete' => "\u{E017}",
        'Meta' => "\u{E03D}",
        'Command' => "\u{E03D}",
    ];

    public static function resolve(string $key): string
    {
        return self::MAP[$key] ?? $key;
    }
}
