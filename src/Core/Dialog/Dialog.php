<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Dialog;

/**
 * A native browser dialog the page opened — `window.alert`, `window.confirm`,
 * `window.prompt`, or a `beforeunload` guard — as the test sees it.
 */
final readonly class Dialog
{
    public function __construct(
        public DialogType $type,
        public string $message,
        public string $defaultValue = '',
    ) {}

    public function describe(): string
    {
        return $this->message === ''
            ? $this->type->value
            : sprintf('%s: "%s"', $this->type->value, $this->message);
    }
}
