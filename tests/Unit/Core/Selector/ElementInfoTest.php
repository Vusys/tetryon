<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Selector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Selector\ElementInfo;

#[CoversClass(ElementInfo::class)]
final class ElementInfoTest extends TestCase
{
    public function test_it_parses_the_element_descriptor_json(): void
    {
        $info = ElementInfo::fromJson('{"tag":"input","type":"checkbox","editable":false}');

        self::assertSame('input', $info->tag);
        self::assertSame('checkbox', $info->type);
        self::assertFalse($info->editable);
    }

    public function test_it_falls_back_to_empty_on_malformed_json(): void
    {
        $info = ElementInfo::fromJson('not json');

        self::assertSame('', $info->tag);
        self::assertSame('', $info->type);
        self::assertFalse($info->editable);
    }

    public function test_describe_names_an_input_by_type(): void
    {
        self::assertSame('input type="text"', new ElementInfo('input', 'text', false)->describe());
    }

    public function test_describe_flags_a_contenteditable_element(): void
    {
        self::assertSame('div contenteditable', new ElementInfo('div', '', true)->describe());
    }

    public function test_describe_falls_back_to_the_tag(): void
    {
        self::assertSame('select', new ElementInfo('select', 'select-one', false)->describe());
    }
}
