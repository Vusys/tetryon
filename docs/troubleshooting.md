# Troubleshooting

Start with `vendor/bin/tetryon doctor` — it diagnoses most setup problems and prints a fix hint for each.

## Firefox could not be located

```
Could not locate a Firefox executable. Tried: ...
```

Install Firefox (see [Installation](installation.md)), or set the path explicitly:

```bash
TETRYON_FIREFOX_BINARY=/path/to/firefox vendor/bin/phpunit --testsuite Browser
```

## Firefox launches but BiDi never connects

`doctor`'s "Headless launch" check covers this. Make sure your Firefox is recent enough to expose WebDriver BiDi (any current stable release is fine). If you run in a locked-down sandbox, ensure Firefox is allowed to open a local WebSocket on a loopback port.

## No element matched

```
Could not find an element for "Save changes".
Selector attempts:
  ...
```

The exception lists every strategy that was tried and how many nodes matched — use it to see what Tetryon looked for. Common fixes:

- Add a test attribute (`data-testid="save"`) and target `@save`.
- Check the text actually rendered (the failure [screenshot/HTML](diagnostics.md) will tell you).
- Actions already wait for the element to appear; if it genuinely never appears, the timeout has elapsed.

## A test is flaky / times out

You should not need `sleep()`. If an assertion or action times out, the content genuinely did not appear in time. Increase the relevant [timeout](waiting.md#timeouts), or assert on a more specific signal that the page is ready.

## An unhandled dialog appeared

The page opened a `window.confirm` / `alert` / `prompt` that the test had not arranged an answer for. A dialog blocks the page, so it is dismissed to keep the session usable and the action fails naming it. Arrange the answer **before** the action that opens it — see [native dialogs](interactions.md#native-dialogs).

## Base URL is not reachable

Tetryon does not start your app. Make sure it is running and that `TETRYON_BASE_URL` points at it. In CI, start the server before the test step and give it a moment to come up.

## CI has no display

Headless Firefox needs no display, so this is usually fine. Ensure `TETRYON_HEADLESS` is `true` (the default) in CI.

## Leftover processes or profiles

Tetryon terminates its own Firefox by PID and deletes the temporary profile on teardown — it never touches a Firefox you started yourself. If you kill a test run hard (e.g. `SIGKILL`), a stray temp profile may remain under your system temp directory; it is safe to delete.
