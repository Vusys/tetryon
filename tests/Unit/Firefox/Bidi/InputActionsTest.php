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
}
