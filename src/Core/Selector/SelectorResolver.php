<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * Resolves a human target to a single element by trying each {@see Locator}
 * from {@see SelectorStrategy} in order and returning the first match —
 * preferring a form control over its `<label>` — or throwing
 * {@see ElementNotFoundException} with the full attempt list.
 */
final readonly class SelectorResolver
{
    /** @var list<string> */
    private array $testAttributes;

    /**
     * @param  list<string>|null  $testAttributes
     */
    public function __construct(
        private NodeLocator $nodeLocator,
        private SelectorStrategy $strategy = new SelectorStrategy,
        ?array $testAttributes = null,
    ) {
        $this->testAttributes = $testAttributes ?? ['data-testid', 'data-test', 'data-cy'];
    }

    public function resolve(string $target): ElementReference
    {
        $attempts = [];
        foreach ($this->strategy->candidates($target, $this->testAttributes) as $candidate) {
            $matches = $this->nodeLocator->locateAll($candidate);
            $attempts[] = new ResolutionAttempt($candidate->description, count($matches));

            $picked = $this->preferControl($matches);
            if ($picked instanceof ElementReference) {
                return $picked;
            }
        }

        throw new ElementNotFoundException($target, $attempts);
    }

    /**
     * @param  list<ElementReference>  $matches
     */
    private function preferControl(array $matches): ?ElementReference
    {
        foreach ($matches as $match) {
            if ($match->localName !== 'label') {
                return $match;
            }
        }

        return $matches[0] ?? null;
    }
}
