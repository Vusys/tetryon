<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Assert;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\NaturalLanguage\StepParser;
use Vusys\Tetryon\Core\NaturalLanguage\UnknownStepException;
use Vusys\Tetryon\Core\Selector\ElementInfo;
use Vusys\Tetryon\Core\Selector\ElementNotFoundException;
use Vusys\Tetryon\Core\Selector\ElementReference;
use Vusys\Tetryon\Core\Selector\OptionNotFoundException;
use Vusys\Tetryon\Core\Selector\SelectorResolver;
use Vusys\Tetryon\Core\Selector\SelectorStrategy;
use Vusys\Tetryon\Core\Selector\UndrivableElementException;
use Vusys\Tetryon\Core\Support\TimeoutException;
use Vusys\Tetryon\Core\Support\Waiter;
use Vusys\Tetryon\Firefox\Bidi\BiDiTrace;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\NetworkRecord;

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
    /**
     * Injected actionability probe. Scrolls the element into view, rejects
     * invisible / transparent / pointer-event-deaf elements, waits for the
     * bounding box to be stable across one animation frame (covering opacity,
     * transform and size transitions, not just opacity), then hit-tests the
     * click point so an overlay painted on top makes the action wait rather
     * than land on the wrong element. Returns `ok` or a short failure reason.
     */
    private const string ACTIONABLE_JS = <<<'JS'
        async function () {
          this.scrollIntoView({ block: 'center', inline: 'center' });
          const s = getComputedStyle(this);
          if (this.disabled) return 'disabled';
          if (s.display === 'none' || s.visibility === 'hidden') return 'hidden';
          if (parseFloat(s.opacity) === 0) return 'transparent';
          if (s.pointerEvents === 'none') return 'no-pointer-events';
          const a = this.getBoundingClientRect();
          if (!(a.width || a.height)) return 'zero-size';
          const b = await new Promise(res => requestAnimationFrame(() => res(this.getBoundingClientRect())));
          if (a.x !== b.x || a.y !== b.y || a.width !== b.width || a.height !== b.height) return 'unstable';
          const hit = document.elementFromPoint(b.left + b.width / 2, b.top + b.height / 2);
          if (!hit) return 'off-screen';
          if (hit === this || this.contains(hit) || hit.contains(this)) return 'ok';
          if (hit.id) return 'occluded:#' + hit.id;
          if (typeof hit.className === 'string' && hit.className.trim()) {
            return 'occluded:.' + hit.className.trim().split(/\s+/).join('.');
          }
          return 'occluded:' + hit.tagName.toLowerCase();
        }
        JS;

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
        $this->driver->clickElement($this->actionable($target, preferInteractive: true));

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
        $this->driver->doubleClickElement($this->actionable($target, preferInteractive: true));

        return $this;
    }

    public function rightClick(string $target): self
    {
        $this->driver->rightClickElement($this->actionable($target, preferInteractive: true));

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

    /**
     * Drag the source element onto the target, with intermediate pointer moves so
     * pointer-drag libraries (Sortable.js, vuedraggable, …) register the gesture.
     * Targets pointer-based DnD, not HTML5 `draggable` drag events.
     */
    public function drag(string $source, string $target): self
    {
        $this->driver->dragElement($this->actionable($source), $this->resolveWaiting($target));

        return $this;
    }

    /**
     * Drag the source element to absolute viewport coordinates.
     */
    public function dragTo(string $source, int $x, int $y): self
    {
        $this->driver->dragElementTo($this->actionable($source), $x, $y);

        return $this;
    }

    public function dragUp(string $source, int $pixels): self
    {
        return $this->dragBy($source, 0, -$pixels);
    }

    public function dragDown(string $source, int $pixels): self
    {
        return $this->dragBy($source, 0, $pixels);
    }

    public function dragLeft(string $source, int $pixels): self
    {
        return $this->dragBy($source, -$pixels, 0);
    }

    public function dragRight(string $source, int $pixels): self
    {
        return $this->dragBy($source, $pixels, 0);
    }

    private function dragBy(string $source, int $dx, int $dy): self
    {
        $this->driver->dragElementBy($this->actionable($source), $dx, $dy);

        return $this;
    }

    public function choose(string $field, string $value): self
    {
        $this->driver->clickElement($this->actionable($this->radioSelector($field, $value)));

        return $this;
    }

    public function upload(string $field, string $path): self
    {
        $this->driver->setFiles($this->resolveWaiting($field), $path);

        return $this;
    }

    public function fill(string $field, string $value): self
    {
        [$element, $info] = $this->textControl('fill', $field);
        $this->driver->callFunctionOn($element, $this->clearTextScript($info));
        $this->driver->typeInto($element, $value);

        return $this;
    }

    public function type(string $field, string $value): self
    {
        [$element] = $this->textControl('type', $field);
        $this->driver->typeInto($element, $value);

        return $this;
    }

    public function clear(string $field): self
    {
        [$element, $info] = $this->textControl('clear', $field);
        $this->driver->callFunctionOn($element, $this->clearTextScript($info));

        return $this;
    }

    /**
     * Choose a `<select>` option by its visible label or its value (label is the
     * common case when values are opaque ids). Use {@see selectByValue()} to
     * match the value only. Throws {@see OptionNotFoundException} if no option
     * matches.
     */
    public function select(string $field, string $value): self
    {
        return $this->selectOption($field, $value, byValueOnly: false);
    }

    public function selectByValue(string $field, string $value): self
    {
        return $this->selectOption($field, $value, byValueOnly: true);
    }

    public function check(string $field): self
    {
        $this->driver->callFunctionOn($this->drivable('check', $field, 'checkable'), 'function(){ if (!this.checked) this.click(); }');

        return $this;
    }

    public function uncheck(string $field): self
    {
        $this->driver->callFunctionOn($this->drivable('uncheck', $field, 'checkable'), 'function(){ if (this.checked) this.click(); }');

        return $this;
    }

    public function value(string $field): string
    {
        $value = $this->driver->callFunctionOn(
            $this->resolveWaiting($field),
            'function(){ return this.isContentEditable ? this.textContent : this.value; }',
        );

        return is_string($value) ? $value : '';
    }

    /**
     * Whether a checkbox or radio is currently checked.
     */
    public function isChecked(string $target): bool
    {
        return $this->driver->callFunctionOn(
            $this->resolveWaiting($target),
            'function(){ return !!this.checked; }',
        ) === true;
    }

    /**
     * The selected option's value of a `<select>`, or null if the element is not
     * a select.
     */
    public function selected(string $field): ?string
    {
        $value = $this->driver->callFunctionOn(
            $this->resolveWaiting($field),
            'function(){ if (this.tagName.toLowerCase() !== "select") return null;'
            .' return this.multiple ? ((this.selectedOptions[0] || {}).value ?? null) : this.value; }',
        );

        return is_string($value) ? $value : null;
    }

    /**
     * The value of an attribute (`href`, `data-*`, `aria-*`, …), or null when the
     * attribute is absent.
     */
    public function attribute(string $target, string $name): ?string
    {
        $value = $this->driver->callFunctionOn(
            $this->resolveWaiting($target),
            'function(name){ return this.getAttribute(name); }',
            $name,
        );

        return is_string($value) ? $value : null;
    }

    // ── Cookies (state, not actions — no auto-wait) ─────────────────────────

    /**
     * Set a cookie. The domain defaults to the base-URL host and the path to
     * `/`; pass `domain`, `path`, `secure`, `httpOnly`, `sameSite`, or `expiry`
     * to override. Backed by BiDi storage, so HttpOnly cookies work and the
     * cookie is in place before the first navigation carries it.
     *
     * @param  array{domain?: string, path?: string, secure?: bool, httpOnly?: bool, sameSite?: string, expiry?: int}  $options
     */
    public function setCookie(string $name, string $value, array $options = []): self
    {
        $domain = $options['domain'] ?? $this->cookieDomain();
        unset($options['domain']);
        $this->driver->setCookie($name, $value, $domain, $this->cookieOrigin(), $options);

        return $this;
    }

    public function cookie(string $name): ?string
    {
        return $this->driver->getCookie($name, $this->cookieOrigin());
    }

    public function deleteCookie(string $name): self
    {
        $this->driver->deleteCookie($name, $this->cookieOrigin());

        return $this;
    }

    public function clearCookies(): self
    {
        $this->driver->clearCookies($this->cookieOrigin());

        return $this;
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

    /**
     * Poll a JavaScript expression until it evaluates truthy — for page state
     * the DOM doesn't render as text (store readiness, a derived flag, a chart
     * library's data). Built on {@see evaluate()}; promises are awaited.
     */
    public function waitForExpression(string $expression, ?int $timeoutMs = null): self
    {
        return $this->awaitOrThrow(
            $timeoutMs ?? $this->configuration->timeouts->default,
            fn (): bool => (bool) $this->evaluate($expression),
            "Timed out waiting for the expression to become truthy: {$expression}",
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

    // ── Form-control state assertions (retry until they pass) ───────────────

    public function assertChecked(string $target): self
    {
        $this->retry(fn (): bool => $this->isChecked($target));
        Assert::assertTrue($this->isChecked($target), "Expected \"{$target}\" to be checked.");

        return $this;
    }

    public function assertNotChecked(string $target): self
    {
        $this->retry(fn (): bool => ! $this->isChecked($target));
        Assert::assertFalse($this->isChecked($target), "Expected \"{$target}\" not to be checked.");

        return $this;
    }

    public function assertRadioSelected(string $field, string $value): self
    {
        return $this->assertChecked($this->radioSelector($field, $value));
    }

    public function assertRadioNotSelected(string $field, string $value): self
    {
        return $this->assertNotChecked($this->radioSelector($field, $value));
    }

    public function assertSelected(string $field, string $value): self
    {
        $this->retry(fn (): bool => $this->selected($field) === $value);
        Assert::assertSame($value, $this->selected($field), "Expected \"{$field}\" to have \"{$value}\" selected.");

        return $this;
    }

    public function assertNotSelected(string $field, string $value): self
    {
        $this->retry(fn (): bool => $this->selected($field) !== $value);
        Assert::assertNotSame($value, $this->selected($field), "Expected \"{$field}\" not to have \"{$value}\" selected.");

        return $this;
    }

    public function assertEnabled(string $target): self
    {
        $this->retry(fn (): bool => ! $this->isDisabled($target));
        Assert::assertFalse($this->isDisabled($target), "Expected \"{$target}\" to be enabled.");

        return $this;
    }

    public function assertDisabled(string $target): self
    {
        $this->retry(fn (): bool => $this->isDisabled($target));
        Assert::assertTrue($this->isDisabled($target), "Expected \"{$target}\" to be disabled.");

        return $this;
    }

    public function assertAttribute(string $target, string $name, string $expected): self
    {
        $this->retry(fn (): bool => $this->attribute($target, $name) === $expected);
        Assert::assertSame($expected, $this->attribute($target, $name), "Attribute \"{$name}\" of \"{$target}\" had an unexpected value.");

        return $this;
    }

    public function assertAttributeContains(string $target, string $name, string $needle): self
    {
        $this->retry(fn (): bool => str_contains($this->attribute($target, $name) ?? '', $needle));
        Assert::assertStringContainsString(
            $needle,
            $this->attribute($target, $name) ?? '',
            "Attribute \"{$name}\" of \"{$target}\" did not contain \"{$needle}\".",
        );

        return $this;
    }

    // ── JavaScript state probes (retry until they pass) ─────────────────────

    /**
     * Assert that a JavaScript expression evaluates truthy, retrying until it
     * does or the timeout elapses — the auto-wait counterpart to {@see evaluate()}
     * for state the DOM doesn't render as text.
     */
    public function assertExpression(string $expression, string $message = ''): self
    {
        $this->retry(fn (): bool => (bool) $this->evaluate($expression));
        Assert::assertTrue(
            (bool) $this->evaluate($expression),
            $message !== '' ? $message : "Expected this expression to be truthy: {$expression}",
        );

        return $this;
    }

    /**
     * Assert that a JavaScript expression equals an expected (serialisable)
     * value, retrying until it matches — so a failure shows expected-vs-actual.
     */
    public function assertExpressionEquals(string $expression, mixed $expected, string $message = ''): self
    {
        $this->retry(fn (): bool => $this->evaluate($expression) == $expected);
        Assert::assertEquals(
            $expected,
            $this->evaluate($expression),
            $message !== '' ? $message : "Expression did not equal the expected value: {$expression}",
        );

        return $this;
    }

    // ── Network observation ─────────────────────────────────────────────────

    /**
     * Wait until a request whose URL matches has been sent — synchronise on the
     * network instead of polling the DOM. The pattern is a substring, or a glob
     * with `*` wildcards (e.g. `*​/api/search*`).
     */
    public function waitForRequest(string $pattern): self
    {
        return $this->awaitOrThrow(
            $this->configuration->timeouts->navigation,
            fn (): bool => $this->matchingRequests($pattern) !== [],
            "Timed out waiting for a request matching \"{$pattern}\".",
        );
    }

    /**
     * Wait until a matching request has *completed* (a response arrived).
     */
    public function waitForResponse(string $pattern): self
    {
        return $this->awaitOrThrow(
            $this->configuration->timeouts->navigation,
            fn (): bool => $this->matchingResponses($pattern) !== [],
            "Timed out waiting for a response matching \"{$pattern}\".",
        );
    }

    public function assertRequested(string $pattern): self
    {
        $this->retry(fn (): bool => $this->matchingRequests($pattern) !== []);
        Assert::assertNotEmpty($this->matchingRequests($pattern), "Expected a request matching \"{$pattern}\".");

        return $this;
    }

    public function assertNotRequested(string $pattern): self
    {
        Assert::assertEmpty(
            $this->matchingRequests($pattern),
            "Did not expect a request matching \"{$pattern}\".",
        );

        return $this;
    }

    // ── Internals ───────────────────────────────────────────────────────────

    /**
     * Waits until the element is "actionable" in the Playwright sense: visible,
     * layout-stable (not mid-transition), and actually receiving pointer events
     * at its click point (nothing painted on top). Returns the element once the
     * injected check reports `ok`; on timeout it throws naming the last reason
     * (e.g. `occluded:.modal-backdrop`, `unstable`, `transparent`) so a
     * swallowed click surfaces as a useful error instead of a silent no-op.
     */
    private function actionable(string $target, bool $preferInteractive = false): ElementReference
    {
        $element = $this->resolveWaiting($target, $preferInteractive);

        $reason = 'unknown';
        $ok = $this->wait(
            $this->configuration->timeouts->default,
            function () use ($element, &$reason): bool {
                $result = $this->driver->callFunctionOn($element, self::ACTIONABLE_JS);
                $reason = is_string($result) ? $result : 'unknown';

                return $reason === 'ok';
            },
        );

        if (! $ok) {
            throw new TimeoutException("Timed out waiting for \"{$target}\" to become actionable ({$reason}).");
        }

        return $element;
    }

    private function selectOption(string $field, string $value, bool $byValueOnly): self
    {
        $matched = $this->driver->callFunctionOn(
            $this->drivable('select', $field, 'select'),
            'function(v, byValue){'
            .' for (const o of this.options) {'
            .'  if (o.value === v || (byValue !== "1" && o.text.trim() === v)) {'
            .'   this.value = o.value; this.dispatchEvent(new Event("change", { bubbles: true })); return true; } }'
            .' return false; }',
            $value,
            $byValueOnly ? '1' : '0',
        );

        if ($matched !== true) {
            throw OptionNotFoundException::for($field, $value, $byValueOnly);
        }

        return $this;
    }

    /**
     * Resolve an actionable element for a text verb (`fill`/`type`/`clear`) and
     * verify it has settable text — an `<input>`/`<textarea>` value, or a
     * `contenteditable` element (#80). Throws otherwise (#77).
     *
     * @return array{0: ElementReference, 1: ElementInfo}
     */
    private function textControl(string $verb, string $field): array
    {
        $element = $this->actionable($field);
        $info = $this->elementInfo($element);

        $hasValue = $info->tag === 'textarea'
            || ($info->tag === 'input' && ! in_array($info->type, ['checkbox', 'radio', 'file', 'submit', 'button', 'reset', 'image'], true));

        if (! $hasValue && ! $info->editable) {
            throw UndrivableElementException::for($verb, $field, $info->describe(), 'value');
        }

        return [$element, $info];
    }

    private function clearTextScript(ElementInfo $info): string
    {
        return $info->editable
            ? 'function(){ this.textContent = ""; this.dispatchEvent(new Event("input", { bubbles: true })); }'
            : 'function(){ this.value = ""; this.dispatchEvent(new Event("input", { bubbles: true })); }';
    }

    /**
     * Resolve an actionable element for a form verb and verify it is a control
     * the verb can actually drive, throwing {@see UndrivableElementException}
     * otherwise rather than silently no-opping (#77).
     *
     * @param  'select'|'checkable'  $kind
     */
    private function drivable(string $verb, string $field, string $kind): ElementReference
    {
        $element = $this->actionable($field);

        $info = $this->elementInfo($element);
        $accepted = match ($kind) {
            'select' => $info->tag === 'select',
            'checkable' => $info->tag === 'input' && in_array($info->type, ['checkbox', 'radio'], true),
        };

        if (! $accepted) {
            throw UndrivableElementException::for($verb, $field, $info->describe(), $kind);
        }

        return $element;
    }

    private function elementInfo(ElementReference $element): ElementInfo
    {
        $json = $this->driver->callFunctionOn(
            $element,
            'function(){ return JSON.stringify({'
            .' tag: this.tagName.toLowerCase(),'
            .' type: (this.type || "").toLowerCase(),'
            .' editable: !!this.isContentEditable }); }',
        );

        return ElementInfo::fromJson(is_string($json) ? $json : '');
    }

    private function resolveWaiting(string $target, bool $preferInteractive = false): ElementReference
    {
        $element = null;
        $this->wait($this->configuration->timeouts->default, function () use ($target, $preferInteractive, &$element): bool {
            $element = $this->resolveNow($target, $preferInteractive);

            return $element instanceof ElementReference;
        });

        // On timeout, resolve once more so the rich ElementNotFoundException (with
        // its attempt list) surfaces instead of a bare null.
        return $element ?? $this->resolveElement($target, $preferInteractive);
    }

    private function resolveNow(string $target, bool $preferInteractive = false): ?ElementReference
    {
        try {
            return $this->resolveElement($target, $preferInteractive);
        } catch (ElementNotFoundException) {
            return null;
        }
    }

    private function resolveElement(string $target, bool $preferInteractive): ElementReference
    {
        return $preferInteractive
            ? $this->resolver->resolveInteractive($target)
            : $this->resolver->resolve($target);
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

    private function isDisabled(string $target): bool
    {
        return $this->driver->callFunctionOn(
            $this->resolveWaiting($target),
            'function(){ return !!this.disabled; }',
        ) === true;
    }

    private function radioSelector(string $field, string $value): string
    {
        return sprintf('[type="radio"][name=%s][value=%s]', $this->cssQuote($field), $this->cssQuote($value));
    }

    private function cssQuote(string $value): string
    {
        return '"'.addcslashes($value, '"\\').'"';
    }

    /**
     * @return list<NetworkRecord>
     */
    private function matchingRequests(string $pattern): array
    {
        return array_values(array_filter(
            $this->driver->networkLog(),
            fn (NetworkRecord $record): bool => $this->urlMatches($pattern, $record->url),
        ));
    }

    /**
     * @return list<NetworkRecord>
     */
    private function matchingResponses(string $pattern): array
    {
        return array_values(array_filter(
            $this->matchingRequests($pattern),
            static fn (NetworkRecord $record): bool => $record->status !== null,
        ));
    }

    private function urlMatches(string $pattern, string $url): bool
    {
        if (str_contains($pattern, '*')) {
            return preg_match('#'.str_replace('\*', '.*', preg_quote($pattern, '#')).'#', $url) === 1;
        }

        return str_contains($url, $pattern);
    }

    private function cookieDomain(): string
    {
        $host = parse_url($this->configuration->baseUrl, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'localhost';
    }

    private function cookieOrigin(): string
    {
        $parts = parse_url($this->configuration->baseUrl);
        $scheme = is_array($parts) && is_string($parts['scheme'] ?? null) ? $parts['scheme'] : 'http';
        $host = is_array($parts) && is_string($parts['host'] ?? null) ? $parts['host'] : 'localhost';
        $port = is_array($parts) && is_int($parts['port'] ?? null) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$host}{$port}";
    }
}
