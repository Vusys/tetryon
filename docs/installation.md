# Installation

## Requirements

- **PHP 8.4+** with the `json` and `mbstring` extensions.
- **PHPUnit 12 or 13.**
- **Firefox** (stable; ESR where practical) on **macOS** or **Linux**.

No Node, Selenium Server, ChromeDriver, or geckodriver is needed — Tetryon
drives Firefox directly over WebDriver BiDi.

## Install with Composer

```bash
composer require --dev vusys/tetryon
```

## Install Firefox

### Arch Linux

```bash
sudo pacman -S firefox
```

### Debian / Ubuntu

```bash
sudo apt-get update && sudo apt-get install -y firefox
```

### macOS

```bash
brew install --cask firefox
```

If Firefox is not on your `PATH`, point Tetryon at it with the
`TETRYON_FIREFOX_BINARY` environment variable (see [Configuration](configuration.md)).

## Check your environment

```bash
vendor/bin/tetryon doctor
```

```text
Tetryon Doctor

PHP               OK    8.4.x
Extensions        OK    json, mbstring
Firefox           OK    /usr/bin/firefox
Headless launch   OK
Artifacts         OK    tests/Browser/Artifacts

Ready.
```

`doctor` actually launches headless Firefox and completes a WebDriver BiDi
handshake, so a green report means real browser tests will run. Each failure
prints a fix hint; `doctor` exits non-zero if anything is wrong.

## Configure a Browser test suite

Add a `Browser` suite to your `phpunit.xml` so browser tests are opt-in and run
separately from your fast unit tests:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>
    <testsuite name="Browser">
        <directory>tests/Browser</directory>
    </testsuite>
</testsuites>
```

```bash
vendor/bin/phpunit --testsuite Browser
```

Next: [writing your first test](writing-tests.md).
