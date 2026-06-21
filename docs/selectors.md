# Selectors

Tetryon tests should read like user behaviour. Instead of CSS, you name things
the way a person would:

```php
->fill('Email', 'bryan@example.com')
->press('Save changes')
->click('Settings')
```

When you pass a human target like `'Email'` or `'Save changes'`, Tetryon tries
a series of strategies **in order** and uses the first match. If several nodes
match (for example a `<label>` and the input it labels), the form control is
preferred over the label.

## Resolution order

1. **Explicit selector** (see below).
2. **Test attributes** — `data-testid`, `data-test`, `data-cy`.
3. **Label** — `<label>Email</label>` associated with a control (via `for`/`id`
   or by wrapping).
4. **Accessible name** — `aria-label`, etc.
5. **Placeholder** — `placeholder="you@example.com"`.
6. **Button text** — `<button>Save changes</button>` or an `<input type="submit"
   value="...">`.
7. **Link text** — `<a>Settings</a>`.
8. **Name** — `name="email"`.
9. **Id** — `id="email"` (when the target is a valid identifier).
10. **Visible text** — any element whose text matches.

The configured test attributes are the most robust and the recommended way to
target elements you control. See [Configuration](configuration.md) to change
the list.

## Explicit selectors

Prefix or shape the target to bypass the human-text resolution:

| Form | Matches |
| --- | --- |
| `@save-button` | the first configured test attribute, i.e. `[data-testid="save-button"]` |
| `#save` | CSS id |
| `.btn-primary` | CSS class |
| `[data-role="dialog"]` | CSS attribute selector |
| `//button[@type='submit']` | XPath |

```php
->click('@save-button')
->click('[data-testid="save-button"]')
->click('#save');
```

## When nothing matches

If no element resolves, Tetryon throws an `ElementNotFoundException` that lists
every strategy it tried and how many nodes each matched — the raw material for
the [failure report](diagnostics.md). Actions also wait for the element to
appear before giving up; see [Waiting](waiting.md).
