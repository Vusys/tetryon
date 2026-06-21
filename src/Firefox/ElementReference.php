<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

/**
 * A handle to a DOM node returned by a BiDi locate call. The `sharedId` is
 * Firefox's stable reference for the node within its browsing context; it is
 * used as the origin for input actions and as an argument to scripts.
 */
final readonly class ElementReference
{
    public function __construct(public string $sharedId) {}
}
