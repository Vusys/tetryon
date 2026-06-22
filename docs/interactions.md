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

## Typing

```php
->fill('Email', 'bryan@example.com')   // clears, then types
->type('Search', 'tetryon')            // types without clearing first
->clear('Email')
->pressKey('Enter');                   // named keys: Enter, Tab, Escape, ArrowDown, ...
```

`pressKey` sends to the focused element. Named keys (`Enter`, `Tab`, `Escape`,
`Backspace`, `Delete`, `ArrowUp`/`Down`/`Left`/`Right`, `Home`, `End`,
`PageUp`/`PageDown`, …) are translated to the right key codes; a single
character is sent literally.

## Forms

```php
->select('Country', 'uk')      // <select>: matches the option value
->check('Remember me')         // checkbox: ensures it is checked
->uncheck('Remember me')
->choose('plan', 'pro')        // radio group by name + value
->upload('Avatar', __DIR__.'/fixtures/avatar.png');
```

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
in-page setup the verbs don't model, and the fluent verbs for everything they
cover.

For the rare case a custom base class needs the driver primitives directly,
`InteractsWithBrowser` exposes a `protected driver(): FirefoxBiDiDriver`
accessor (it boots the browser if it hasn't started) — no reflection needed.

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
