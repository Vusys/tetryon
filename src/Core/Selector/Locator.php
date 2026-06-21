<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * One concrete way to look up a node — a BiDi locator (`css`, `xpath`, or
 * `accessibility`) plus a human description used in the failure report's
 * "selector attempts" list.
 */
final readonly class Locator
{
    /**
     * @param  array{type: string, value: mixed}  $bidi  the BiDi `locator` payload
     */
    public function __construct(
        public string $description,
        public array $bidi,
    ) {}

    public static function css(string $description, string $selector): self
    {
        return new self($description, ['type' => 'css', 'value' => $selector]);
    }

    public static function xpath(string $description, string $expression): self
    {
        return new self($description, ['type' => 'xpath', 'value' => $expression]);
    }

    public static function accessibleName(string $name): self
    {
        return new self('accessible name', ['type' => 'accessibility', 'value' => ['name' => $name]]);
    }
}
