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
        private ?ElementReference $root = null,
    ) {
        $this->testAttributes = $testAttributes ?? ['data-testid', 'data-test', 'data-cy'];
    }

    /**
     * A copy of this resolver scoped to the descendants of the given element.
     */
    public function within(ElementReference $root): self
    {
        return new self($this->nodeLocator, $this->strategy, $this->testAttributes, $root);
    }

    /** Tags an action verb can meaningfully click. */
    private const array INTERACTIVE_TAGS = ['button', 'a', 'input', 'select', 'textarea', 'option', 'summary'];

    public function resolve(string $target): ElementReference
    {
        $attempts = [];
        foreach ($this->strategy->candidates($target, $this->testAttributes) as $candidate) {
            $matches = $this->nodeLocator->locateAll($candidate, $this->root);
            $attempts[] = new ResolutionAttempt($candidate->description, count($matches));

            $picked = $this->preferControl($matches);
            if ($picked instanceof ElementReference) {
                return $picked;
            }
        }

        throw new ElementNotFoundException($target, $attempts);
    }

    /**
     * Resolve for an action verb (click/press), preferring an interactive
     * element even when a higher-priority strategy matched a non-interactive
     * node — so pressing a button is not shadowed by a heading sharing its text
     * (#72). Falls back to the first match when nothing interactive is found, so
     * a clickable non-native element (a `<div>` with a handler) still resolves.
     */
    public function resolveInteractive(string $target): ElementReference
    {
        $attempts = [];
        $fallback = null;
        foreach ($this->strategy->candidates($target, $this->testAttributes) as $candidate) {
            $matches = $this->nodeLocator->locateAll($candidate, $this->root);
            $attempts[] = new ResolutionAttempt($candidate->description, count($matches));

            foreach ($matches as $match) {
                if (in_array($match->localName, self::INTERACTIVE_TAGS, true)) {
                    return $match;
                }
            }

            $fallback ??= $this->preferControl($matches);
        }

        return $fallback ?? throw new ElementNotFoundException($target, $attempts);
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
