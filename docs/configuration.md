# Configuration

By default Tetryon reads its configuration from the environment. You can also
override it per test class.

## Environment variables

| Variable | Default | Purpose |
| --- | --- | --- |
| `TETRYON_BASE_URL` | `http://127.0.0.1:8000` | base URL that paths resolve against |
| `TETRYON_HEADLESS` | `true` | run Firefox headless |
| `TETRYON_FIREFOX_BINARY` | (auto) | explicit path to the Firefox executable |
| `TETRYON_ARTIFACTS_PATH` | `tests/Browser/Artifacts` | where failure artifacts are written |
| `TETRYON_DEBUG` | (unset) | stream the BiDi command log to stderr |

Set them in `phpunit.xml`:

```xml
<php>
    <env name="TETRYON_BASE_URL" value="http://127.0.0.1:8000"/>
    <env name="TETRYON_HEADLESS" value="true"/>
</php>
```

## Per-test configuration

Override `browserConfiguration()` to build a `Configuration` yourself — useful
for pointing a test at a server it started on a random port:

```php
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;

final class CheckoutTest extends BrowserTestCase
{
    protected function browserConfiguration(): Configuration
    {
        return new Configuration(
            baseUrl: 'http://127.0.0.1:9000',
            headless: true,
        );
    }
}
```

## From an array

`Configuration::fromArray()` accepts the published config shape:

```php
Configuration::fromArray([
    'base_url' => 'http://127.0.0.1:8000',
    'headless' => true,
    'firefox_binary' => null,

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
    ],

    'selectors' => [
        'test_attributes' => ['data-testid', 'data-test', 'data-cy'],
    ],
]);
```

## Selector test attributes

The attributes tried first when resolving a human target (see
[Selectors](selectors.md)). Change them via the `selectors.test_attributes`
array key or by passing `selectorTestAttributes` to `Configuration`.
