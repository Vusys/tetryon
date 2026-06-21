# PHPUnit Browser Testing Framework Plan

## Product position

Build a PHP-native browser testing package for PHPUnit.

The package should let PHP developers write browser tests that are readable, reliable, and easy to run locally or in CI, without Node, Playwright, Selenium Server, ChromeDriver, or Dusk-style setup pain.

The framework is Firefox-first and PHPUnit-first.

It is not a general browser automation framework for every language. If someone uses it to test a Ruby, Rails, Node, or static app by pointing it at a local server, fine. But the supported user is a PHP developer writing PHPUnit tests.

## Target user

A PHP developer who wants to write this:

```php
final class LoginTest extends BrowserTestCase
{
    public function test_guest_can_log_in(): void
    {
        $this->browser()
            ->visit('/login')
            ->fill('Email', 'bryan@example.com')
            ->fill('Password', 'password')
            ->press('Log in')
            ->assertSee('Dashboard');
    }
}
```

And run it with:

```bash
vendor/bin/phpunit --testsuite Browser
```

Or in Laravel:

```bash
php artisan test --testsuite=Browser
```

## Core decisions

### PHPUnit only

Support PHPUnit 12 and PHPUnit 13.

Do not support Pest, Behat, Codeception, PHPSpec, Gherkin files, or a custom test runner in the MVP.

Tests should be normal PHPUnit tests. PHPUnit owns discovery, filtering, failures, grouping, exit codes, and CI behaviour.

The package owns browser automation.

### Firefox first

Firefox is the reference browser for v1.

Supported for v1:

* Firefox stable
* Firefox ESR if practical
* macOS
* Linux
* CI on Linux

Not supported for v1:

* Chrome
* Chromium
* Edge
* Safari
* Windows
* mobile browsers

Chrome support can be considered later, but it should not shape the v1 design.

### WebDriver BiDi

Use WebDriver BiDi as the primary browser control protocol.

Avoid:

* Playwright
* Puppeteer
* Node
* Selenium Server
* ChromeDriver
* CDP as a core protocol

The ideal path is:

```text
PHPUnit test
  ↓
Framework browser API
  ↓
Firefox WebDriver BiDi adapter
  ↓
Firefox
```

If direct Firefox BiDi control proves too painful, geckodriver can be considered as a fallback, but the preferred model is direct control with minimal moving parts.

### Bring your own server

The package should not care how the application is served.

The user starts their app however they like:

```bash
php artisan serve
symfony serve
docker compose up
npm run dev
python -m http.server
```

Then the package tests against:

```php
'base_url' => 'http://127.0.0.1:8000',
```

Laravel can have optional helpers for starting a server, but the core model is:

> You run the app. The package controls the browser.

## Public API

### Base test case

Provide:

```php
Journey\PHPUnit\BrowserTestCase
```

Example:

```php
use Journey\PHPUnit\BrowserTestCase;

final class LoginTest extends BrowserTestCase
{
    public function test_guest_can_log_in(): void
    {
        $this->browser()
            ->visit('/login')
            ->fill('Email', 'bryan@example.com')
            ->fill('Password', 'password')
            ->press('Log in')
            ->assertSee('Dashboard');
    }
}
```

Also provide a trait for users who cannot extend the base class:

```php
use Journey\PHPUnit\InteractsWithBrowser;
use PHPUnit\Framework\TestCase;

final class LoginTest extends TestCase
{
    use InteractsWithBrowser;

    public function test_guest_can_log_in(): void
    {
        $this->browser()
            ->visit('/login')
            ->assertSee('Login');
    }
}
```

The base class is the recommended path. The trait is the escape hatch.

## Browser API

### Navigation

```php
$this->browser()
    ->visit('/')
    ->assertUrlIs('/')
    ->assertTitleIs('Home');
```

Supported methods:

```php
visit(string $pathOrUrl)
back()
forward()
refresh()
assertUrlIs(string $url)
assertPathIs(string $path)
assertTitleIs(string $title)
```

### Text and visibility

```php
$this->browser()
    ->visit('/dashboard')
    ->assertSee('Dashboard')
    ->assertDontSee('Exception')
    ->assertVisible('Save changes')
    ->assertMissing('Loading...');
```

Supported methods:

```php
assertSee(string $text)
assertDontSee(string $text)
assertVisible(string $labelOrSelector)
assertMissing(string $labelOrSelector)
assertTextNear(string $near, string $text)
```

### Forms

```php
$this->browser()
    ->fill('Email', 'bryan@example.com')
    ->fill('Password', 'password')
    ->check('Remember me')
    ->press('Log in');
```

Supported methods:

```php
fill(string $field, string $value)
clear(string $field)
select(string $field, string $value)
check(string $field)
uncheck(string $field)
choose(string $field)
upload(string $field, string $path)
```

### Actions

```php
$this->browser()
    ->click('Settings')
    ->press('Save')
    ->hover('Account')
    ->pressKey('Enter');
```

Supported methods:

```php
click(string $target)
press(string $button)
doubleClick(string $target)
rightClick(string $target)
hover(string $target)
type(string $target, string $value)
pressKey(string $key)
```

Drag and drop can wait until after the MVP unless it is easy to implement cleanly.

### Waiting

Auto-wait by default.

Users should rarely need manual waits.

Supported explicit waits:

```php
waitForText(string $text)
waitUntilMissing(string $text)
waitForUrl(string $url)
waitForIdle()
```

Avoid encouraging:

```php
sleep(1);
```

Every action should wait until the target is actionable:

* exists
* visible
* enabled
* stable
* scrolled into view

Every assertion should retry until timeout.

## Natural-language layer

Natural-language-style testing should live inside PHPUnit.

No `.feature` files. No separate runner.

Example:

```php
$this->browser()
    ->step('I am on "/login"')
    ->step('I fill "Email" with "bryan@example.com"')
    ->step('I fill "Password" with "password"')
    ->step('I press "Log in"')
    ->step('I should see "Dashboard"');
```

Or:

```php
$this->scenario()
    ->given('I am on "/login"')
    ->when('I fill "Email" with "bryan@example.com"')
    ->and('I fill "Password" with "password"')
    ->and('I press "Log in"')
    ->then('I should see "Dashboard"');
```

This should be deterministic, not AI-driven.

The parser should map a small grammar to internal commands:

```text
I am on "/login"
I fill "Email" with "bryan@example.com"
I press "Log in"
I should see "Dashboard"
I should not see "Error"
```

Natural language is a convenience layer over the same fluent API.

The canonical API is still:

```php
$this->browser()
    ->visit('/login')
    ->fill('Email', 'bryan@example.com')
    ->press('Log in')
    ->assertSee('Dashboard');
```

## Selector strategy

Tests should read like user behaviour.

Prefer:

```php
->fill('Email', 'bryan@example.com')
->press('Save changes')
->click('Settings')
```

Rather than:

```php
->click('#app > div:nth-child(2) > button')
```

Element lookup order:

1. Explicit selector, if marked as selector.
2. Configured test attributes:

   * `data-testid`
   * `data-test`
   * `data-cy`
3. Associated label text.
4. Accessible name / ARIA label.
5. Placeholder.
6. Button text.
7. Link text.
8. Input `name`.
9. Input `id`.
10. Visible text fallback.

Support explicit selectors when needed:

```php
->click('@save-button')
->click('[data-testid="save-button"]')
->click('#save')
```

Config:

```php
'selectors' => [
    'test_attributes' => [
        'data-testid',
        'data-test',
        'data-cy',
    ],
],
```

## Failure diagnostics

This is a core feature, not polish.

On failure, capture:

* screenshot
* page HTML
* current URL
* browser console logs
* network log if available
* last actions
* selector resolution attempts
* browser stderr
* viewport size
* artifact paths

Example failure:

```text
Failed asserting that "Save changes" was visible.

Current URL:
  http://127.0.0.1:8000/settings

Tried to find:
  Save changes

Selector attempts:
  [data-testid="Save changes"]       no matches
  button text "Save changes"         no matches
  link text "Save changes"           no matches
  aria-label "Save changes"          no matches
  visible text "Save changes"        found 1 hidden match

Closest visible matches:
  "Save"
  "Discard changes"
  "Settings"

Artifacts:
  Screenshot: tests/Browser/Artifacts/SettingsTest/screenshot.png
  HTML:       tests/Browser/Artifacts/SettingsTest/page.html
  Console:    tests/Browser/Artifacts/SettingsTest/console.log
```

Bad errors kill browser testing adoption. Good errors are a feature.

## Configuration

Example `journey.php`:

```php
return [
    'base_url' => env('JOURNEY_BASE_URL', 'http://127.0.0.1:8000'),

    'browser' => 'firefox',

    'headless' => env('JOURNEY_HEADLESS', true),

    'timeout' => [
        'default' => 5000,
        'navigation' => 15000,
        'assertion' => 5000,
    ],

    'viewport' => [
        'width' => 1280,
        'height' => 720,
    ],

    'artifacts' => [
        'path' => 'tests/Browser/Artifacts',
        'screenshots' => true,
        'html' => true,
        'console' => true,
        'network' => true,
    ],

    'selectors' => [
        'test_attributes' => [
            'data-testid',
            'data-test',
            'data-cy',
        ],
    ],

    'server' => [
        'manage' => false,
        'command' => null,
        'url' => env('JOURNEY_BASE_URL', 'http://127.0.0.1:8000'),
        'reuse_existing' => true,
        'timeout' => 30000,
    ],
];
```

## CLI

Keep the CLI small.

Include:

```bash
vendor/bin/journey doctor
vendor/bin/journey open /login
```

Do not build a competing test runner.

Avoid:

```bash
vendor/bin/journey test
```

Tests should run through PHPUnit.

### Doctor command

```bash
vendor/bin/journey doctor
```

Checks:

* PHP version
* required PHP extensions
* PHPUnit compatibility
* Firefox installed
* Firefox can launch headless
* WebDriver BiDi connection works
* base URL is reachable
* artifact directory is writable
* common CI issues

Example:

```text
Journey Doctor

PHP                 OK
PHPUnit             OK
Firefox             OK
Headless launch     OK
BiDi connection     OK
Base URL            OK
Artifacts           OK

Ready.
```

Failures should explain how to fix the issue.

Bad:

```text
Could not connect.
```

Good:

```text
Firefox launched, but the WebDriver BiDi endpoint was not reachable.

Command:
  firefox --headless --remote-debugging-port=0

Try:
  vendor/bin/journey doctor -vvv

Also check that your installed Firefox version supports remote debugging.
```

### Open command

```bash
vendor/bin/journey open /login
```

Launches Firefox headed against the configured base URL.

Useful for debugging.

## Browser lifecycle

Each test should get isolation by default.

Lifecycle:

1. Load config.
2. Check base URL.
3. Locate Firefox.
4. Create temporary Firefox profile.
5. Launch Firefox.
6. Connect to WebDriver BiDi.
7. Create or select browsing context.
8. Run test.
9. Capture artifacts on failure.
10. Close context.
11. Stop browser.
12. Delete temporary profile unless debug mode is enabled.

Default isolation:

* fresh cookies
* fresh local storage
* fresh session storage
* fresh browser context/profile
* no leakage between tests

## Internal architecture

Core objects:

```php
Browser
Page
Locator
Command
Scenario
StepParser
ArtifactBag
FailureReport
FirefoxBiDiDriver
```

Interfaces should exist only where useful.

Avoid over-abstracting for imaginary future browsers.

Good:

```php
final class FirefoxBiDiDriver
{
    // v1 implementation
}
```

Acceptable:

```php
interface BrowserDriver
{
    public function start(): void;

    public function stop(): void;

    public function newPage(): PageDriver;
}
```

Bad:

```php
interface UniversalCrossBrowserEverythingDriver
{
    // invented too early
}
```

The public API should avoid Firefox-specific names where possible, but the implementation can be proudly Firefox-specific.

## Package structure

```text
journey/
  packages/
    core/
    phpunit/
    firefox-bidi/
    laravel/
  tests/
    fixtures/
      static-site/
      laravel-app/
  docs/
    installation.md
    writing-tests.md
    selectors.md
    waiting.md
    ci.md
    laravel.md
    troubleshooting.md
```

No Pest package in MVP.

No Gherkin package in MVP.

No Chrome package in MVP.

## Composer packages

User-facing install should be simple:

```bash
composer require --dev vendor/journey
```

Internally, this may include:

```text
vendor/journey-core
vendor/journey-phpunit
vendor/journey-firefox-bidi
```

Laravel users can add:

```bash
composer require --dev vendor/journey-laravel
```

Or the Laravel integration can be included and auto-discovered if the package is installed in a Laravel app.

## Laravel integration

Laravel support should feel first-class, but Laravel should not be required by the core.

Install:

```bash
composer require --dev vendor/journey-laravel
php artisan journey:install
```

Publishes:

```text
config/journey.php
tests/Browser/ExampleTest.php
tests/Browser/Artifacts/.gitignore
```

Example:

```php
namespace Tests\Browser;

use App\Models\User;
use Journey\Laravel\BrowserTestCase;

final class LoginTest extends BrowserTestCase
{
    public function test_user_can_log_in(): void
    {
        User::factory()->create([
            'email' => 'bryan@example.com',
            'password' => bcrypt('password'),
        ]);

        $this->browser()
            ->visit('/login')
            ->fill('Email', 'bryan@example.com')
            ->fill('Password', 'password')
            ->press('Log in')
            ->assertSee('Dashboard');
    }
}
```

Laravel commands:

```bash
php artisan journey:install
php artisan journey:doctor
php artisan journey:serve
```

Avoid:

```bash
php artisan journey:test
```

Use:

```bash
php artisan test --testsuite=Browser
```

### Laravel helpers

Possible later helpers:

```php
$this->browser()
    ->loginAs($user)
    ->visit('/dashboard')
    ->assertSee('Dashboard');
```

This must only work in local/testing environments.

If enabled outside testing, it should refuse to boot.

Possible implementation:

* signed one-time login URL
* testing-only route
* session injection
* middleware guarded by environment

Do this after the base browser flow works.

## PHPUnit config

Recommended `phpunit.xml`:

```xml
<testsuites>
    <testsuite name="Unit">
        <directory>tests/Unit</directory>
    </testsuite>

    <testsuite name="Feature">
        <directory>tests/Feature</directory>
    </testsuite>

    <testsuite name="Browser">
        <directory>tests/Browser</directory>
    </testsuite>
</testsuites>
```

Recommended env:

```xml
<php>
    <env name="JOURNEY_BASE_URL" value="http://127.0.0.1:8000"/>
    <env name="JOURNEY_HEADLESS" value="true"/>
</php>
```

Run:

```bash
vendor/bin/phpunit --testsuite Browser
```

Or exclude browser tests:

```bash
vendor/bin/phpunit --exclude-group browser
```

## CI

GitHub Actions example:

```yaml
name: Browser Tests

on:
  push:
  pull_request:

jobs:
  browser:
    runs-on: ubuntu-latest

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.4"
          coverage: none

      - name: Install dependencies
        run: composer install --no-interaction --prefer-dist

      - name: Install Firefox
        run: |
          sudo apt-get update
          sudo apt-get install -y firefox

      - name: Prepare app
        run: |
          cp .env.example .env
          php artisan key:generate
          php artisan migrate --force

      - name: Start app
        run: |
          php artisan serve --env=testing --host=127.0.0.1 --port=8000 > storage/logs/journey-server.log 2>&1 &

      - name: Check browser environment
        run: vendor/bin/journey doctor
        env:
          JOURNEY_BASE_URL: http://127.0.0.1:8000
          JOURNEY_HEADLESS: true

      - name: Run browser tests
        run: vendor/bin/phpunit --testsuite Browser
        env:
          JOURNEY_BASE_URL: http://127.0.0.1:8000
          JOURNEY_HEADLESS: true

      - name: Upload browser artifacts
        if: failure()
        uses: actions/upload-artifact@v4
        with:
          name: journey-artifacts
          path: tests/Browser/Artifacts
```

Long term, provide:

* GitHub Actions recipe
* GitLab CI recipe
* Docker image
* artifact upload docs
* parallel test docs

## Local install docs

### Arch Linux

```bash
sudo pacman -S php composer firefox
composer require --dev vendor/journey
vendor/bin/journey doctor
```

### macOS

```bash
brew install php composer
brew install --cask firefox
composer require --dev vendor/journey
vendor/bin/journey doctor
```

## MVP roadmap

### v0.1 — Firefox protocol spike

Prove the core technical risk.

Must do:

* launch Firefox headless
* create temporary profile
* connect to WebDriver BiDi
* open page
* navigate to URL
* execute JavaScript
* find element
* click
* type
* take screenshot
* collect console logs
* shut down cleanly

No public release until this is stable on macOS and Linux.

### v0.2 — PHPUnit alpha

Must do:

* PHPUnit 12 support
* PHPUnit 13 support
* `BrowserTestCase`
* `InteractsWithBrowser` trait
* fluent browser API
* `visit`
* `click`
* `press`
* `fill`
* `select`
* `check`
* `assertSee`
* `assertDontSee`
* `assertVisible`
* `assertUrlIs`
* auto-waiting
* screenshots on failure
* HTML snapshots on failure
* console logs on failure
* `vendor/bin/journey doctor`

### v0.3 — Natural-language steps

Add:

```php
$this->browser()
    ->step('I am on "/login"')
    ->step('I fill "Email" with "bryan@example.com"')
    ->step('I press "Log in"')
    ->step('I should see "Dashboard"');
```

And:

```php
$this->scenario()
    ->given('I am on "/login"')
    ->when('I fill "Email" with "bryan@example.com"')
    ->then('I should see "Dashboard"');
```

Keep the grammar small and deterministic.

### v0.4 — Laravel integration

Add:

* service provider
* config publishing
* `php artisan journey:install`
* `php artisan journey:doctor`
* optional `php artisan journey:serve`
* generated browser test
* Laravel-specific docs
* optional `loginAs()` helper if safe and clean

### v0.5 — CI hardening

Add:

* GitHub Actions docs
* GitLab CI docs
* Docker example
* failure artifact upload docs
* parallel PHPUnit guidance
* better CI diagnostics

### v1.0 — Firefox-only stable

Compatibility promise:

```text
Journey v1 supports browser testing with PHPUnit 12/13 and Firefox on macOS and Linux.
```

No Chrome promise.

No Playwright promise.

No Selenium Server promise.

## Non-goals

For v1, do not build:

* Chrome support
* Chromium support
* CDP adapter
* Playwright compatibility
* Selenium Grid support
* Pest integration
* Behat integration
* Gherkin files
* standalone Journey test runner
* Windows support
* mobile support
* visual regression
* video recording
* cloud browser provider support
* test recorder
* AI-generated tests

## Success criteria

The package succeeds if this is true:

1. A PHP developer can install it with Composer.
2. They can run `vendor/bin/journey doctor`.
3. They can write a normal PHPUnit browser test.
4. They can point it at any local web app.
5. It launches Firefox without drama.
6. It waits intelligently.
7. It gives useful failure output.
8. It works in CI.
9. Laravel feels first-class.
10. The user never has to think about Node, Playwright, Selenium Server, ChromeDriver, or browser version matching.

## Project mantra

PHPUnit. Firefox. WebDriver BiDi. Composer install. Useful errors. No faff.
