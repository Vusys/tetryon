# Diagnostics

Bad errors kill browser-testing adoption, so Tetryon treats diagnostics as a
feature.

## Failure artifacts

When a browser test **fails**, Tetryon captures a diagnostic bundle into a
per-test directory under the artifacts path (`tests/Browser/Artifacts` by
default) and prints a report pointing at it:

```text
Tetryon browser diagnostics

Current URL:
  http://127.0.0.1:8000/settings

Artifacts:
  Screenshot: tests/Browser/Artifacts/SettingsTest_test_save/screenshot.png
  HTML:       tests/Browser/Artifacts/SettingsTest_test_save/page.html
  Console:    tests/Browser/Artifacts/SettingsTest_test_save/console.log
  Trace:      tests/Browser/Artifacts/SettingsTest_test_save/trace.log
```

The bundle contains:

- `screenshot.png` — the page at the moment of failure.
- `page.html` — the rendered DOM.
- `console.log` — browser console messages.
- `trace.log` — the recent BiDi command trace (what the browser was doing).
- `browser-stderr.log` — Firefox's own stderr.
- `info.txt` — current URL and viewport.

Add the artifacts directory to your `.gitignore`:

```gitignore
/tests/Browser/Artifacts
```

## `tetryon doctor`

Run a preflight check of the whole environment:

```bash
vendor/bin/tetryon doctor
```

It verifies PHP, the required extensions, that Firefox is found, that it can
actually launch headless and complete a WebDriver BiDi handshake, and that the
artifact directory is writable. Failures print a fix hint and `doctor` exits
non-zero — handy as a CI gate before the Browser suite.

## Verbose logging

Set `TETRYON_DEBUG` to stream the BiDi command log to stderr while tests run:

```bash
TETRYON_DEBUG=1 vendor/bin/phpunit --testsuite Browser
```

Tetryon logs through PSR-3. To plug in your own logger (Monolog, etc.),
override `browserLogger()` in your test class. You can also read the recent
command trace directly:

```php
$trace = $this->browser()->trace();
```
