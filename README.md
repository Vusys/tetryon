# Tetryon

> PHPUnit. Firefox. WebDriver BiDi. Composer install. Useful errors. No faff.

**Tetryon** is a PHP-native browser testing package for PHPUnit. It lets PHP
developers write browser tests that are readable, reliable, and easy to run
locally or in CI — without Node, Playwright, Selenium Server, ChromeDriver, or
Dusk-style setup pain.

It is **Firefox-first** and **PHPUnit-first**. You run your app however you
like; Tetryon drives the browser.

> [!WARNING]
> **Status: pre-alpha (v0.1 — Firefox protocol spike).** Nothing here is
> released yet. The public API below is the target design, not a shipped
> contract. Follow the [milestones](https://github.com/Vusys/tetryon/milestones)
> for progress.

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
            ->press('Log in')
            ->assertSee('Dashboard');
    }
}
```

Run it through PHPUnit — Tetryon does not ship a competing test runner:

```bash
vendor/bin/phpunit --testsuite Browser
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
- **Auto-waiting by default.** Every action waits until the target is
  actionable; every assertion retries until timeout. You should rarely write a
  manual wait, never a `sleep()`.
- **Failure diagnostics as a feature.** On failure, capture a screenshot, page
  HTML, current URL, console logs, last actions, and the full selector
  resolution trace. Bad errors kill browser-testing adoption; good errors are
  the product.

## Engineering standards

Tetryon is built with the same gates as
[`vusys/laravel-nestedset`](https://github.com/Vusys/laravel-nestedset) from
day one:

- **PHPStan level 9**, no baseline, no inline `@phpstan-ignore`.
- **Laravel Pint** for code style.
- **Rector** (dry-run gate in CI).
- **Infection** mutation testing.
- **CI matrix** across PHP 8.4 / 8.5 and PHPUnit 12 / 13.
- **OpenSSF Scorecard**, Dependabot, and pinned-SHA GitHub Actions.

See [`CONTRIBUTING.md`](CONTRIBUTING.md) and [`CLAUDE.md`](CLAUDE.md).

## Roadmap

| Milestone | Theme |
| --------- | ----- |
| v0.1 | Firefox protocol spike — prove direct WebDriver BiDi control |
| v0.2 | PHPUnit alpha — `BrowserTestCase`, fluent API, auto-wait, failure artifacts, `doctor` |
| v0.3 | Natural-language steps (`->step(...)` / `->scenario()`), deterministic grammar |
| v0.4 | Laravel integration — service provider, `tetryon:install`, optional `loginAs()` |
| v0.5 | CI hardening — GitHub Actions / GitLab / Docker recipes, parallelism |
| v1.0 | Firefox-only stable — PHPUnit 12/13 on macOS and Linux |

## License

MIT — see [`LICENSE`](LICENSE).
