# Natural-language steps

Tetryon has a small, deterministic natural-language layer that lives **inside**
PHPUnit — no `.feature` files, no separate runner, no AI. Each sentence maps to
exactly one fluent call, so it is just a readable convenience over the
[canonical API](writing-tests.md).

## `->step()`

```php
$this->browser()
    ->step('I am on "/login"')
    ->step('I fill "Email" with "bryan@example.com"')
    ->step('I fill "Password" with "password"')
    ->step('I press "Log in"')
    ->step('I should see "Dashboard"');
```

## `->scenario()` (given / when / then)

The clause verbs (`given`, `when`, `and`, `but`, `then`) are interchangeable —
they read as English and do not change behaviour.

```php
$this->scenario()
    ->given('I am on "/login"')
    ->when('I fill "Email" with "bryan@example.com"')
    ->and('I fill "Password" with "password"')
    ->and('I press "Log in"')
    ->then('I should see "Dashboard"');
```

## Supported sentences

Quoted values are the arguments. Parsing is case-insensitive.

| Sentence | Maps to |
| --- | --- |
| `I am on "/path"` (also `visit`, `go to`, `open`) | `visit` |
| `I fill "Field" with "value"` | `fill` |
| `I type "value" into "Field"` | `type` |
| `I clear "Field"` | `clear` |
| `I press "Button"` | `press` |
| `I click "Target"` | `click` |
| `I check "Field"` | `check` |
| `I uncheck "Field"` | `uncheck` |
| `I select "value" from "Field"` | `select` |
| `I press the "Enter" key` | `pressKey` |
| `I should see "text"` | `assertSee` |
| `I should not see "text"` | `assertDontSee` |
| `I should be on "/path"` | `assertPathIs` |
| `the title should be "Title"` | `assertTitleIs` |

An unrecognised sentence throws an `UnknownStepException` naming the step — the
grammar is intentionally small and explicit. For anything outside it, drop back
to the [fluent API](writing-tests.md); the two mix freely.
