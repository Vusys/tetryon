<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * An optional capability a {@see NodeLocator} may also implement: report how
 * usable an element is right now. The {@see SelectorResolver} uses it only to
 * break ties between several elements that match the same target — preferring a
 * clickable match, then a merely rendered one, over an unrendered duplicate
 * (e.g. a widget that renders an option's label in both a visible node and an
 * off-screen measurement node). When the locator does not implement this,
 * resolution keeps its first-match-in-DOM-order behaviour.
 */
interface VisibilityProbe
{
    /**
     * Rank the element as {@see Visibility::Clickable}, {@see Visibility::Rendered}
     * or {@see Visibility::Hidden}. Used solely to break ties between several
     * matches — and the resolver only consults it when more than one element
     * matched — so a single legitimately off-screen target is never subjected to
     * this check.
     */
    public function visibility(ElementReference $element): Visibility;
}
