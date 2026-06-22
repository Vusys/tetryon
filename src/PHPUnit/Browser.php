<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Assert;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\NaturalLanguage\StepParser;
use Vusys\Tetryon\Core\NaturalLanguage\UnknownStepException;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Core\Selector\ElementReference;
use Vusys\Tetryon\Core\Selector\SelectorResolver;
use Vusys\Tetryon\Core\Selector\SelectorStrategy;
use Vusys\Tetryon\Core\Support\TimeoutException;
use Vusys\Tetryon\Core\Support\Waiter;
use Vusys\Tetryon\Firefox\Bidi\BiDiTrace;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;

/**
 * The fluent, user-facing browser API. Wraps the Firefox driver and turns its
 * primitives into the readable verbs and assertions a PHPUnit test calls.
 *
 * Auto-waiting is the contract: every action waits for its target to be
 * actionable, and every assertion retries until it passes or the timeout
 * elapses — so tests never need a manual `sleep()`. Assertions delegate to
 * PHPUnit so failures appear as normal test failures.
 */
final readonly class Browser
{
    private SelectorResolver $resolver;

    public function __construct(
        private FirefoxBiDiDriver $driver,
        private Configuration $configuration,
        ?SelectorResolver $resolver = null,
        private ?ElementReference $scope = null,
    ) {
        $this->resolver = $resolver ?? new SelectorResolver(
            $driver,
            new SelectorStrategy,
            $configuration->selectorTestAttributes,
        );
    }

    // ── Navigation ──────────────────────────────────────────────────────────

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

    // ── Actions (auto-wait for the element to be actionable) ────────────────

    public function click(string $target): self
    {
        $this->driver->clickElement($this->actionable($target));

        return $this;
    }

    public function press(string $button): self
    {
        return $this->click($button);
    }

    /**
     * Run a callback with this browser, then continue the chain. Handy for
     * grouping assertions or extracting reusable, named assertion helpers.
     *
     * @param  callable(self): void  $callback
     */
    public function tap(callable $callback): self
    {
        $callback($this);

        return $this;
    }

    /**
     * Run a callback against a browser scoped to inside a container element, so
     * its selectors only resolve within that element. The outer chain continues
     * unscoped.
     *
     * @param  callable(self): void  $callback
     */
    public function within(string $target, callable $callback): self
    {
        $container = $this->resolveWaiting($target);
        $callback(new self($this->driver, $this->configuration, $this->resolver->within($container), $container));

        return $this;
    }

    public function doubleClick(string $target): self
    {
        $this->driver->doubleClickElement($this->actionable($target));

        return $this;
    }

    public function rightClick(string $target): self
    {
        $this->driver->rightClickElement($this->actionable($target));

        return $this;
    }

    public function hover(string $target): self
    {
        $this->driver->hoverElement($this->resolveWaiting($target));

        return $this;
    }

    public function pressKey(string $key): self
    {
        $this->driver->pressKeys($key);

        return $this;
    }

    public function choose(string $field, string $value): self
    {
        $css = sprintf('[type="radio"][name=%s][value=%s]', $this->cssQuote($field), $this->cssQuote($value));
        $this->driver->clickElement($this->actionable($css));

        return $this;
    }

    public function upload(string $field, string $path): self
    {
        $this->driver->setFiles($this->resolveWaiting($field), $path);

        return $this;
    }

    public function fill(string $field, string $value): self
    {
        $element = $this->actionable($field);
        $this->driver->callFunctionOn($element, 'function(){ this.value = ""; }');
        $this->driver->typeInto($element, $value);

        return $this;
    }

    public function type(string $field, string $value): self
    {
        $this->driver->typeInto($this->actionable($field), $value);

        return $this;
    }

    public function clear(string $field): self
    {
        $this->driver->callFunctionOn(
            $this->actionable($field),
            'function(){ this.value = ""; this.dispatchEvent(new Event("input", { bubbles: true })); }',
        );

        return $this;
    }

    public function select(string $field, string $value): self
    {
        $this->driver->callFunctionOn(
            $this->actionable($field),
            'function(v){ this.value = v; this.dispatchEvent(new Event("change", { bubbles: true })); }',
            $value,
        );

        return $this;
    }

    public function check(string $field): self
    {
        $this->driver->callFunctionOn($this->actionable($field), 'function(){ if (!this.checked) this.click(); }');

        return $this;
    }

    public function uncheck(string $field): self
    {
        $this->driver->callFunctionOn($this->actionable($field), 'function(){ if (this.checked) this.click(); }');

        return $this;
    }

    public function value(string $field): string
    {
        $value = $this->driver->callFunctionOn($this->resolveWaiting($field), 'function(){ return this.value; }');

        return is_string($value) ? $value : '';
    }

    // ── Natural language ────────────────────────────────────────────────────

    /**
     * Run a natural-language step ("I fill \"Email\" with \"x\"") by parsing it
     * to a fluent call. A convenience layer over the same API — see
     * {@see Scenario} for the given/when/then form.
     */
    public function step(string $sentence): self
    {
        $step = StepParser::parse($sentence);
        $first = $step->arguments[0] ?? '';
        $second = $step->arguments[1] ?? '';

        match ($step->action) {
            'visit' => $this->visit($first),
            'fill' => $this->fill($first, $second),
            'type' => $this->type($first, $second),
            'clear' => $this->clear($first),
            'press' => $this->press($first),
            'click' => $this->click($first),
            'check' => $this->check($first),
            'uncheck' => $this->uncheck($first),
            'select' => $this->select($first, $second),
            'pressKey' => $this->pressKey($first),
            'assertSee' => $this->assertSee($first),
            'assertDontSee' => $this->assertDontSee($first),
            'assertPathIs' => $this->assertPathIs($first),
            'assertTitleIs' => $this->assertTitleIs($first),
            default => throw UnknownStepException::for($sentence),
        };

        return $this;
    }

    // ── Explicit waits (throw on timeout) ───────────────────────────────────

    public function waitForText(string $text): self
    {
        return $this->awaitOrThrow(
            $this->configuration->timeouts->default,
            fn (): bool => str_contains($this->visibleText(), $text),
            "Timed out waiting to see \"{$text}\".",
        );
    }

    public function waitUntilMissing(string $text): self
    {
        return $this->awaitOrThrow(
            $this->configuration->timeouts->default,
            fn (): bool => ! str_contains($this->visibleText(), $text),
            "Timed out waiting for \"{$text}\" to disappear.",
        );
    }

    public function waitForPath(string $path): self
    {
        return $this->awaitOrThrow(
            $this->configuration->timeouts->navigation,
            fn (): bool => $this->currentPath() === $path,
            "Timed out waiting for the path to become \"{$path}\".",
        );
    }

    public function waitForUrl(string $url): self
    {
        $expected = $this->configuration->resolveUrl($url);

        return $this->awaitOrThrow(
            $this->configuration->timeouts->navigation,
            fn (): bool => $this->currentUrl() === $expected,
            "Timed out waiting for the URL to become \"{$expected}\".",
        );
    }

    // ── Queries ─────────────────────────────────────────────────────────────

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

    /**
     * Evaluate a JavaScript expression in the page and return its value. Promises
     * are awaited, so an async IIFE resolves to its value:
     *
     *     $browser->evaluate('document.title');
     *     $browser->evaluate('(async () => (await fetch("/__test__/login", {method:"POST"})).status)()');
     *
     * The generic escape hatch for the cases the fluent verbs don't model. State,
     * not an action — it does not auto-wait.
     */
    public function evaluate(string $script): mixed
    {
        return $this->driver->evaluateScript($script);
    }

    /**
     * The recent BiDi command trace — useful for debugging or asserting on what
     * the browser actually did.
     */
    public function trace(): BiDiTrace
    {
        return $this->driver->trace();
    }

    // ── Assertions (retry until they pass or the timeout elapses) ───────────

    public function assertSee(string $text): self
    {
        $this->retry(fn (): bool => str_contains($this->visibleText(), $text));
        Assert::assertStringContainsString($text, $this->visibleText(), "Expected to see \"{$text}\" on the page.");

        return $this;
    }

    public function assertDontSee(string $text): self
    {
        $this->retry(fn (): bool => ! str_contains($this->visibleText(), $text));
        Assert::assertStringNotContainsString($text, $this->visibleText(), "Did not expect to see \"{$text}\" on the page.");

        return $this;
    }

    public function assertUrlIs(string $url): self
    {
        $expected = $this->configuration->resolveUrl($url);
        $this->retry(fn (): bool => $this->currentUrl() === $expected);
        Assert::assertSame($expected, $this->currentUrl());

        return $this;
    }

    public function assertPathIs(string $path): self
    {
        $this->retry(fn (): bool => $this->currentPath() === $path);
        Assert::assertSame($path, $this->currentPath());

        return $this;
    }

    public function assertTitleIs(string $title): self
    {
        $this->retry(fn (): bool => $this->title() === $title);
        Assert::assertSame($title, $this->title());

        return $this;
    }

    public function assertValue(string $field, string $expected): self
    {
        $this->retry(fn (): bool => $this->value($field) === $expected);
        Assert::assertSame($expected, $this->value($field), "Field \"{$field}\" had an unexpected value.");

        return $this;
    }

    public function assertVisible(string $target): self
    {
        $this->retry(fn (): bool => $this->isVisibleNow($target));
        Assert::assertTrue($this->isVisibleNow($target), "Expected \"{$target}\" to be visible.");

        return $this;
    }

    public function assertMissing(string $target): self
    {
        $this->retry(fn (): bool => ! $this->isVisibleNow($target));
        Assert::assertFalse($this->isVisibleNow($target), "Expected \"{$target}\" to be missing or hidden.");

        return $this;
    }

    public function assertTextNear(string $near, string $text): self
    {
        $script = $this->textNearScript($near, $text);
        $this->retry(fn (): bool => $this->driver->evaluateScript($script) === true);
        Assert::assertTrue(
            $this->driver->evaluateScript($script) === true,
            "Expected to see \"{$text}\" near \"{$near}\".",
        );

        return $this;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    private function actionable(string $target): ElementReference
    {
        $element = $this->resolveWaiting($target);
        $this->wait(
            $this->configuration->timeouts->default,
            fn (): bool => $this->driver->callFunctionOn(
                $element,
                'function(){ const r = this.getBoundingClientRect(); const s = getComputedStyle(this);'
                .' return !this.disabled && !!(r.width || r.height)'
                .' && s.visibility !== "hidden" && s.display !== "none"; }',
            ) === true,
        );

        return $element;
    }

    private function resolveWaiting(string $target): ElementReference
    {
        $element = null;
        $this->wait($this->configuration->timeouts->default, function () use ($target, &$element): bool {
            $element = $this->resolveNow($target);

            return $element instanceof ElementReference;
        });

        // On timeout, resolve once more so the rich ElementNotFoundException (with
        // its attempt list) surfaces instead of a bare null.
        return $element ?? $this->resolver->resolve($target);
    }

    private function resolveNow(string $target): ?ElementReference
    {
        try {
            return $this->resolver->resolve($target);
        } catch (ElementNotFoundException) {
            return null;
        }
    }

    private function isVisibleNow(string $target): bool
    {
        $element = $this->resolveNow($target);
        if (! $element instanceof ElementReference) {
            return false;
        }

        return $this->driver->callFunctionOn(
            $element,
            'function(){ const r = this.getBoundingClientRect(); const s = getComputedStyle(this);'
            .' return !!(r.width || r.height) && s.visibility !== "hidden" && s.display !== "none"; }',
        ) === true;
    }

    /**
     * @param  callable(): bool  $condition
     */
    private function retry(callable $condition): void
    {
        $this->wait($this->configuration->timeouts->assertion, $condition);
    }

    /**
     * @param  callable(): bool  $condition
     */
    private function awaitOrThrow(int $timeoutMs, callable $condition, string $message): self
    {
        if (! $this->wait($timeoutMs, $condition)) {
            throw new TimeoutException($message);
        }

        return $this;
    }

    /**
     * @param  callable(): bool  $condition
     */
    private function wait(int $timeoutMs, callable $condition): bool
    {
        return new Waiter($timeoutMs)->until($condition);
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
        $text = $this->scope instanceof ElementReference
            ? $this->driver->callFunctionOn($this->scope, 'function(){ return this.innerText; }')
            : $this->driver->evaluateScript('document.body ? document.body.innerText : ""');

        return is_string($text) ? $text : '';
    }

    private function cssQuote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }
}
