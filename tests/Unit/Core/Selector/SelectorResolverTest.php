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

            public function locateAll(Locator $locator): array
            {
                return $locator->description === $this->description ? $this->nodes : [];
            }
        };
    }
}
