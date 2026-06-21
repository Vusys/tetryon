<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Assert;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Core\Selector\SelectorResolver;
use Vusys\Tetryon\Core\Selector\SelectorStrategy;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;

/**
 * The fluent, user-facing browser API. Wraps the Firefox driver and turns its
 * primitives into the readable verbs and assertions a PHPUnit test calls.
 * Assertions delegate to PHPUnit so failures appear as normal test failures.
 */
final readonly class Browser
{
    private SelectorResolver $resolver;

    public function __construct(
        private FirefoxBiDiDriver $driver,
        private Configuration $configuration,
    ) {
        $this->resolver = new SelectorResolver(
            $driver,
            new SelectorStrategy,
            $configuration->selectorTestAttributes,
        );
    }

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

    public function click(string $target): self
    {
        $this->driver->clickElement($this->resolver->resolve($target));

        return $this;
    }

    public function press(string $button): self
    {
        return $this->click($button);
    }

    public function fill(string $field, string $value): self
    {
        $element = $this->resolver->resolve($field);
        $this->driver->callFunctionOn($element, 'function(){ this.value = ""; }');
        $this->driver->typeInto($element, $value);

        return $this;
    }

    public function type(string $field, string $value): self
    {
        $this->driver->typeInto($this->resolver->resolve($field), $value);

        return $this;
    }

    public function clear(string $field): self
    {
        $this->driver->callFunctionOn(
            $this->resolver->resolve($field),
            'function(){ this.value = ""; this.dispatchEvent(new Event("input", { bubbles: true })); }',
        );

        return $this;
    }

    public function select(string $field, string $value): self
    {
        $this->driver->callFunctionOn(
            $this->resolver->resolve($field),
            'function(v){ this.value = v; this.dispatchEvent(new Event("change", { bubbles: true })); }',
            $value,
        );

        return $this;
    }

    public function check(string $field): self
    {
        $this->driver->callFunctionOn($this->resolver->resolve($field), 'function(){ if (!this.checked) this.click(); }');

        return $this;
    }

    public function uncheck(string $field): self
    {
        $this->driver->callFunctionOn($this->resolver->resolve($field), 'function(){ if (this.checked) this.click(); }');

        return $this;
    }

    public function value(string $field): string
    {
        $value = $this->driver->callFunctionOn($this->resolver->resolve($field), 'function(){ return this.value; }');

        return is_string($value) ? $value : '';
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

    public function assertValue(string $field, string $expected): self
    {
        Assert::assertSame($expected, $this->value($field), "Field \"{$field}\" had an unexpected value.");

        return $this;
    }

    public function assertVisible(string $target): self
    {
        Assert::assertTrue($this->isVisible($target), "Expected \"{$target}\" to be visible.");

        return $this;
    }

    public function assertMissing(string $target): self
    {
        Assert::assertFalse($this->isVisible($target), "Expected \"{$target}\" to be missing or hidden.");

        return $this;
    }

    public function assertTextNear(string $near, string $text): self
    {
        $found = $this->driver->evaluateScript($this->textNearScript($near, $text));
        Assert::assertTrue($found === true, "Expected to see \"{$text}\" near \"{$near}\".");

        return $this;
    }

    private function isVisible(string $target): bool
    {
        try {
            $element = $this->resolver->resolve($target);
        } catch (ElementNotFoundException) {
            return false;
        }

        return $this->driver->callFunctionOn(
            $element,
            'function(){ const r = this.getBoundingClientRect(); const s = getComputedStyle(this);'
            .' return !!(r.width || r.height) && s.visibility !== "hidden" && s.display !== "none"; }',
        ) === true;
    }

    private function textNearScript(string $near, string $text): string
    {
        $nearJson = json_encode($near, JSON_THROW_ON_ERROR);
        $textJson = json_encode($text, JSON_THROW_ON_ERROR);

        return '(function(near, text){'
            .' const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);'
            .' let node; while (node = walker.nextNode()) {'
            .'  if (node.textContent.includes(near)) {'
            .'   let el = node.parentElement;'
            .'   for (let i = 0; i < 3 && el; i++) { if (el.textContent.includes(text)) return true; el = el.parentElement; }'
            .'  } }'
            .' return false;'
            ."})({$nearJson}, {$textJson})";
    }

    private function visibleText(): string
    {
        $text = $this->driver->evaluateScript('document.body ? document.body.innerText : ""');

        return is_string($text) ? $text : '';
    }
}
