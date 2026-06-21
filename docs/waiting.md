# Waiting

Auto-waiting is the contract. You should never write `sleep()` in a Tetryon
test.

## What waits, automatically

- **Every action** (`click`, `fill`, `select`, …) first waits for its target to
  exist and be **actionable** — visible, not `display:none` / `visibility:hidden`,
  and not `disabled`. So clicking a button that appears 400ms after an AJAX call
  just works.
- **Every assertion** (`assertSee`, `assertVisible`, `assertValue`, …) retries
  until it passes or the timeout elapses. A delayed-rendered "Dashboard" heading
  is asserted without any explicit wait.

```php
$this->browser()
    ->visit('/dashboard')
    ->press('Load report')      // waits for the button to be clickable
    ->assertSee('Quarterly revenue'); // retries until the report renders
```

## Explicit waits

When you need to wait for something that isn't tied to your next assertion, use
an explicit wait. These **throw** a `TimeoutException` if the condition never
holds:

```php
->waitForText('Loaded')
->waitUntilMissing('Loading…')
->waitForUrl('/dashboard')      // resolved against base_url
->waitForLocation('/dashboard') // path only
```

## Timeouts

Timeouts are configured in milliseconds and default to:

| Setting | Default | Used by |
| --- | --- | --- |
| `default` | 5000 | actions and element resolution |
| `assertion` | 5000 | assertion retries |
| `navigation` | 15000 | `waitForUrl` / `waitForLocation` |

Override them via config (see [Configuration](configuration.md)):

```php
'timeout' => [
    'default' => 5000,
    'navigation' => 15000,
    'assertion' => 5000,
],
```
