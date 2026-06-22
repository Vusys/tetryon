<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\InputActions;

#[CoversClass(InputActions::class)]
final class InputActionsTest extends TestCase
{
    public function test_click_builds_a_pointer_sequence_targeting_the_element(): void
    {
        $source = InputActions::clickElement('abc-123');

        self::assertSame('pointer', $source['type']);
        self::assertSame('mouse', $source['id']);

        $actions = $source['actions'];
        self::assertSame('pointerMove', $actions[0]['type']);
        self::assertSame(
            ['type' => 'element', 'element' => ['sharedId' => 'abc-123']],
            $actions[0]['origin'],
        );
        self::assertSame('pointerDown', $actions[1]['type']);
        self::assertSame('pointerUp', $actions[2]['type']);
    }

    public function test_type_text_emits_keydown_and_keyup_per_character(): void
    {
        $source = InputActions::typeText('hi');

        self::assertSame('key', $source['type']);
        self::assertSame([
            ['type' => 'keyDown', 'value' => 'h'],
            ['type' => 'keyUp', 'value' => 'h'],
            ['type' => 'keyDown', 'value' => 'i'],
            ['type' => 'keyUp', 'value' => 'i'],
        ], $source['actions']);
    }

    public function test_type_text_is_multibyte_aware(): void
    {
        $source = InputActions::typeText('é');

        self::assertCount(2, $source['actions']);
        self::assertSame('é', $source['actions'][0]['value']);
    }

    public function test_type_text_with_an_empty_string_emits_no_actions(): void
    {
        self::assertSame([], InputActions::typeText('')['actions']);
    }

    public function test_double_click_issues_two_press_release_pairs(): void
    {
        $actions = InputActions::doubleClickElement('abc')['actions'];

        $types = array_map(static fn (array $action): mixed => $action['type'], $actions);
        self::assertSame(
            ['pointerMove', 'pointerDown', 'pointerUp', 'pointerDown', 'pointerUp'],
            $types,
        );
    }

    public function test_right_click_uses_the_secondary_button(): void
    {
        $actions = InputActions::contextClickElement('abc')['actions'];

        self::assertSame('pointerDown', $actions[1]['type']);
        self::assertSame(2, $actions[1]['button']);
        self::assertSame(2, $actions[2]['button']);
    }

    public function test_hover_only_moves(): void
    {
        $actions = InputActions::hoverElement('abc')['actions'];

        self::assertCount(1, $actions);
        self::assertSame('pointerMove', $actions[0]['type']);
    }

    public function test_pointer_drag_presses_moves_through_the_path_and_releases(): void
    {
        $source = InputActions::pointerDrag([
            ['x' => 10, 'y' => 20],
            ['x' => 15, 'y' => 25],
            ['x' => 30, 'y' => 40],
        ]);

        self::assertSame('pointer', $source['type']);

        $actions = $source['actions'];
        $types = array_map(static fn (array $action): mixed => $action['type'], $actions);
        self::assertSame(['pointerMove', 'pointerDown', 'pointerMove', 'pointerMove', 'pointerUp'], $types);

        self::assertSame(['type' => 'pointerMove', 'x' => 10, 'y' => 20, 'origin' => 'viewport'], $actions[0]);
        self::assertSame(0, $actions[1]['button']);
        self::assertSame(['type' => 'pointerMove', 'x' => 30, 'y' => 40, 'origin' => 'viewport'], $actions[3]);
        self::assertSame('pointerUp', $actions[4]['type']);
    }

    public function test_press_keys_emits_keydown_and_keyup_per_key(): void
    {
        self::assertSame([
            ['type' => 'keyDown', 'value' => "\u{E007}"],
            ['type' => 'keyUp', 'value' => "\u{E007}"],
        ], InputActions::pressKeys(["\u{E007}"])['actions']);
    }
}
