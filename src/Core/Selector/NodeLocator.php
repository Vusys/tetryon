<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * The narrow capability {@see SelectorResolver} needs from a driver: run one
 * locator and return every matching node. Implemented by the Firefox driver;
 * faked in tests so resolution logic runs without a browser.
 */
interface NodeLocator
{
    /**
     * Run one locator and return every matching node, optionally scoped to the
     * descendants of `$within`.
     *
     * @return list<ElementReference>
     */
    public function locateAll(Locator $locator, ?ElementReference $within = null): array;
}
