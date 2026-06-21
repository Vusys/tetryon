# Continuous integration

The Browser suite runs anywhere Firefox can be installed and your app can be
served. Headless Firefox needs no display.

## GitHub Actions

```yaml
name: Browser tests

on: [push, pull_request]

permissions:
  contents: read

jobs:
  browser:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.4'
          extensions: mbstring
          coverage: none

      - name: Install Firefox
        uses: browser-actions/setup-firefox@v1

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      # Start your app however it is served — e.g. Laravel:
      - name: Start app
        run: php artisan serve --host=127.0.0.1 --port=8000 &

      - name: Check the environment
        run: vendor/bin/tetryon doctor
        env:
          TETRYON_BASE_URL: http://127.0.0.1:8000

      - name: Run browser tests
        run: vendor/bin/phpunit --testsuite Browser
        env:
          TETRYON_BASE_URL: http://127.0.0.1:8000
          TETRYON_HEADLESS: true

      - name: Upload artifacts on failure
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: tetryon-artifacts
          path: tests/Browser/Artifacts
```

## Notes

- **macOS runners work too** — `browser-actions/setup-firefox` installs Firefox
  on `macos-latest`, and the Browser suite runs unchanged. Tetryon's own CI
  exercises Linux and macOS.
- **Upload artifacts on failure** so a red build comes with the screenshot,
  HTML, console log, and command trace (see [Diagnostics](diagnostics.md)).
- **`tetryon doctor`** makes a good pre-flight step — it fails fast with a clear
  message if Firefox or the environment is wrong.
