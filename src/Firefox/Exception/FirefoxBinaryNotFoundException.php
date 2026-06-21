<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Exception;

/**
 * Thrown when no Firefox executable can be located.
 */
final class FirefoxBinaryNotFoundException extends FirefoxException
{
    /**
     * @param  list<string>  $triedPaths
     */
    public static function afterTrying(array $triedPaths): self
    {
        $tried = $triedPaths === []
            ? '(no candidate paths)'
            : implode(', ', $triedPaths);

        return new self(
            "Could not locate a Firefox executable. Tried: {$tried}. "
            .'Set TETRYON_FIREFOX_BINARY to the absolute path of your Firefox binary.',
        );
    }
}
