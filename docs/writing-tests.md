# Writing tests

Tetryon tests are normal PHPUnit tests. PHPUnit owns discovery, filtering, failures, grouping, and exit codes; Tetryon owns the browser.

## Bring your own server

Tetryon never serves your app — you start it however you like, then point Tetryon at the URL:

```bash
php artisan serve            # Laravel
symfony serve                # Symfony
php -S 127.0.0.1:8000 -t public
docker compose up
npm run dev
```

Tell Tetryon the base URL with the `TETRYON_BASE_URL` environment variable (default `http://127.0.0.1:8000`). See [Configuration](configuration.md).

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

`$this->browser()` launches a fresh, isolated Firefox the first time it is called in a test, and the browser is torn down automatically after the test.

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

Every test that calls `browser()` gets its **own** Firefox instance with a fresh temporary profile — fresh cookies, local storage, and session storage, with no leakage between tests. The profile is deleted on teardown.

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

## Reusing one browser across a class

A fresh Firefox per test is the safest default, but each launch costs a second or two, and logging in costs more. To launch once per test class instead, mark the class with `#[ReuseBrowser]`, or use the `ReusesBrowser` trait if you prefer. Put whatever every test should start from in `authenticate()`:

```php
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\ReuseBrowser;

#[ReuseBrowser]
final class DashboardTest extends BrowserTestCase
{
    protected function authenticate(Browser $browser): void
    {
        $this->loginAs(User::where('email', 'admin@example.com')->firstOrFail());
    }

    public function test_admin_sees_the_dashboard(): void
    {
        $this->browser()->visit('/dashboard')->assertSee('Welcome back');
    }
}
```

- **When it runs:** `authenticate()` runs once, right after the class's browser launches. The browser's cookies are snapshotted the moment it returns.
- **Before every test:** the cookies are restored to that snapshot, the console, network and dialog history is cleared, and the browser is left on `about:blank`. A test can't leave state behind for the next one.
- **If the browser dies:** a browser that stops answering is relaunched, and `authenticate()` runs again.
- **Storage:** `#[ReuseBrowser(storage: true)]` also snapshots the origin's `localStorage` and `sessionStorage`. The trait form is `snapshotsBrowserStorage()` returning `true`. Restoring storage needs a page on the origin, so each test then starts with one visit to the base URL.
- **Teardown:** the browser is stopped after the class's last test.

### Checkpointing the database (Laravel)

With `Vusys\Tetryon\Laravel\BrowserTestCase`, `#[ReuseBrowser(database: true)]` (or `snapshotsDatabase()` returning `true` on the trait) also checkpoints the database. A class can then seed and log in once:

```php
#[ReuseBrowser(database: true)]
final class ChartTest extends BrowserTestCase
{
    use DatabaseTruncation;

    protected function authenticate(Browser $browser): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->loginAs(User::where('email', 'admin@example.com')->firstOrFail());
    }
}
```

- **Timing:** in this mode the browser starts in `setUp()`, after `DatabaseTruncation` has run. The first test runs `authenticate()` there, and the database is checkpointed when it returns. Every later test's `setUp()` puts the checkpoint back before the browser is restored, so nothing a test writes reaches the next test, and nothing it writes before calling `browser()` is lost.
- **SQLite:** checkpointed with `VACUUM INTO` a temp file, and restored from it table by table.
- **MySQL and MariaDB:** shadow copies of each table in the same database (`tetryon_cp_*`), restored with foreign keys off and dropped after the class.
- **Who restores:** the test process does the restore on its own connection, and the writes are committed, so the served app sees them on its next request.
- **What's restored:** table contents only. A table created after the checkpoint, or a schema change, is left as it is.
- **Speed:** in a real app, a three-test class that seeded in every `setUp()` went from 81–114s to 46–47s.

Limits:

- **Not reset:** service workers, IndexedDB, caches and permissions. Leave reuse off for a test that needs those pristine.
- **The user must outlive the class.** The snapshot holds a session for whoever `authenticate()` logged in. With `DatabaseTruncation`, keep that user's table out of the truncation (`protected $exceptTables = ['users'];`), or log in a user your seeders create for every test.
- **The base URL is fixed for the class.** It's taken from the first test's configuration.
