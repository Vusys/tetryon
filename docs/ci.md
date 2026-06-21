# Continuous integration

The Browser suite runs anywhere Firefox can be installed and your app can be
served. Headless Firefox needs no display.

The pattern is always the same:

1. Install PHP + Firefox.
2. Install Composer dependencies.
3. Start your app (any way you like).
4. Run `vendor/bin/phpunit --testsuite Browser`.
5. On failure, upload `tests/Browser/Artifacts`.

## GitHub Actions (reference)

This is the primary recipe — it mirrors how Tetryon tests itself.

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
          if-no-files-found: ignore
```

**macOS runners work too** — `browser-actions/setup-firefox` installs Firefox
on `macos-latest`, and the suite runs unchanged. Tetryon's own CI exercises
Linux and macOS.

## Docker

A minimal image with PHP and Firefox, usable from any CI provider or locally:

```dockerfile
FROM php:8.4-cli

RUN apt-get update \
    && apt-get install -y --no-install-recommends firefox-esr unzip git libzip-dev \
    && docker-php-ext-install zip \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app
```

```bash
docker build -t myapp-browser .
docker run --rm -v "$PWD":/app myapp-browser sh -c '
  composer install --no-interaction --prefer-dist &&
  php artisan serve --host=127.0.0.1 --port=8000 &
  TETRYON_BASE_URL=http://127.0.0.1:8000 vendor/bin/phpunit --testsuite Browser
'
```

## GitLab CI

```yaml
browser-tests:
  image: php:8.4-cli
  variables:
    TETRYON_BASE_URL: "http://127.0.0.1:8000"
    TETRYON_HEADLESS: "true"
  before_script:
    - apt-get update && apt-get install -y --no-install-recommends firefox-esr unzip git
    - curl -sS https://getcomposer.org/installer | php && mv composer.phar /usr/local/bin/composer
    - composer install --no-interaction --prefer-dist
  script:
    - php artisan serve --host=127.0.0.1 --port=8000 &
    - vendor/bin/tetryon doctor
    - vendor/bin/phpunit --testsuite Browser
  artifacts:
    when: on_failure
    paths:
      - tests/Browser/Artifacts
    expire_in: 1 week
```

## Running in parallel

Each test that calls `browser()` gets its **own** Firefox with a fresh
temporary profile, so browser state never leaks between parallel workers. The
contention to watch is shared resources — the database and the single served
app instance.

PHPUnit does not parallelise on its own; use a runner such as
[`brianium/paratest`](https://github.com/paratestphp/paratest):

```bash
vendor/bin/paratest --testsuite Browser --processes 4
```

For Laravel, give each worker its own database (Laravel's parallel testing
already does this with `php artisan test --parallel`), and make sure the served
app points at the same database the test process seeds.

## Diagnostics in CI

- **Always upload `tests/Browser/Artifacts` on failure** so a red build comes
  with the screenshot, HTML, console log, and command trace. See
  [Diagnostics](diagnostics.md).
- **Run `tetryon doctor` first** — it fails fast with a clear message if Firefox
  or the environment is wrong, before the suite even starts.
- **Set `TETRYON_DEBUG=1`** to stream the BiDi command log to stderr when you
  need to see exactly what the browser did.
