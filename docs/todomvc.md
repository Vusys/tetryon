# TodoMVC cross-framework compatibility

Tetryon's pitch is that a test reads like user behaviour — `fill('Email', …)`, `press('Save')` — and that the selector strategy turns that into a real element without CSS. Proving it against fixtures we wrote ourselves proves very little. [TodoMVC](https://github.com/tastejs/todomvc) is the honest test: one identical application, one shared spec, ten genuinely different DOM outputs, MIT licensed.

One shared behavioural suite (`tests/TodoMvc/`) runs against all ten. Where it passes, the selector strategy is real; where it doesn't, we get a precise, reproducible entry in the matrix below rather than a hunch.

This suite is **opt-in** and not part of the CI gate — it tracks third-party apps at a pinned upstream SHA.

```bash
composer todomvc:fetch     # sparse-checkout the ten built apps (~9.5 MB, no npm)
composer test:todomvc      # run the suite (needs Firefox)
```

## The matrix

Every cell is a behavioural scenario from upstream's `app-spec.md`, driven the way the docs sell Tetryon. ✅ passes; ⊘ is a documented skip (see the key). No cell is a silent failure — a skip is a `knownIssues` entry on the app's profile with a reason.

Apps: **es6** javascript-es6 · **rea** react · **r-r** react-redux · **vue** · **ang** angular · **sve** svelte · **pre** preact · **lit** · **jq** jquery · **bb** backbone.

| Scenario | es6 | rea | r-r | vue | ang | sve | pre | lit | jq | bb |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| app loads (framework marker) | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ |
| empty state hides main + footer | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| add clears input + counts it | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| counter pluralises | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| input trimmed / whitespace rejected | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| toggling completes + updates count | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| toggle-all completes / un-completes | ⊘³ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| double-click edits + focuses input | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| Enter saves an edit | ⊘¹ | ✅ | ✅ | ✅ | ✅ | ⊘¹ | ✅ | ⊘⁴ | ⊘¹ | ✅ |
| Escape discards an edit | ✅ | ⊘² | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ⊘¹ | ✅ |
| blur saves an edit | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| editing to empty destroys item | ⊘¹ | ✅ | ⊘⁵ | ✅ | ✅ | ⊘¹ | ✅ | ⊘⁴ | ⊘¹ | ✅ |
| hover reveals destroy + removes | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| filters move selection + filter | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| clear-completed removes + hides | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |
| active filter (hash) survives reload | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ✅ | ⊘⁴ | ✅ | ✅ |

**Skip key** — ¹ app commits via an internal blur on Enter/Escape, dropped in headless (#143) · ² React Escape deviation · ³ es6 toggle-all deviation · ⁴ shadow DOM: resolution now pierces it (#151, #162), but these scenarios read state via non-piercing `evaluate()` (#165) · ⁵ react-redux empty-destroy deviation.

## Triage of every gap

The whole point of the epic was that the *selector strategy* — the thing Tetryon is selling — either works or produces a filed, categorised issue. Categorised, every gap is one of three things, and **none of them is a selector-strategy failure**:

### Tetryon bugs (fix)

- **Commit-on-blur under headless — [#143](https://github.com/Vusys/tetryon/issues/143), mitigated.** In headless Firefox `document.hasFocus()` is `false` and moving focus fires no `blur`/`focusout` event, so any commit-on-blur pattern no-ops. The [`blur()`](../blob/master/docs/interactions.md) verb now dispatches those events itself, so *blur saves* passes on all nine reachable apps. What remains is what Tetryon can't intercept: es6, svelte, and jquery commit by calling the field's *own* `blur()` internally on Enter/Escape, and that internal blur is still dropped headless — so *Enter saves* / *Escape discards* / *edit-to-empty* on those apps need a focused window (run headed, or under Xvfb in CI — see [Continuous integration](../blob/master/docs/ci.md)). Tetryon's keys are fine either way: `pressKey('Enter')` fires `keydown`+`keypress` correctly.
- **Shadow DOM — [#151](https://github.com/Vusys/tetryon/issues/151) + [#162](https://github.com/Vusys/tetryon/issues/162), fixed.** Lit renders the whole app into nested shadow roots. Resolution now pierces them for **both** CSS-based targets (test-id, placeholder, name, explicit CSS/id) and text/label strategies (button/link text, label association) — via a JS matcher, since XPath can't cross shadow boundaries — and actionability and `assertSee()` are shadow-aware. So a web-component app is drivable behaviourally. Lit still can't run the *whole* suite green because these scenarios read state via `document.querySelectorAll` in `evaluate()`, which doesn't pierce (tracked in [#165](https://github.com/Vusys/tetryon/issues/165)); it stays skipped until that's shadow-aware. The only strategy that still doesn't pierce is the accessible-name locator.

### Upstream app deviations (record, don't fix)

We are testing Tetryon, not TodoMVC — where an app deviates from its own spec, that gets recorded, not fixed.

- **React doesn't handle Escape** to cancel an edit (no `onKeyDown` Escape) — an upstream deviation, not a Tetryon gap.
- **react-redux exits edit on Enter but doesn't destroy an emptied todo** — upstream behaviour that differs from plain React despite the same view layer.
- **javascript-es6 binds toggle-all to the label's click**, not the input's change (the label then clicks the input), so a synthetic input click can't trigger it. Every other app binds `change` on the input, where `check()` drives it fine.

### Non-goals

- **localStorage persistence.** `app-spec.md` requires it, but measured against the built `dist/` output **none of the ten persist** — zero storage keys after adding a todo, zero todos after reload. The persistence scenario is dropped; the reload scenario instead asserts the hash route survives.

## What this proved

The behavioural approach holds. Across the nine reachable apps, `fill()` by placeholder, `check()`/`uncheck()` on custom (visually-hidden) checkboxes, `doubleClick()`/`click()` by a todo's text, filter links by their text, the counter across its `<strong>` boundary, `.selected`/`.completed`/`.editing` state, hover-to-reveal destroy, and hash-route assertions all pass **unmodified** — the same suite, byte for byte, against ten different DOMs.

Three blockers surfaced by the initial probe were **fixed** as part of this work, which is why the grid above is mostly green:

- `check()`/`uncheck()` resolving the wrong element next to a sibling label — [#138](https://github.com/Vusys/tetryon/issues/138).
- visually-hidden custom checkboxes being undrivable — [#139](https://github.com/Vusys/tetryon/issues/139).
- `currentPath()` dropping the URL fragment, so hash routes couldn't be asserted — [#140](https://github.com/Vusys/tetryon/issues/140).

Two smaller API gaps the suite reached for: [`blur()`](https://github.com/Vusys/tetryon/issues/142) (now shipped — it also mitigates #143) and [`assertFocused()`](https://github.com/Vusys/tetryon/issues/141).
