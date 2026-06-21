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
