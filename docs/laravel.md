# Laravel

Tetryon ships an optional, auto-discovered Laravel integration. The core never requires Laravel — these pieces only load in a Laravel app.

## Install

```bash
composer require --dev vusys/tetryon
php artisan tetryon:install
```

`tetryon:install` publishes `config/tetryon.php` and scaffolds `tests/Browser/ExampleTest.php` (plus a gitignore for the artifacts directory).

## Configuration

`config/tetryon.php` is the published config — `base_url` (falls back to `APP_URL`), `headless`, `firefox_binary`, `timeout`, `viewport`, `artifacts`, `selectors`, and `server` (see [Parallel browser tests](#parallel-browser-tests)), all reading from env. See [Configuration](configuration.md).

## Writing tests

Extend `Vusys\Tetryon\Laravel\BrowserTestCase`. It boots the application — so factories, the database, and Laravel's testing traits are available — and wires `$this->browser()` from `config('tetryon')`.

```php
namespace Tests\Browser;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Vusys\Tetryon\Laravel\BrowserTestCase;

final class DashboardTest extends BrowserTestCase
{
    use DatabaseTruncation;

    public function test_a_user_sees_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->loginAs($user)
            ->visit('/dashboard')
            ->assertSee('Welcome back');
    }
}
```

## The database: truncate, don't roll back

The browser drives a separately served app with its own database connection, so it only ever sees **committed** rows. `RefreshDatabase` and `DatabaseTransactions` wrap each test in a transaction that is rolled back, and the served app never sees what the test wrote. `BrowserTestCase` refuses them with an error that says so. Use `DatabaseTruncation` (or `DatabaseMigrations`), and point the test process and the served app at the same database. A file-based SQLite database works well.

## Serving the app

You run the app; Tetryon drives the browser. In another terminal:

```bash
php artisan serve          # or: php artisan tetryon:serve
```

Point `base_url` at it (default `http://127.0.0.1:8000`), then:

```bash
php artisan test --testsuite=Browser
```

## Parallel browser tests

Under `php artisan test --parallel` (paratest), every worker needs its own app server and database: a shared server would serialise the workers, and its database wouldn't be the worker's. Let Tetryon run the server:

```php
// config/tetryon.php
'server' => [
    'managed' => env('TETRYON_MANAGED_SERVER', false),
    'command' => 'php artisan serve --host={host} --port={port} --no-reload',
    'host' => '127.0.0.1',
    'base_port' => 8100,
    'ready_path' => '/',
    'ready_timeout' => 15000,
    // Laravel's parallel testing gives worker N the database "<name>_test_N".
    'env' => ['DB_DATABASE' => database_path('tetryon.sqlite').'_test_{token}'],
    // Where the command runs; null = the app's base path.
    'working_directory' => null,
],
```

With `TETRYON_MANAGED_SERVER=true`, each process starts its own server on the first `browser()` call. It listens on `base_port + TEST_TOKEN`, so `8100` outside a parallel run and `8103` for worker 3. Tetryon waits until `ready_path` answers, uses it in place of `base_url`, reuses it for every test in that process, and stops it when the process exits.

The server inherits the worker's environment. `env` adds to it, with `{token}`, `{port}` and `{host}` replaced, which is how a worker's server reaches the worker's database. Laravel's `ParallelTesting` creates and migrates `<name>_test_N` in the test process; Tetryon never creates databases itself. Laravel names that database only under `--parallel`, so turn the managed server on for parallel runs, for example `TETRYON_MANAGED_SERVER=true php artisan test --parallel`.

With a file-based SQLite database, create the worker's file first. Laravel creates a missing test database only after a `QueryException`, and a missing SQLite file throws something else:

```php
public static function setUpBeforeClass(): void
{
    parent::setUpBeforeClass();

    // The app hasn't booted yet, so no database_path(): this is tests/Browser/.
    $token = getenv('TEST_TOKEN');
    if (is_string($token) && $token !== '') {
        touch(dirname(__DIR__, 2).'/database/tetryon.sqlite_test_'.$token);
    }
}
```

Each worker runs a PHP server and a Firefox, so the number of workers is bounded by memory. A handful of workers is realistic; dozens aren't.

## `loginAs()`

`$this->loginAs($user)` authenticates the user in the **browser's** session (the browser hits a separately-served app, so logging in inside the test process is not enough). It works via a session-aware route (`/_tetryon/login/{id}/{guard?}`) that the service provider registers **only in the local/testing environment** — it can never authenticate a real request in production.

```php
$this->loginAs($user, 'web')->visit('/account')->assertSee($user->name);
```

## Commands

| Command | Purpose |
| --- | --- |
| `php artisan tetryon:install` | Publish config + scaffold a browser test. |
| `php artisan tetryon:doctor` | Preflight the environment (see [Diagnostics](diagnostics.md)). |
| `php artisan tetryon:serve` | Serve the app (wraps `artisan serve`). |
