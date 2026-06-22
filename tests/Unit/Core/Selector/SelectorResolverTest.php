<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Selector;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Core\Selector\ElementReference;
use Vusys\Tetryon\Core\Selector\Locator;
use Vusys\Tetryon\Core\Selector\NodeLocator;
use Vusys\Tetryon\Core\Selector\ResolutionAttempt;
use Vusys\Tetryon\Core\Selector\SelectorResolver;

#[CoversClass(SelectorResolver::class)]
#[CoversClass(ElementNotFoundException::class)]
#[CoversClass(ResolutionAttempt::class)]
final class SelectorResolverTest extends TestCase
{
    public function test_it_returns_the_first_matching_strategy(): void
    {
        $locator = $this->matchingOn('placeholder', [new ElementReference('node-1')]);

        self::assertSame('node-1', new SelectorResolver($locator)->resolve('Email')->sharedId);
    }

    public function test_it_prefers_a_control_over_its_label(): void
    {
        $locator = $this->matchingOn('label', [
            new ElementReference('the-label', 'label'),
            new ElementReference('the-input', 'input'),
        ]);

        self::assertSame('the-input', new SelectorResolver($locator)->resolve('Email')->sharedId);
    }

    public function test_it_throws_with_the_full_attempt_list_when_nothing_matches(): void
    {
        $locator = $this->matchingOn('never', []);

        try {
            new SelectorResolver($locator)->resolve('Nonexistent');
            self::fail('Expected an ElementNotFoundException.');
        } catch (ElementNotFoundException $e) {
            self::assertSame('Nonexistent', $e->target);
            self::assertNotEmpty($e->attempts);
            self::assertStringContainsString('Selector attempts:', $e->getMessage());
        }
    }

    public function test_interactive_prefers_an_interactive_match_within_one_strategy(): void
    {
        // Accessible-name matches both the heading and the button sharing "Log in";
        // the button must win even though the heading comes first in DOM order.
        $locator = $this->matchingOn('accessible name', [
            new ElementReference('the-heading', 'h1'),
            new ElementReference('the-button', 'button'),
        ]);

        self::assertSame('the-button', new SelectorResolver($locator)->resolveInteractive('Log in')->sharedId);
    }

    public function test_interactive_prefers_a_later_strategys_interactive_match(): void
    {
        // Heading matched by accessible-name (higher priority), button matched by
        // button-text (lower priority) — the interactive button still wins.
        $locator = $this->matchingByStrategy([
            'accessible name' => [new ElementReference('the-heading', 'h1')],
            'button text' => [new ElementReference('the-button', 'button')],
        ]);

        self::assertSame('the-button', new SelectorResolver($locator)->resolveInteractive('Log in')->sharedId);
    }

    public function test_interactive_falls_back_to_first_match_when_nothing_is_interactive(): void
    {
        $locator = $this->matchingOn('visible text', [new ElementReference('the-div', 'div')]);

        self::assertSame('the-div', new SelectorResolver($locator)->resolveInteractive('Right click me')->sharedId);
    }

    public function test_interactive_throws_when_nothing_matches(): void
    {
        $this->expectException(ElementNotFoundException::class);

        new SelectorResolver($this->matchingOn('never', []))->resolveInteractive('Nope');
    }

    public function test_within_passes_the_root_to_the_locator(): void
    {
        $recorder = new class implements NodeLocator
        {
            public ?ElementReference $within = null;

            public function locateAll(Locator $locator, ?ElementReference $within = null): array
            {
                $this->within = $within;

                return [new ElementReference('child')];
            }
        };
        $root = new ElementReference('container');

        $element = new SelectorResolver($recorder)->within($root)->resolve('anything');

        self::assertSame('child', $element->sharedId);
        self::assertSame($root, $recorder->within);
    }

    /**
     * A NodeLocator that maps each strategy description to the nodes it returns.
     *
     * @param  array<string, list<ElementReference>>  $byStrategy
     */
    private function matchingByStrategy(array $byStrategy): NodeLocator
    {
        return new readonly class($byStrategy) implements NodeLocator
        {
            /**
             * @param  array<string, list<ElementReference>>  $byStrategy
             */
            public function __construct(private array $byStrategy) {}

            public function locateAll(Locator $locator, ?ElementReference $within = null): array
            {
                return $this->byStrategy[$locator->description] ?? [];
            }
        };
    }

    /**
     * A NodeLocator that returns the given nodes only for the named strategy.
     *
     * @param  list<ElementReference>  $nodes
     */
    private function matchingOn(string $description, array $nodes): NodeLocator
    {
        return new readonly class($description, $nodes) implements NodeLocator
        {
            /**
             * @param  list<ElementReference>  $nodes
             */
            public function __construct(
                private string $description,
                private array $nodes,
            ) {}

            public function locateAll(Locator $locator, ?ElementReference $within = null): array
            {
                return $locator->description === $this->description ? $this->nodes : [];
            }
        };
    }
}
