# Assertions

Every assertion retries until it passes or the assertion timeout elapses (see
[Waiting](waiting.md)), then delegates to PHPUnit — so a failure is a normal
PHPUnit failure, and a flaky-by-timing assertion just waits.

## Text

```php
->assertSee('Dashboard')        // visible page text contains the string
->assertDontSee('Exception')
->assertTextNear('Status:', 'Active'); // "Active" appears near "Status:"
```

`assertSee` / `assertDontSee` match against the page's **visible** text
(`innerText`), so hidden content is not "seen".

## Visibility

```php
->assertVisible('Save changes')  // resolves and is actually visible
->assertMissing('Loading…');     // absent, or present but hidden
```

## URL and title

```php
->assertUrlIs('/dashboard')   // full URL, resolved against base_url
->assertPathIs('/dashboard')  // path component only
->assertTitleIs('Dashboard'); // document.title
```

## Form values

```php
->assertValue('Email', 'bryan@example.com');
```

## Form-control state

`assertValue` covers an input's text; these cover the **state** of toggles and
selects — a checkbox's `value` attribute is `"on"`, not its checked state, so
these read `this.checked` / the selected option instead. Like every assertion,
they retry until they pass, so async-rendered forms need no manual wait.

```php
->assertChecked('Remember me')          // checkbox / radio is checked
->assertNotChecked('Subscribe')
->assertRadioSelected('plan', 'pro')    // radio group by name + value
->assertRadioNotSelected('plan', 'free')
->assertSelected('Country', 'uk')       // <select> has this option chosen
->assertNotSelected('Country', 'us');

// query counterparts
$browser->isChecked('Remember me');     // bool
$browser->selected('Country');          // ?string — the selected option's value
```

## Enabled / disabled and attributes

```php
->assertEnabled('Save')                 // !this.disabled
->assertDisabled('Save')
->assertAttribute('@avatar', 'src', '/img/ada.png')
->assertAttributeContains('@nav-home', 'class', 'is-active');

$browser->attribute('@nav-home', 'data-state'); // ?string — null if absent
```

`attribute()` reads the literal attribute (`href`, `src`, `title`, `data-*`,
`aria-*`, …), so `href="/x"` reads back as `/x`, not the resolved absolute URL.

## Grouping with `tap()`

`tap()` hands your callback the browser and returns it, so you can group related
assertions or extract reusable, named helpers without breaking the chain:

```php
$this->browser()
    ->visit('/dashboard')
    ->tap(function (Browser $browser) {
        $browser->assertSee('Revenue')
            ->assertVisible('Export')
            ->assertDontSee('Error');
    })
    ->press('Refresh');

// reuse a named assertion block:
$assertHeader = static fn (Browser $b) => $b->assertVisible('Logo')->assertSee('Welcome');

$this->browser()->visit('/')->tap($assertHeader);
```

## Scoping with `within()`

`within('@container', fn ($b) => …)` scopes both element resolution **and**
text assertions to inside one element — so identical elements in sibling
components are disambiguated. The outer chain continues unscoped after the
callback returns.

```php
$this->browser()
    ->visit('/users')
    ->within('@user-card-42', function (Browser $browser) {
        // 'Edit' and 'Ada' are resolved/asserted only inside this card,
        // even if other cards contain the same text.
        $browser->assertSee('Ada')->press('Edit');
    });
```

## Example

```php
$this->browser()
    ->visit('/settings')
    ->fill('Display name', 'Bryan')
    ->press('Save')
    ->assertSee('Saved')
    ->assertValue('Display name', 'Bryan')
    ->assertMissing('Unsaved changes');
```
