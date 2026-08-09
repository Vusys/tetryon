# Selectors

Tetryon tests should read like user behaviour. Instead of CSS, you name things the way a person would:

```php
->fill('Email', 'bryan@example.com')
->press('Save changes')
->click('Settings')
```

When you pass a human target like `'Email'` or `'Save changes'`, Tetryon tries a series of strategies **in order** and uses the first match. If several nodes match (for example a `<label>` and the input it labels), the form control is preferred over the label.

## Resolution order

1. **Explicit selector** (see below).
2. **Test attributes** — `data-testid`, `data-test`, `data-cy`.
3. **Label** — `<label>Email</label>` associated with a control (via `for`/`id` or by wrapping).
4. **Accessible name** — `aria-label`, etc.
5. **Placeholder** — `placeholder="you@example.com"`.
6. **Button text** — `<button>Save changes</button>` or an `<input type="submit" value="...">`.
7. **Link text** — `<a>Settings</a>`.
8. **Name** — `name="email"`.
9. **Id** — `id="email"` (when the target is a valid identifier).
10. **Visible text** — any element whose text matches.

The configured test attributes are the most robust and the recommended way to target elements you control. See [Configuration](configuration.md) to change the list.

## When several elements match

Within one strategy, resolution breaks the tie rather than blindly taking the first node in DOM order:

- a **form control** wins over its `<label>`;
- for `click()` / `press()`, an **interactive** element (button, link, input, …) wins over a non-interactive one — so pressing `'Log in'` doesn't hit a heading that shares the text;
- a **clickable** match — rendered, on-screen, and top-most at its own centre — wins over one that is hidden or covered;
- failing that, a **rendered** match wins over an unrendered one. A real option below the fold and a zero-size measurement node are both un-clickable where they sit, but only the first becomes clickable once the action scrolls to it. Rich select / tree-select widgets that keep a hidden sizer node alongside the visible options rely on this.

DOM order is the last resort, so a single match is never probed and behaves exactly as before.

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

## Shadow DOM

Web components render into shadow roots, which the browser's own `querySelector` (and WebDriver's node location) don't cross. Tetryon's resolution pierces them anyway: when a locator matches nothing in the light DOM, it retries with a matcher run inside every open shadow root. This covers the **CSS-based** strategies — explicit `#id` / `.class` / `[attr]`, a configured test attribute, `[placeholder]`, `[name]` ([#151](https://github.com/Vusys/tetryon/issues/151)) — and the **text-based** ones — label text, button text, link text, and bare visible text — via a JavaScript equivalent, since XPath can't cross shadow boundaries ([#162](https://github.com/Vusys/tetryon/issues/162)). Actionability, `assertSee()`, and `assertFocused()` are shadow-aware too, so an element nested several shadow roots deep is found, driven, read, and its focus checked.

```php
->fill('Email', 'ada@example.com')    // by placeholder, inside a shadow root
->check('Accept terms')               // by its sibling label, across shadow
->click('Go');                        // by link text
```

The one strategy that doesn't pierce is the **accessible-name** locator (it uses the platform accessibility tree). Closed shadow roots (`mode: 'closed'`) are unreachable by anything, by design.

## When nothing matches

If no element resolves, Tetryon throws an `ElementNotFoundException` that lists every strategy it tried and how many nodes each matched — the raw material for the [failure report](diagnostics.md). Actions also wait for the element to appear before giving up; see [Waiting](waiting.md).
