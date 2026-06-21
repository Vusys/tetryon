<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\NaturalLanguage;

/**
 * Parses a small, fixed grammar of step sentences into a {@see Step}. Pure and
 * deterministic — no AI, no `.feature` files — so the grammar is fully
 * unit-testable. Quoted values are the arguments.
 *
 * @phpstan-type Rule array{0: string, 1: string, 2: list<int>}
 */
final class StepParser
{
    /**
     * Each rule is [pattern, action, capture-group order]. Order matters only
     * where a more specific sentence must be tried before a broader one.
     *
     * @var list<Rule>
     */
    private const array RULES = [
        ['/^I (?:am on|visit|go to|open) "([^"]*)"$/i', 'visit', [1]],
        ['/^I fill "([^"]*)" with "([^"]*)"$/i', 'fill', [1, 2]],
        ['/^I type "([^"]*)" into "([^"]*)"$/i', 'type', [2, 1]],
        ['/^I clear "([^"]*)"$/i', 'clear', [1]],
        ['/^I press "([^"]*)"$/i', 'press', [1]],
        ['/^I click "([^"]*)"$/i', 'click', [1]],
        ['/^I check "([^"]*)"$/i', 'check', [1]],
        ['/^I uncheck "([^"]*)"$/i', 'uncheck', [1]],
        ['/^I select "([^"]*)" from "([^"]*)"$/i', 'select', [2, 1]],
        ['/^I press the "([^"]*)" key$/i', 'pressKey', [1]],
        ['/^I should not see "([^"]*)"$/i', 'assertDontSee', [1]],
        ['/^I should see "([^"]*)"$/i', 'assertSee', [1]],
        ['/^I should be on "([^"]*)"$/i', 'assertPathIs', [1]],
        ['/^the title should be "([^"]*)"$/i', 'assertTitleIs', [1]],
    ];

    public static function parse(string $step): Step
    {
        $sentence = trim($step);

        foreach (self::RULES as [$pattern, $action, $order]) {
            if (preg_match($pattern, $sentence, $matches) === 1) {
                $arguments = array_map(
                    static fn (int $group): string => $matches[$group] ?? '',
                    $order,
                );

                return new Step($action, $arguments);
            }
        }

        throw UnknownStepException::for($sentence);
    }
}
