# Interactions

Every action resolves its target (see [Selectors](selectors.md)) and waits for
the element to be **actionable** — present, visible, and enabled — before
acting. You never need a manual wait.

## Navigation

```php
$this->browser()
    ->visit('/login')   // path resolved against base_url; absolute URLs pass through
    ->back()
    ->forward()
    ->refresh();
```

## Clicking

```php
->click('Settings')            // by link/button text, test attribute, etc.
->press('Save changes')        // press() reads naturally for buttons
->doubleClick('Spreadsheet cell')
->rightClick('Table row')      // opens the context menu target
->hover('Account menu');
```

`click()` / `press()` prefer an **interactive** target (button, link, input, …)
when more than one element matches — so pressing `'Log in'` clicks the submit
button, not a heading that happens to share its text. When nothing interactive
matches, the first match still wins, so a `<div>` with a click handler is fine.

## Typing

```php
->fill('Email', 'bryan@example.com')   // clears, then types
->type('Search', 'tetryon')            // types without clearing first
->clear('Email')
->pressKey('Enter');                   // named keys: Enter, Tab, Escape, ArrowDown, ...
```

`fill` / `type` / `clear` work on `<input>`/`<textarea>` **and**
`contenteditable` editors (rich-text fields), and `value()` reads either back.
On anything else they throw `UndrivableElementException` rather than silently
no-opping.

`pressKey` sends to the focused element. Named keys (`Enter`, `Tab`, `Escape`,
`Backspace`, `Delete`, `ArrowUp`/`Down`/`Left`/`Right`, `Home`, `End`,
`PageUp`/`PageDown`, …) are translated to the right key codes; a single
character is sent literally.

## Forms

```php
->select('Country', 'United Kingdom') // <select>: matches the option label or value
->selectByValue('Country', 'uk')      // match the option value only
->check('Remember me')                // checkbox: ensures it is checked
->uncheck('Remember me')
->choose('plan', 'pro')               // radio group by name + value
->upload('Avatar', __DIR__.'/fixtures/avatar.png');
```

`select()` matches an option by its **visible label or its value** — handy when
values are opaque ids. Use `selectByValue()` to match the value only. Either
throws `OptionNotFoundException` if no option matches, rather than silently
selecting nothing.

These verbs drive **native** form controls. If one resolves an element it can't
drive — `fill()` on a `<div contenteditable>`, `select()` on a custom dropdown
that isn't a `<select>`, `check()` on something that isn't a checkbox — it
throws `UndrivableElementException` naming the element, instead of silently
doing nothing and failing later at an unrelated assertion. Drive a custom widget
by composing `click()` with `evaluate()` (or `within()` + `click()`).

## Reading values

```php
$email = $this->browser()->value('Email');
```

## Escape hatch: evaluate()

When the fluent verbs don't reach something, run JavaScript in the page
directly. Promises are awaited, so an async IIFE resolves to its value:

```php
$title  = $this->browser()->evaluate('document.title');                       // mixed
$status = $this->browser()->evaluate(
    '(async () => (await fetch("/__test__/login", {method:"POST"})).status)()'
);
$this->browser()->evaluate('window.localStorage.setItem("flag", "1")');
```

`evaluate()` is state, not an action — it does not auto-wait. Reach for it for
in-page setup the verbs don't model — though for cookies prefer the
[cookie API](#cookies) below, and for everything the verbs cover, the verbs.

For the rare case a custom base class needs the driver primitives directly,
`InteractsWithBrowser` exposes a `protected driver(): FirefoxBiDiDriver`
accessor (it boots the browser if it hasn't started) — no reflection needed.

### Waiting on and asserting page state

`evaluate()` is a one-shot read; these add the **wait** and **assert**
counterparts with the same auto-wait/retry contract as the DOM assertions —
for state the page knows but doesn't render as text (store readiness, a derived
total, a chart library's dataset):

```php
$this->browser()
    ->waitForExpression('window.store.state.status === "loaded"')  // poll until truthy
    ->assertExpression('window.chart.data.datasets[0].data.length === 12')
    ->assertExpressionEquals('window.store.getters.total', 1999);  // diffs expected vs actual
```

A probe only reaches what's reachable from the page's global scope: anything
DOM-derived and any library on `window` work as-is, but deeply-bundled framework
internals (a Vue component's private state, a chart instance not on `window`)
need the app to opt in by exposing them — e.g. `window.__appState__ = …`.
Tetryon provides the probe; deciding what internal state to expose is the app's
call. This covers "is the **data** right"; pixel-level visual correctness is out
of scope for a DOM/JS tool.

## Cookies

Seed the cookie state a test depends on — feature flags, locale, consent
banners, A/B buckets, or a session token — before exercising the UI. Backed by
WebDriver BiDi storage rather than `document.cookie`, so **HttpOnly cookies work**
and a cookie set before the first `visit()` is carried by that request.

```php
$this->browser()
    ->setCookie('feature_flags', $value)                       // domain/path inferred from base_url
    ->setCookie('session', $token, ['httpOnly' => true, 'sameSite' => 'Lax'])
    ->visit('/');                                              // first request already carries them

$this->browser()->cookie('feature_flags');   // ?string — null if unset
$this->browser()->deleteCookie('feature_flags');
$this->browser()->clearCookies();
```

The domain defaults to the base-URL host and the path to `/`. Override with the
options array: `domain`, `path`, `secure`, `httpOnly`, `sameSite`, `expiry`.
Set/delete/clear are fluent; `cookie()` returns the value. No auto-wait — it's
state, not an actionable element.

> **Encrypted cookies.** Some frameworks (Laravel among them) encrypt cookies,
> so a plaintext `setCookie('name', '1')` may be rejected on decrypt unless the
> cookie name is excluded from encryption. That's the application's concern, not
> Tetryon's.

This is orthogonal to `loginAs()`, which establishes auth via a server-side
session route. Use cookies for the state `loginAs()` doesn't cover — flags,
routing, locale, banners.

## A complete flow

```php
$this->browser()
    ->visit('/signup')
    ->fill('Email', 'bryan@example.com')
    ->fill('Password', 'hunter2')
    ->select('Country', 'uk')
    ->check('Accept terms')
    ->press('Create account')
    ->assertSee('Welcome');
```
