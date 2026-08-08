<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Dialog;

/**
 * The kind of native dialog the page opened.
 */
enum DialogType: string
{
    case Alert = 'alert';
    case Confirm = 'confirm';
    case Prompt = 'prompt';
    case BeforeUnload = 'beforeunload';

    /**
     * Read the type a driver reported, falling back to `alert` — the least
     * capable kind — for anything unrecognised, so an unknown dialog is still
     * described rather than dropped.
     */
    public static function fromDriver(mixed $type): self
    {
        return is_string($type) ? self::tryFrom($type) ?? self::Alert : self::Alert;
    }
}
