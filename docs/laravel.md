# Laravel

Tetryon ships an optional, auto-discovered Laravel integration. The core never
requires Laravel — these pieces only load in a Laravel app.

## Install

```bash
composer require --dev vusys/tetryon
php artisan tetryon:install
```

`tetryon:install` publishes `config/tetryon.php` and scaffolds
`tests/Browser/ExampleTest.php` (plus a gitignore for the artifacts directory).

## Configuration

`config/tetryon.php` is the published config — `base_url` (falls back to
`APP_URL`), `headless`, `firefox_binary`, `timeout`, `viewport`, `artifacts`,
and `selectors`, all reading from env. See [Configuration](configuration.md).

## Writing tests

Extend `Vusys\Tetryon\Laravel\BrowserTestCase`. It boots the application — so
factories, the database, and Laravel's testing traits are available — and wires
`$this->browser()` from `config('tetryon')`.

```php
namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Vusys\Tetryon\Laravel\BrowserTestCase;

final class DashboardTest extends BrowserTestCase
{
    use RefreshDatabase;

    public function test_a_user_sees_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->loginAs($user)
            ->visit('/dashboard')
            ->assertSee('Welcome back');
    }
}
```

## Serving the app

You run the app; Tetryon drives the browser. In another terminal:

```bash
php artisan serve          # or: php artisan tetryon:serve
```

Point `base_url` at it (default `http://127.0.0.1:8000`), then:

```bash
php artisan test --testsuite=Browser
```

## `loginAs()`

`$this->loginAs($user)` authenticates the user in the **browser's** session
(the browser hits a separately-served app, so logging in inside the test
process is not enough). It works via a session-aware route
(`/_tetryon/login/{id}/{guard?}`) that the service provider registers **only in
the local/testing environment** — it can never authenticate a real request in
production.

```php
$this->loginAs($user, 'web')->visit('/account')->assertSee($user->name);
```

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan tetryon:install` | Publish config + scaffold a browser test. |
| `php artisan tetryon:doctor` | Preflight the environment (see [Diagnostics](diagnostics.md)). |
| `php artisan tetryon:serve` | Serve the app (wraps `artisan serve`). |
