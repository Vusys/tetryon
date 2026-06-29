<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * An optional capability a {@see NodeLocator} may also implement: report whether
 * an element is visible and the top-most hit at its own centre point. The
 * {@see SelectorResolver} uses it only to break ties between several elements
 * that match the same target — preferring a visible, clickable match over an
 * occluded or hidden duplicate (e.g. a widget that renders an option's label in
 * both a visible node and an off-screen measurement node). When the locator does
 * not implement this, resolution keeps its first-match-in-DOM-order behaviour.
 */
interface HitTestProbe
{
    /**
     * True when the element is rendered (not `display:none` / `visibility:hidden`),
     * has a non-zero box, lies within the viewport, and is the top-most element at
     * its own centre (or an ancestor/descendant of it). Used only to break ties
     * between several matches — and the resolver only consults it when more than
     * one element matched — so a single legitimately off-screen target is never
     * subjected to this check.
     */
    public function isHitTestable(ElementReference $element): bool;
}
