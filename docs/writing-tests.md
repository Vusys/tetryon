# Writing tests

Tetryon tests are normal PHPUnit tests. PHPUnit owns discovery, filtering,
failures, grouping, and exit codes; Tetryon owns the browser.

## Bring your own server

Tetryon never serves your app — you start it however you like, then point
Tetryon at the URL:

```bash
php artisan serve            # Laravel
symfony serve                # Symfony
php -S 127.0.0.1:8000 -t public
docker compose up
npm run dev
```

Tell Tetryon the base URL with the `TETRYON_BASE_URL` environment variable
(default `http://127.0.0.1:8000`). See [Configuration](configuration.md).

## The base class

Extend `BrowserTestCase` and call `$this->browser()`:

```php
use Vusys\Tetryon\PHPUnit\BrowserTestCase;

final class LoginTest extends BrowserTestCase
{
    public function test_guest_can_log_in(): void
    {
        $this->browser()
            ->visit('/login')
            ->fill('Email', 'bryan@example.com')
            ->fill('Password', 'password')
            ->press('Log in')
            ->assertSee('Dashboard');
    }
}
```

`$this->browser()` launches a fresh, isolated Firefox the first time it is
called in a test, and the browser is torn down automatically after the test.

## The trait (escape hatch)

If you can't extend `BrowserTestCase`, use the trait on any `TestCase`:

```php
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\PHPUnit\InteractsWithBrowser;

final class LoginTest extends TestCase
{
    use InteractsWithBrowser;

    public function test_guest_sees_login(): void
    {
        $this->browser()->visit('/login')->assertSee('Sign in');
    }
}
```

The base class is the recommended path; the trait is the escape hatch.

## Isolation

Every test that calls `browser()` gets its **own** Firefox instance with a
fresh temporary profile — fresh cookies, local storage, and session storage,
with no leakage between tests. The profile is deleted on teardown.

## The fluent API at a glance

```php
$this->browser()
    // navigation
    ->visit('/path')->back()->forward()->refresh()

    // interaction (auto-waited)
    ->click('Settings')
    ->press('Save changes')
    ->fill('Email', 'a@b.com')
    ->type('Search', 'tetryon')
    ->clear('Email')
    ->select('Country', 'uk')
    ->check('Remember me')->uncheck('Remember me')
    ->choose('plan', 'pro')          // radio
    ->upload('Avatar', '/path/to/file.png')
    ->doubleClick('Cell')->rightClick('Row')->hover('Menu')
    ->pressKey('Enter')

    // assertions (retry until they pass)
    ->assertSee('Dashboard')
    ->assertDontSee('Exception')
    ->assertVisible('Save')
    ->assertMissing('Spinner')
    ->assertTitleIs('Home')
    ->assertPathIs('/dashboard')
    ->assertValue('Email', 'a@b.com')

    // explicit waits (throw on timeout)
    ->waitForText('Loaded')
    ->waitUntilMissing('Loading')
    ->waitForUrl('/dashboard');
```

Read on:

- [Selectors](selectors.md) — what `'Email'` and `'Save changes'` match.
- [Interactions](interactions.md) — every action verb in detail.
- [Waiting](waiting.md) — why you never write `sleep()`.
- [Assertions](assertions.md) — every `assert*` method.
