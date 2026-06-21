<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * Pure builders for `input.performActions` source sequences. Kept separate
 * from the driver so the (fiddly) action shapes are unit-testable without a
 * browser.
 */
final class InputActions
{
    /**
     * A pointer sequence that moves to an element's centre and clicks it.
     *
     * @return array{type: string, id: string, actions: list<array<string, mixed>>}
     */
    public static function clickElement(string $sharedId): array
    {
        return [
            'type' => 'pointer',
            'id' => 'mouse',
            'actions' => [
                [
                    'type' => 'pointerMove',
                    'x' => 0,
                    'y' => 0,
                    'origin' => ['type' => 'element', 'element' => ['sharedId' => $sharedId]],
                ],
                ['type' => 'pointerDown', 'button' => 0],
                ['type' => 'pointerUp', 'button' => 0],
            ],
        ];
    }

    /**
     * A key sequence that types text one character at a time (keyDown+keyUp),
     * so real input events fire on the focused element.
     *
     * @return array{type: string, id: string, actions: list<array<string, mixed>>}
     */
    public static function typeText(string $text): array
    {
        $actions = [];
        foreach (mb_str_split($text) as $character) {
            $actions[] = ['type' => 'keyDown', 'value' => $character];
            $actions[] = ['type' => 'keyUp', 'value' => $character];
        }

        return ['type' => 'key', 'id' => 'keyboard', 'actions' => $actions];
    }
}
