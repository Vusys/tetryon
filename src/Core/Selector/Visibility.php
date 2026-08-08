<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * How usable an element is *right now*, as a preference order for breaking ties
 * between several elements matching the same target (#101).
 *
 * `Clickable` is the strongest signal and `Hidden` the weakest, but the middle
 * case is the one that matters: an element can be perfectly real and perfectly
 * clickable a scroll away, so "not clickable at rest" must not rank it level
 * with a zero-size measurement node that can never be clicked at all.
 */
enum Visibility: string
{
    /** Not rendered: `display:none`, `visibility:hidden`, or a zero-size box. */
    case Hidden = 'hidden';

    /** Rendered with a real box, but not clickable where it currently sits — off-screen, or covered. */
    case Rendered = 'rendered';

    /** Rendered, in the viewport, and top-most at its own centre point. */
    case Clickable = 'clickable';

    public static function fromProbe(mixed $result): self
    {
        return is_string($result) ? self::tryFrom($result) ?? self::Hidden : self::Hidden;
    }
}
