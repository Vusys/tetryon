<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\NaturalLanguage;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\NaturalLanguage\Step;
use Vusys\Tetryon\Core\NaturalLanguage\StepParser;
use Vusys\Tetryon\Core\NaturalLanguage\UnknownStepException;

#[CoversClass(StepParser::class)]
#[CoversClass(Step::class)]
#[CoversClass(UnknownStepException::class)]
final class StepParserTest extends TestCase
{
    public function test_it_parses_navigation(): void
    {
        $step = StepParser::parse('I am on "/login"');

        self::assertSame('visit', $step->action);
        self::assertSame(['/login'], $step->arguments);
    }

    public function test_it_parses_fill_with_two_arguments(): void
    {
        $step = StepParser::parse('I fill "Email" with "bryan@example.com"');

        self::assertSame('fill', $step->action);
        self::assertSame(['Email', 'bryan@example.com'], $step->arguments);
    }

    public function test_type_maps_value_into_field_order(): void
    {
        $step = StepParser::parse('I type "tetryon" into "Search"');

        self::assertSame('type', $step->action);
        self::assertSame(['Search', 'tetryon'], $step->arguments);
    }

    public function test_select_maps_value_from_field_order(): void
    {
        $step = StepParser::parse('I select "uk" from "Country"');

        self::assertSame('select', $step->action);
        self::assertSame(['Country', 'uk'], $step->arguments);
    }

    public function test_should_not_see_is_distinct_from_should_see(): void
    {
        self::assertSame('assertDontSee', StepParser::parse('I should not see "Error"')->action);
        self::assertSame('assertSee', StepParser::parse('I should see "Dashboard"')->action);
    }

    public function test_parsing_is_case_insensitive_and_trims(): void
    {
        self::assertSame('press', StepParser::parse('  i PRESS "Save"  ')->action);
    }

    public function test_an_unknown_step_throws(): void
    {
        $this->expectException(UnknownStepException::class);
        $this->expectExceptionMessage('could not understand');

        StepParser::parse('I do a backflip');
    }
}
