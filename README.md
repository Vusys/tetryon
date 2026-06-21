# Tetryon

> PHPUnit. Firefox. WebDriver BiDi. Composer install. Useful errors. No faff.

**Tetryon** is a PHP-native browser testing package for PHPUnit. Write browser
tests that are readable, reliable, and easy to run locally or in CI — without
Node, Playwright, Selenium Server, ChromeDriver, or Dusk-style setup pain.

It is **Firefox-first** and **PHPUnit-first**. You run your app however you
like; Tetryon drives the browser.

> [!NOTE]
> **Status: 0.1.0 — beta, pre-1.0.** The API below ships and is exercised
> against real Firefox on Linux and macOS in CI — including a real Vue 3
> single-page app — but it may still change before 1.0. See
> [compatibility](docs/compatibility.md).

## The test you want to write

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
            ->check('Remember me')
            ->press('Log in')
            ->assertSee('Dashboard');
    }
}
```

Run it through PHPUnit — Tetryon does not ship a competing test runner:

```bash
vendor/bin/phpunit --testsuite Browser
```

## What works today

- **A fluent, readable API** — navigation, clicks, typing, forms, pointer and
  keyboard actions, and assertions, all driven by human labels rather than CSS.
- **A selector engine** — `'Email'` / `'Save changes'` resolve via test
  attributes → label → accessible name → placeholder → button/link text → name
  → id → visible text, with `@`/CSS/XPath escape hatches.
- **Auto-waiting** — every action waits for its target to be actionable; every
  assertion retries until timeout. Never a `sleep()`.
- **Failure diagnostics** — on failure, a screenshot, page HTML, console log,
  BiDi command trace, and browser stderr land in an artifact directory, with a
  readable report pointing at them.
- **`tetryon doctor`** — a preflight CLI that launches Firefox and checks the
  whole environment.
- **Natural-language steps** — `->step('I fill "Email" with "x"')` and
  `->scenario()->given()->when()->then()`, a deterministic layer over the same
  API.
- **First-class Laravel integration** — auto-discovered provider,
  `tetryon:install`, a self-booting `Laravel\BrowserTestCase` (factories + DB),
  and `loginAs()`.

It's proven against a real Vue 3 SPA — reactivity, async rendering, form
validation, and client-side routing — with no manual waits.

## A quick look

Selectors, auto-wait, and assertions:

```php
$this->browser()
    ->visit('/settings')
    ->fill('Display name', 'Bryan')
    ->check('Email notifications')
    ->press('Save changes')
    ->assertSee('Saved');           // retries until it appears
```

Laravel, with a factory and login:

```php
use App\Models\User;
use Vusys\Tetryon\Laravel\BrowserTestCase;

final class DashboardTest extends BrowserTestCase
{
    public function test_a_user_sees_their_dashboard(): void
    {
        $user = User::factory()->create();

        $this->loginAs($user)
            ->visit('/dashboard')
            ->assertSee('Welcome back');
    }
}
```

Natural language:

```php
$this->scenario()
    ->given('I am on "/login"')
    ->when('I fill "Email" with "bryan@example.com"')
    ->and('I press "Log in"')
    ->then('I should see "Dashboard"');
```

## Design pillars

- **PHPUnit only.** Supports PHPUnit 12 and 13. Normal PHPUnit tests — PHPUnit
  owns discovery, filtering, failures, grouping, exit codes, and CI behaviour.
- **Firefox first.** Firefox stable (and ESR where practical) on macOS and
  Linux. No Chrome/Edge/Safari/Windows/mobile in v1.
- **WebDriver BiDi.** Direct browser control with minimal moving parts. No
  Node, no Selenium Server, no ChromeDriver, no CDP as a core protocol.
- **Bring your own server.** Start your app with `php artisan serve`,
  `symfony serve`, `docker compose up`, `npm run dev`, whatever — point Tetryon
  at the base URL.
- **Auto-waiting by default.** You should rarely write a manual wait, never a
  `sleep()`.
- **Failure diagnostics as a feature.** Bad errors kill browser-testing
  adoption; good errors are the product.

## Engineering standards

- **PHPStan level 9**, no baseline, no inline `@phpstan-ignore`.
- **Laravel Pint** for code style, **Rector** as a CI gate, **Infection**
  mutation testing.
- **CI** across PHP 8.4 / 8.5 and PHPUnit 12 / 13, plus a real-Firefox browser
  suite on **Linux and macOS**.
- **OpenSSF Scorecard**, Dependabot, and pinned-SHA GitHub Actions.

See [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`CLAUDE.md`](CLAUDE.md).

## Documentation

Full docs live in [`docs/`](docs/README.md):
[installation](docs/installation.md) ·
[writing tests](docs/writing-tests.md) ·
[selectors](docs/selectors.md) ·
[interactions](docs/interactions.md) ·
[natural language](docs/natural-language.md) ·
[waiting](docs/waiting.md) ·
[assertions](docs/assertions.md) ·
[configuration](docs/configuration.md) ·
[diagnostics](docs/diagnostics.md) ·
[Laravel](docs/laravel.md) ·
[CI](docs/ci.md) ·
[troubleshooting](docs/troubleshooting.md).

## Roadmap

| Milestone | Status | Theme |
| --------- | ------ | ----- |
| v0.1 | ✓ | Firefox protocol spike — direct WebDriver BiDi control |
| v0.2 | ✓ | PHPUnit alpha — fluent API, auto-wait, failure artifacts, `doctor` |
| v0.3 | ✓ | Natural-language steps |
| v0.4 | ✓ | Laravel integration — provider, `tetryon:install`, `loginAs()` |
| v0.5 | … | CI hardening — GitHub Actions, GitLab, Docker, parallelism |
| v0.6 | | Stabilisation & docs |

## License

MIT — see [`LICENSE`](LICENSE).
