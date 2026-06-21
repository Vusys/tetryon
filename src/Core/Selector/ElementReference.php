<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * A handle to a located DOM node. The `sharedId` is the driver's stable
 * reference for the node within its browsing context; `localName` (when known)
 * is the tag name, used to prefer a form control over its `<label>`.
 */
final readonly class ElementReference
{
    public function __construct(
        public string $sharedId,
        public ?string $localName = null,
    ) {}
}
