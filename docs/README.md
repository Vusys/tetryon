# Tetryon documentation

PHP-native browser testing for PHPUnit. Firefox-first, WebDriver BiDi — no
Node, Selenium, ChromeDriver, or Dusk-style setup.

> **Status: pre-alpha.** The API below ships and is exercised against real
> Firefox in CI (Linux + macOS), but nothing is tagged yet and breaking changes
> are expected before 1.0.

## Start here

- [Installation](installation.md) — Composer, Firefox, the `doctor` check.
- [Writing tests](writing-tests.md) — `BrowserTestCase`, the fluent API, the
  anatomy of a test.

## Reference

- [Selectors](selectors.md) — how a human target ("Email", "Save") resolves to
  an element, and the explicit escape hatches.
- [Interactions](interactions.md) — navigation, clicking, typing, forms.
- [Natural-language steps](natural-language.md) — `->step()` and `->scenario()`.
- [Waiting](waiting.md) — auto-waiting, explicit waits, timeouts.
- [Assertions](assertions.md) — the `assert*` methods.
- [Configuration](configuration.md) — base URL, timeouts, viewport, artifacts.
- [Diagnostics](diagnostics.md) — failure artifacts, `tetryon doctor`, logging.
- [Continuous integration](ci.md) — running the Browser suite in CI.
- [Troubleshooting](troubleshooting.md) — common problems.

## The shape of a test

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

```bash
vendor/bin/phpunit --testsuite Browser
```
