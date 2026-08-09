<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * One concrete way to look up a node — a BiDi locator (`css`, `xpath`, or
 * `accessibility`) plus a human description used in the failure report's
 * "selector attempts" list.
 *
 * `$pierce` is an optional JavaScript expression evaluating to a matcher
 * `(root) => Element[]`. The native BiDi locator only sees the light DOM; when it
 * finds nothing, the driver runs `$pierce` across every open shadow root so a
 * strategy can reach into web components (#151, #162). CSS locators pierce via a
 * generic `querySelectorAll` walk and need no `$pierce`; the text/label
 * strategies (XPath, which can't cross shadow boundaries) supply one.
 */
final readonly class Locator
{
    /**
     * @param  array{type: string, value: mixed}  $bidi  the BiDi `locator` payload
     * @param  string|null  $pierce  JS expression `(root) => Element[]` for shadow-piercing
     */
    public function __construct(
        public string $description,
        public array $bidi,
        public ?string $pierce = null,
    ) {}

    public static function css(string $description, string $selector): self
    {
        return new self($description, ['type' => 'css', 'value' => $selector]);
    }

    public static function xpath(string $description, string $expression, ?string $pierce = null): self
    {
        return new self($description, ['type' => 'xpath', 'value' => $expression], $pierce);
    }

    public static function accessibleName(string $name): self
    {
        return new self('accessible name', ['type' => 'accessibility', 'value' => ['name' => $name]]);
    }
}
