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
