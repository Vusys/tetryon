# Interactions

Every action resolves its target (see [Selectors](selectors.md)) and waits for the element to be **actionable** — present, visible, and enabled — before acting. You never need a manual wait.

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

`click()` / `press()` prefer an **interactive** target (button, link, input, …) when more than one element matches — so pressing `'Log in'` clicks the submit button, not a heading that happens to share its text. When nothing interactive matches, the first match still wins, so a `<div>` with a click handler is fine.

### Drag and drop

```php
->drag('@card-1', '@column-done')   // element onto element
->dragTo('@card-1', 320, 480)       // element to absolute viewport coordinates
->dragDown('@row-3', 120)           // by a pixel offset; also dragUp/dragLeft/dragRight
```

The drag issues intermediate pointer moves (not a single jump), so pointer-drag libraries like Sortable.js / vuedraggable register the gesture and compute direction. This drives **pointer-based** DnD; HTML5-native `draggable` drag/drop events are out of scope.

## Scrolling

```php
->scrollTo('Load more')   // centre the target, scrolling any scrollable ancestor
->scrollToBottom()
->scrollToTop();
```

Actions scroll on their own — `click()` brings its target into view, including out of an `overflow: auto` pane, and moves it clear of a fixed header or footer if one covers the click point. These verbs are for when the scroll *is* the behaviour under test: an infinite scroller, a scroll-spy nav, a list that lazy-loads as you reach it. `scrollTo()` waits for the element to exist but not to be clickable, so it can reach a region that has not rendered its controls yet.

## Typing

```php
->fill('Email', 'bryan@example.com')   // clears, then types
->type('Search', 'tetryon')            // types without clearing first
->clear('Email')
->pressKey('Enter');                   // named keys: Enter, Tab, Escape, ArrowDown, ...
```

`fill` / `type` / `clear` work on `<input>`/`<textarea>` **and** `contenteditable` editors (rich-text fields), and `value()` reads either back. On anything else they throw `UndrivableElementException` rather than silently no-opping.

`pressKey` sends to the focused element. Named keys (`Enter`, `Tab`, `Escape`, `Backspace`, `Delete`, `ArrowUp`/`Down`/`Left`/`Right`, `Home`, `End`, `PageUp`/`PageDown`, …) are translated to the right key codes; a single character is sent literally.

### Committing on blur

```php
->fill('Display name', 'Ada')
->blur()                        // blur the focused field — commits a save-on-blur input
->assertSee('Saved');

->blur('Display name');         // or blur a specific target
```

`blur()` drives "commit on blur" flows — inline edits, validate-on-blur fields, autosave — so you don't have to fake it with `pressKey('Tab')`. With no argument it blurs the currently-focused element; pass a target to blur that one.

It works in **headless** Firefox, which is the catch: headless never treats its window as focused (`document.hasFocus()` is `false`), so it silently drops every `blur`/`focusout` event — a real blur would commit nothing. `blur()` dispatches the events itself when the browser didn't, so the handler runs exactly once headless or headed. The limit is what Tetryon can't see: if the **application** commits by calling the field's own `blur()` internally (some frameworks do this on `Enter`), that internal blur is still dropped headless. Run those cases headed — locally with `TETRYON_HEADLESS=false`, or under a virtual display in CI (see [Continuous integration](ci.md)).

## Forms

```php
->select('Country', 'United Kingdom') // <select>: matches the option label or value
->selectByValue('Country', 'uk')      // match the option value only
->check('Remember me')                // checkbox: ensures it is checked
->uncheck('Remember me')
->choose('plan', 'pro')               // radio group by name + value
->upload('Avatar', __DIR__.'/fixtures/avatar.png');
```

`select()` matches an option by its **visible label or its value** — handy when values are opaque ids. Use `selectByValue()` to match the value only. Either throws `OptionNotFoundException` if no option matches, rather than silently selecting nothing.

### Custom dropdowns (comboboxes)

`select()` only drives a native `<select>`. Component libraries usually render a custom searchable dropdown instead — the WAI-ARIA pattern of a trigger (or `role="combobox"` input) revealing a `role="listbox"` of `role="option"` elements. `chooseFromDropdown()` drives that: it opens the control and clicks the option by its visible text.

```php
->chooseFromDropdown('Fruit', 'Banana');   // open, then pick the option

->fill('Fruit', 'Ban')                     // type-to-filter comboboxes: narrow first,
->chooseFromDropdown('Fruit', 'Banana');   // then pick
```

The option is matched globally by `role="option"`, so it works even when the list is portalled to the end of `<body>`.

These verbs drive **native** form controls (except `chooseFromDropdown()`, which is for custom ones). If one resolves an element it can't drive — `fill()` on a `<div contenteditable>`, `select()` on a custom dropdown that isn't a `<select>`, `check()` on something that isn't a checkbox — it throws `UndrivableElementException` naming the element, instead of silently doing nothing and failing later at an unrelated assertion.

## Native dialogs

`window.confirm`, `window.alert` and `window.prompt` block the page, so the answer has to be arranged **before** the action that opens the dialog — the click that triggered it doesn't complete until the dialog is gone, so there is no "after" to answer it in.

```php
$this->browser()
    ->visit('/presets')
    ->acceptDialog('Delete the preset')   // OK; optionally assert the wording
    ->press('Delete preset')
    ->assertSee('Preset deleted');

->dismissDialog()        // Cancel — the "keep it" branch of a destructive confirm
->typeInDialog('Weekly') // answers a window.prompt with text, then OK
```

After the action, the wording is still readable:

```php
->assertDialogMessage('Delete the preset')   // substring match, retries
$message = $this->browser()->dialogMessage(); // ?string
```

An answer is **one-shot** — it applies to the next dialog only. A second, unexpected dialog is still a failure rather than something silently swallowed.

### Unexpected dialogs

A dialog nobody arranged an answer for is **dismissed and reported**: the action fails immediately with `UnhandledDialogException`, naming the type and message.

```
An unhandled dialog appeared (confirm: "Delete the preset 'Nightly'?"). It was
dismissed so the session could continue. Arrange an answer before the action
that opens it: acceptDialog(), dismissDialog(), or typeInDialog("...").
```

The dismissal is what keeps the session usable — the rest of the test, and the failure artifacts, still work. `beforeunload` guards are left to the browser and accepted automatically, so navigating away from a dirty form is never blocked.

## Reading values

```php
$email = $this->browser()->value('Email');
```

## Escape hatch: evaluate()

When the fluent verbs don't reach something, run JavaScript in the page directly. Promises are awaited, so an async IIFE resolves to its value:

```php
$title  = $this->browser()->evaluate('document.title');                       // mixed
$status = $this->browser()->evaluate(
    '(async () => (await fetch("/__test__/login", {method:"POST"})).status)()'
);
$this->browser()->evaluate('window.localStorage.setItem("flag", "1")');
```

`evaluate()` is state, not an action — it does not auto-wait. Reach for it for in-page setup the verbs don't model — though for cookies prefer the [cookie API](#cookies) below, and for everything the verbs cover, the verbs.

For the rare case a custom base class needs the driver primitives directly, `InteractsWithBrowser` exposes a `protected driver(): FirefoxBiDiDriver` accessor (it boots the browser if it hasn't started) — no reflection needed.

### Waiting on and asserting page state

`evaluate()` is a one-shot read; these add the **wait** and **assert** counterparts with the same auto-wait/retry contract as the DOM assertions — for state the page knows but doesn't render as text (store readiness, a derived total, a chart library's dataset):

```php
$this->browser()
    ->waitForExpression('window.store.state.status === "loaded"')  // poll until truthy
    ->assertExpression('window.chart.data.datasets[0].data.length === 12')
    ->assertExpressionEquals('window.store.getters.total', 1999);  // diffs expected vs actual
```

A probe only reaches what's reachable from the page's global scope: anything DOM-derived and any library on `window` work as-is, but deeply-bundled framework internals (a Vue component's private state, a chart instance not on `window`) need the app to opt in by exposing them — e.g. `window.__appState__ = …`. Tetryon provides the probe; deciding what internal state to expose is the app's call. This covers "is the **data** right"; pixel-level visual correctness is out of scope for a DOM/JS tool.

## Cookies

Seed the cookie state a test depends on — feature flags, locale, consent banners, A/B buckets, or a session token — before exercising the UI. Backed by WebDriver BiDi storage rather than `document.cookie`, so **HttpOnly cookies work** and a cookie set before the first `visit()` is carried by that request.

```php
$this->browser()
    ->setCookie('feature_flags', $value)                       // domain/path inferred from base_url
    ->setCookie('session', $token, ['httpOnly' => true, 'sameSite' => 'Lax'])
    ->visit('/');                                              // first request already carries them

$this->browser()->cookie('feature_flags');   // ?string — null if unset
$this->browser()->deleteCookie('feature_flags');
$this->browser()->clearCookies();
```

The domain defaults to the base-URL host and the path to `/`. Override with the options array: `domain`, `path`, `secure`, `httpOnly`, `sameSite`, `expiry`. Set/delete/clear are fluent; `cookie()` returns the value. No auto-wait — it's state, not an actionable element.

> **Encrypted cookies.** Some frameworks (Laravel among them) encrypt cookies, so a plaintext `setCookie('name', '1')` may be rejected on decrypt unless the cookie name is excluded from encryption. That's the application's concern, not Tetryon's.

This is orthogonal to `loginAs()`, which establishes auth via a server-side session route. Use cookies for the state `loginAs()` doesn't cover — flags, routing, locale, banners.

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
