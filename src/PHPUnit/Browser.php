<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Assert;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;

/**
 * The fluent, user-facing browser API. Wraps the Firefox driver and turns its
 * primitives into the readable verbs and assertions a PHPUnit test calls.
 * Assertions delegate to PHPUnit so failures appear as normal test failures.
 */
final readonly class Browser
{
    public function __construct(
        private FirefoxBiDiDriver $driver,
        private Configuration $configuration,
    ) {}

    public function visit(string $pathOrUrl): self
    {
        $this->driver->navigate($this->configuration->resolveUrl($pathOrUrl));

        return $this;
    }

    public function refresh(): self
    {
        $this->driver->reload();

        return $this;
    }

    public function back(): self
    {
        $this->driver->traverseHistory(-1);

        return $this;
    }

    public function forward(): self
    {
        $this->driver->traverseHistory(1);

        return $this;
    }

    public function currentUrl(): string
    {
        return $this->driver->currentUrl();
    }

    public function currentPath(): string
    {
        $path = parse_url($this->driver->currentUrl(), PHP_URL_PATH);

        return is_string($path) ? $path : '';
    }

    public function title(): string
    {
        return $this->driver->title();
    }

    public function assertSee(string $text): self
    {
        Assert::assertStringContainsString(
            $text,
            $this->visibleText(),
            "Expected to see \"{$text}\" on the page.",
        );

        return $this;
    }

    public function assertDontSee(string $text): self
    {
        Assert::assertStringNotContainsString(
            $text,
            $this->visibleText(),
            "Did not expect to see \"{$text}\" on the page.",
        );

        return $this;
    }

    public function assertUrlIs(string $url): self
    {
        Assert::assertSame($this->configuration->resolveUrl($url), $this->currentUrl());

        return $this;
    }

    public function assertPathIs(string $path): self
    {
        Assert::assertSame($path, $this->currentPath());

        return $this;
    }

    public function assertTitleIs(string $title): self
    {
        Assert::assertSame($title, $this->title());

        return $this;
    }

    private function visibleText(): string
    {
        $text = $this->driver->evaluateScript('document.body ? document.body.innerText : ""');

        return is_string($text) ? $text : '';
    }
}
