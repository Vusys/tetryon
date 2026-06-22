<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

/**
 * Pure builders for `input.performActions` source sequences. Kept separate
 * from the driver so the (fiddly) action shapes are unit-testable without a
 * browser.
 *
 * @phpstan-type Source array{type: string, id: string, actions: list<array<string, mixed>>}
 */
final class InputActions
{
    /**
     * A pointer sequence that moves to an element's centre and clicks it.
     *
     * @return Source
     */
    public static function clickElement(string $sharedId): array
    {
        return self::pointer([self::move($sharedId), self::down(0), self::up(0)]);
    }

    /**
     * @return Source
     */
    public static function doubleClickElement(string $sharedId): array
    {
        return self::pointer([self::move($sharedId), self::down(0), self::up(0), self::down(0), self::up(0)]);
    }

    /**
     * @return Source
     */
    public static function contextClickElement(string $sharedId): array
    {
        return self::pointer([self::move($sharedId), self::down(2), self::up(2)]);
    }

    /**
     * @return Source
     */
    public static function hoverElement(string $sharedId): array
    {
        return self::pointer([self::move($sharedId)]);
    }

    /**
     * A pointer press-drag-release along an absolute (viewport) path: move to the
     * first point, press, move through the remaining points, release. Pointer-drag
     * libraries (Sortable.js, vuedraggable, …) need the intermediate moves to
     * cross their drag threshold and compute direction, so the path carries steps
     * rather than a single jump.
     *
     * @param  list<array{x: int, y: int}>  $path  start point, then one or more move targets
     * @return Source
     */
    public static function pointerDrag(array $path, int $button = 0): array
    {
        $start = $path[0] ?? ['x' => 0, 'y' => 0];
        $actions = [self::moveTo($start['x'], $start['y']), self::down($button)];

        foreach (array_slice($path, 1) as $point) {
            $actions[] = self::moveTo($point['x'], $point['y']);
        }

        $actions[] = self::up($button);

        return self::pointer($actions);
    }

    /**
     * Type text one character at a time (keyDown+keyUp), so real input events
     * fire on the focused element.
     *
     * @return Source
     */
    public static function typeText(string $text): array
    {
        return self::key(mb_str_split($text));
    }

    /**
     * Press each key in turn (keyDown+keyUp). Values are resolved key tokens
     * (see {@see Keys}).
     *
     * @param  list<string>  $keys
     * @return Source
     */
    public static function pressKeys(array $keys): array
    {
        return self::key($keys);
    }

    /**
     * @return array{type: string, x: int, y: int, origin: array{type: string, element: array{sharedId: string}}}
     */
    private static function move(string $sharedId): array
    {
        return [
            'type' => 'pointerMove',
            'x' => 0,
            'y' => 0,
            'origin' => ['type' => 'element', 'element' => ['sharedId' => $sharedId]],
        ];
    }

    /**
     * @return array{type: string, x: int, y: int, origin: string}
     */
    private static function moveTo(int $x, int $y): array
    {
        return ['type' => 'pointerMove', 'x' => $x, 'y' => $y, 'origin' => 'viewport'];
    }

    /**
     * @return array{type: string, button: int}
     */
    private static function down(int $button): array
    {
        return ['type' => 'pointerDown', 'button' => $button];
    }

    /**
     * @return array{type: string, button: int}
     */
    private static function up(int $button): array
    {
        return ['type' => 'pointerUp', 'button' => $button];
    }

    /**
     * @param  list<array<string, mixed>>  $actions
     * @return Source
     */
    private static function pointer(array $actions): array
    {
        return ['type' => 'pointer', 'id' => 'mouse', 'actions' => $actions];
    }

    /**
     * @param  list<string>  $values
     * @return Source
     */
    private static function key(array $values): array
    {
        $actions = [];
        foreach ($values as $value) {
            $actions[] = ['type' => 'keyDown', 'value' => $value];
            $actions[] = ['type' => 'keyUp', 'value' => $value];
        }

        return ['type' => 'key', 'id' => 'keyboard', 'actions' => $actions];
    }
}
