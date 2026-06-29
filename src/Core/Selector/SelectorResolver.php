<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * Resolves a human target to a single element by trying each {@see Locator}
 * from {@see SelectorStrategy} in order and returning the first match —
 * preferring a form control over its `<label>`, and (when the locator can
 * report it) a visible, hit-testable match over an occluded or hidden duplicate
 * (#101) — or throwing {@see ElementNotFoundException} with the full attempt list.
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

            $picked = $this->preferHitTestable($this->controlsFirst($matches));
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

            $interactive = array_values(array_filter(
                $matches,
                fn (ElementReference $match): bool => in_array($match->localName, self::INTERACTIVE_TAGS, true),
            ));
            if ($interactive !== []) {
                return $this->preferHitTestable($interactive) ?? $interactive[0];
            }

            $fallback ??= $this->preferHitTestable($this->controlsFirst($matches));
        }

        return $fallback ?? throw new ElementNotFoundException($target, $attempts);
    }

    /**
     * Order matches so a non-`<label>` control comes before its `<label>` (#72),
     * preserving DOM order within each group.
     *
     * @param  list<ElementReference>  $matches
     * @return list<ElementReference>
     */
    private function controlsFirst(array $matches): array
    {
        $controls = array_filter($matches, fn (ElementReference $m): bool => $m->localName !== 'label');
        $labels = array_filter($matches, fn (ElementReference $m): bool => $m->localName === 'label');

        return [...$controls, ...$labels];
    }

    /**
     * From candidates already in preference order, return the first that is
     * visible and hit-testable, falling back to the first candidate. Only probes
     * when more than one element matched and the locator can answer — so a single
     * match (including a legitimately off-screen target) is returned untouched
     * with no extra round-trip.
     *
     * @param  list<ElementReference>  $ordered
     */
    private function preferHitTestable(array $ordered): ?ElementReference
    {
        if (count($ordered) <= 1 || ! $this->nodeLocator instanceof HitTestProbe) {
            return $ordered[0] ?? null;
        }

        foreach ($ordered as $candidate) {
            if ($this->nodeLocator->isHitTestable($candidate)) {
                return $candidate;
            }
        }

        return $ordered[0];
    }
}
