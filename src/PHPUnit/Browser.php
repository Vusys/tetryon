<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use PHPUnit\Framework\Assert;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Dialog\DialogExpectation;
use Vusys\Tetryon\Core\Dialog\UnhandledDialogException;
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
     * Injected actionability probe. Rejects invisible / transparent /
     * pointer-event-deaf elements, waits for the bounding box to be stable
     * across one animation frame (covering transform and size transitions, not
     * just opacity), then hit-tests the click point so an overlay painted on top
     * makes the action wait rather than land on the wrong element. Returns `ok`
     * or a short failure reason.
     *
     * Scrolling is deliberately reluctant, and happens at two points:
     *
     * 1. Before probing, when the target is not fully in view — measured against
     *    the viewport *and* the visible box of every scrollable ancestor, so an
     *    element scrolled out of an `overflow: auto` pane (a scrollable modal
     *    body, a sticky-footer panel) counts as out of view even though its
     *    layout position is still on-screen (issue #117).
     * 2. After the hit test fails, as a recovery — a `position: fixed` bar can
     *    cover a target that is inside the viewport and inside every pane, and
     *    centring is exactly what clears it. Deciding to scroll before anything
     *    is known about occlusion is what made both shapes of #117 unfixable.
     *
     * A target that is already clear is therefore never moved, so the scroll
     * can't reposition or dismiss a scroll-sensitive overlay (popover / tooltip
     * / menu) out from under the click (issue #96). When a scroll does run it is
     * instant (`behavior: instant` overrides any `scroll-behavior: smooth` —
     * Bootstrap Reboot sets it on :root — so the scroll can't animate under the
     * click), centred vertically (`block: center`, to clear fixed/sticky top and
     * bottom bars) but only as far as needed horizontally (`inline: nearest`).
     */
    private const string ACTIONABLE_JS = <<<'JS'
        async function () {
          const self = this;
          const clips = function (el) {
            if (el === document.body || el === document.documentElement) return false;
            const s = getComputedStyle(el);
            if (!/^(auto|scroll|hidden|clip)$/.test(s.overflowY) && !/^(auto|scroll|hidden|clip)$/.test(s.overflowX)) return false;
            return el.scrollHeight > el.clientHeight || el.scrollWidth > el.clientWidth;
          };
          const inView = function () {
            const r = self.getBoundingClientRect();
            const vw = window.innerWidth || document.documentElement.clientWidth;
            const vh = window.innerHeight || document.documentElement.clientHeight;
            if (r.top < 0 || r.left < 0 || r.bottom > vh || r.right > vw) return false;
            for (let p = self.parentElement; p; p = p.parentElement) {
              if (!clips(p)) continue;
              const q = p.getBoundingClientRect();
              if (r.top < q.top || r.left < q.left || r.bottom > q.bottom || r.right > q.right) return false;
            }
            return true;
          };
          const centre = function () {
            self.scrollIntoView({ behavior: 'instant', block: 'center', inline: 'nearest' });
          };
          const lands = function (hit) {
            return !!hit && (hit === self || self.contains(hit) || hit.contains(self));
          };
          const topmost = function (r) {
            const x = r.left + r.width / 2, y = r.top + r.height / 2;
            let el = document.elementFromPoint(x, y);
            // elementFromPoint returns the shadow host, not the element inside it,
            // and Node.contains() doesn't cross shadow boundaries — so descend
            // open shadow roots to the deepest element at the point (#151).
            while (el && el.shadowRoot) {
              const inner = el.shadowRoot.elementFromPoint(x, y);
              if (!inner || inner === el) break;
              el = inner;
            }
            return el;
          };

          const s = getComputedStyle(this);
          if (this.disabled) return 'disabled';
          if (s.display === 'none' || s.visibility === 'hidden') return 'hidden';
          if (parseFloat(s.opacity) === 0) return 'transparent';
          if (s.pointerEvents === 'none') return 'no-pointer-events';
          if (!inView()) centre();
          const a = this.getBoundingClientRect();
          if (!(a.width || a.height)) return 'zero-size';
          let b = await new Promise(res => requestAnimationFrame(() => res(this.getBoundingClientRect())));
          if (a.x !== b.x || a.y !== b.y || a.width !== b.width || a.height !== b.height) return 'unstable';
          let hit = topmost(b);
          if (!lands(hit)) {
            centre();
            b = this.getBoundingClientRect();
            hit = topmost(b);
          }
          if (!hit) return 'off-screen';
          if (lands(hit)) return 'ok';
          if (hit.id) return 'occluded:#' + hit.id;
          if (typeof hit.className === 'string' && hit.className.trim()) {
            return 'occluded:.' + hit.className.trim().split(/\s+/).join('.');
          }
          return 'occluded:' + hit.tagName.toLowerCase();
        }
        JS;

    /**
     * The body of {@see blur()}, operating on a local `el`. Fires the real blur,
     * and only if the browser didn't dispatch one (headless Firefox never does)
     * synthesises `blur` + bubbling `focusout` so commit-on-blur handlers run
     * exactly once in both headless and headed modes.
     */
    private const string BLUR_BODY = <<<'JS'
        if (el && el !== document.body) {
          let fired = false;
          const mark = function () { fired = true; };
          el.addEventListener('blur', mark, { once: true, capture: true });
          el.blur();
          el.removeEventListener('blur', mark, { capture: true });
          if (!fired) {
            el.dispatchEvent(new FocusEvent('blur'));
            el.dispatchEvent(new FocusEvent('focusout', { bubbles: true }));
          }
        }
        JS;

    /**
     * The body of {@see visibleText()}, operating on a root `r` (document.body or
     * the scoped element). `innerText` for the light DOM, plus the text content of
     * every open shadow root beneath it so shadow-DOM apps aren't invisible (#151).
     */
    private const string VISIBLE_TEXT_BODY = <<<'JS'
        if (!r) return '';
        let text = r.innerText || '';
        const collect = function (node) {
          node.querySelectorAll('*').forEach(function (el) {
            if (el.shadowRoot) {
              text += '\n' + (el.shadowRoot.textContent || '');
              collect(el.shadowRoot);
            }
          });
        };
        collect(r);
        if (r.shadowRoot) text += '\n' + (r.shadowRoot.textContent || '');
        return text;
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

    /**
     * Scroll the target into view, centred vertically — scrolling every
     * scrollable ancestor as needed, so a row inside an `overflow: auto` pane is
     * reached as readily as one below the fold. Waits for the element to exist
     * but not to be clickable, so a test can deliberately reach a lazily-rendered
     * or lazily-loaded region (issue #117). The action verbs scroll on their own,
     * so this is for the cases where the scroll itself is the behaviour under
     * test — an infinite scroller, a scroll-spy nav, a lazy-loading list.
     */
    public function scrollTo(string $target): self
    {
        $this->driver->callFunctionOn(
            $this->resolveWaiting($target),
            'function(){ this.scrollIntoView({ behavior: "instant", block: "center", inline: "nearest" }); }',
        );

        return $this;
    }

    public function scrollToTop(): self
    {
        $this->driver->evaluateScript('window.scrollTo({ top: 0, left: 0, behavior: "instant" })');

        return $this;
    }

    public function scrollToBottom(): self
    {
        $this->driver->evaluateScript(
            'window.scrollTo({ top: document.documentElement.scrollHeight, left: 0, behavior: "instant" })',
        );

        return $this;
    }

    public function pressKey(string $key): self
    {
        $this->driver->pressKeys($key);

        return $this;
    }

    /**
     * Blur an element (or the currently-focused element when no target is given),
     * so "commit on blur" flows — inline edits, validate-on-blur fields — read as
     * behaviour rather than a `pressKey('Tab')` trick.
     *
     * Headless Firefox does not fire `blur`/`focusout` (the window is never
     * "focused": `document.hasFocus()` is false), so a real blur would silently
     * no-op and the commit would never run. This dispatches the events itself
     * **only when the browser didn't** — so it works headless, and does not
     * double-fire when running headed. See {@see currentPath()} neighbours in the
     * docs for the wider headless-focus note.
     */
    public function blur(?string $target = null): self
    {
        if ($target === null) {
            $this->driver->evaluateScript('(function () { const el = document.activeElement; '.self::BLUR_BODY.' })()');
        } else {
            $this->driver->callFunctionOn($this->resolveWaiting($target), 'function () { const el = this; '.self::BLUR_BODY.' }');
        }

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

    /**
     * Choose an option from a **custom** (non-native) dropdown / combobox — the
     * WAI-ARIA pattern component libraries render instead of a `<select>`: a
     * trigger (or `role="combobox"` input) that reveals a `role="listbox"` of
     * `role="option"` elements. {@see select()} only drives a native `<select>`;
     * this opens the control by clicking `$field`, then clicks the option whose
     * visible text matches `$option`.
     *
     * The option is matched globally by `role="option"`, so it works even when the
     * list is portalled to the end of `<body>`. For a type-to-filter combobox,
     * `fill()` the field first to narrow the list, then call this.
     */
    public function chooseFromDropdown(string $field, string $option): self
    {
        $this->click($field);
        $this->click(sprintf('//*[@role="option"][normalize-space()=%s]', $this->xpathLiteral($option)));

        return $this;
    }

    public function check(string $field): self
    {
        $this->driver->callFunctionOn($this->checkable('check', $field), 'function(){ if (!this.checked) this.click(); }');

        return $this;
    }

    public function uncheck(string $field): self
    {
        $this->driver->callFunctionOn($this->checkable('uncheck', $field), 'function(){ if (this.checked) this.click(); }');

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
     * Whether the target is the document's currently-focused element. Works in
     * headless (`document.activeElement` tracks focus even when the window
     * itself is not focused).
     */
    public function isFocused(string $target): bool
    {
        return $this->driver->callFunctionOn(
            $this->resolveWaiting($target),
            'function(){ return document.activeElement === this; }',
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

    // ── Native dialogs ──────────────────────────────────────────────────────

    /**
     * Answer the next `window.confirm` / `window.alert` with OK, optionally
     * asserting its wording (a substring is enough).
     *
     * Arrange this **before** the action that opens the dialog. A native dialog
     * blocks the page, so the click that triggered it does not complete until
     * the dialog is answered — there is no "after" to answer it in:
     *
     *     $this->browser()
     *         ->acceptDialog('Delete the preset')
     *         ->press('Delete preset')
     *         ->assertSee('deleted');
     *
     * A dialog nobody arranged an answer for is dismissed and the action fails
     * with {@see UnhandledDialogException} naming it, so an unexpected
     * confirmation is a one-line diagnosis rather than a wedged session.
     */
    public function acceptDialog(?string $expectedMessage = null): self
    {
        $this->driver->expectDialog(new DialogExpectation(accept: true, expectedMessage: $expectedMessage));

        return $this;
    }

    /**
     * Answer the next dialog with Cancel — the "keep it" branch of a destructive
     * confirmation. Arrange it before the action, as {@see acceptDialog()}.
     */
    public function dismissDialog(?string $expectedMessage = null): self
    {
        $this->driver->expectDialog(new DialogExpectation(accept: false, expectedMessage: $expectedMessage));

        return $this;
    }

    /**
     * Answer the next `window.prompt` with text (and OK). Arrange it before the
     * action, as {@see acceptDialog()}.
     */
    public function typeInDialog(string $text, ?string $expectedMessage = null): self
    {
        $this->driver->expectDialog(new DialogExpectation(accept: true, text: $text, expectedMessage: $expectedMessage));

        return $this;
    }

    /**
     * The message of the last dialog that appeared, or null if none has.
     */
    public function dialogMessage(): ?string
    {
        return $this->driver->lastDialog()?->message;
    }

    /**
     * Assert on the wording of the last dialog that appeared — the phrasing of a
     * destructive confirmation is often the thing worth testing. Runs after the
     * action, on the dialog the arranged answer already closed.
     */
    public function assertDialogMessage(string $expected): self
    {
        $this->retry(fn (): bool => str_contains($this->dialogMessage() ?? '', $expected));
        Assert::assertStringContainsString(
            $expected,
            $this->dialogMessage() ?? '',
            'The last dialog did not say what was expected.',
        );

        return $this;
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
            'scrollTo' => $this->scrollTo($first),
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

    /**
     * The path of the current URL, with the `#fragment` appended when one is
     * present — so hash routes (`/app/#/active`) are assertable with
     * {@see assertPathIs()} and {@see waitForPath()}. A fragment-free URL is
     * unchanged (`/foo` stays `/foo`). The query string is not included.
     */
    public function currentPath(): string
    {
        $url = $this->driver->currentUrl();
        $path = parse_url($url, PHP_URL_PATH);
        $path = is_string($path) ? $path : '';

        $fragment = parse_url($url, PHP_URL_FRAGMENT);

        return is_string($fragment) && $fragment !== '' ? $path.'#'.$fragment : $path;
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

    public function assertFocused(string $target): self
    {
        $this->retry(fn (): bool => $this->isFocused($target));
        Assert::assertTrue($this->isFocused($target), "Expected \"{$target}\" to be focused.");

        return $this;
    }

    public function assertNotFocused(string $target): self
    {
        $this->retry(fn (): bool => ! $this->isFocused($target));
        Assert::assertFalse($this->isFocused($target), "Expected \"{$target}\" not to be focused.");

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
        return $this->awaitActionable($this->resolveWaiting($target, $preferInteractive), $target);
    }

    /**
     * Wait for an already-resolved element to pass the pointer-actionability
     * probe, throwing a {@see TimeoutException} that names the last failure
     * reason. Split out from {@see actionable()} so verbs that resolve their
     * target specially (check()/uncheck()) can reuse the probe.
     */
    private function awaitActionable(ElementReference $element, string $target): ElementReference
    {
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
            $this->drivable('select', $field),
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
     * Resolve an actionable `<select>` for select()/selectByValue(), throwing
     * {@see UndrivableElementException} rather than silently no-opping when the
     * target isn't a native select (#77).
     */
    private function drivable(string $verb, string $field): ElementReference
    {
        $element = $this->actionable($field);

        $info = $this->elementInfo($element);
        if ($info->tag !== 'select') {
            throw UndrivableElementException::for($verb, $field, $info->describe(), 'select');
        }

        return $element;
    }

    /**
     * Resolve a checkbox/radio for check()/uncheck() — including one behind a
     * text label associated by wrapping, `for=`, or adjacency (#138) — and verify
     * it really is a checkbox or radio (#77).
     *
     * Unlike the pointer verbs, check()/uncheck() drive the control with a
     * synthetic `this.click()`, which a visually-hidden real input backing a
     * styled label still receives — the ubiquitous custom checkbox/radio/toggle
     * pattern (`opacity: 0`, `pointer-events: none`). So this deliberately skips
     * the pointer-actionability probe and waits only for the element to exist and
     * be enabled (#139).
     */
    private function checkable(string $verb, string $field): ElementReference
    {
        $element = $this->resolveCheckableWaiting($field);

        $enabled = $this->wait(
            $this->configuration->timeouts->default,
            fn (): bool => $this->driver->callFunctionOn($element, 'function(){ return !this.disabled; }') === true,
        );
        if (! $enabled) {
            throw new TimeoutException("Timed out waiting for \"{$field}\" to become actionable (disabled).");
        }

        $info = $this->elementInfo($element);
        if ($info->tag !== 'input' || ! in_array($info->type, ['checkbox', 'radio'], true)) {
            throw UndrivableElementException::for($verb, $field, $info->describe(), 'checkable');
        }

        return $element;
    }

    private function resolveCheckableWaiting(string $target): ElementReference
    {
        $element = null;
        $this->wait($this->configuration->timeouts->default, function () use ($target, &$element): bool {
            try {
                $element = $this->resolver->resolveCheckable($target);

                return true;
            } catch (ElementNotFoundException) {
                return false;
            }
        });

        return $element ?? $this->resolver->resolveCheckable($target);
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

    /**
     * The page's (or scope's) visible text. `innerText` stops at shadow
     * boundaries, so this also appends the text content of every open shadow root
     * — otherwise a web-component app (#151) would look empty to assertSee().
     */
    private function visibleText(): string
    {
        $text = $this->scope instanceof ElementReference
            ? $this->driver->callFunctionOn($this->scope, 'function(){ const r = this; '.self::VISIBLE_TEXT_BODY.' }')
            : $this->driver->evaluateScript('(function(){ const r = document.body; '.self::VISIBLE_TEXT_BODY.' })()');

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
     * An XPath string literal that survives embedded quotes (via `concat()` when
     * the value contains both kinds).
     */
    private function xpathLiteral(string $value): string
    {
        if (! str_contains($value, '"')) {
            return '"'.$value.'"';
        }

        if (! str_contains($value, "'")) {
            return "'".$value."'";
        }

        return 'concat("'.str_replace('"', '",\'"\',"', $value).'")';
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
