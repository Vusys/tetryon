<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Config;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Config\Configuration;

#[CoversClass(Configuration::class)]
final class ConfigurationTest extends TestCase
{
    public function test_it_has_sensible_defaults(): void
    {
        $config = new Configuration;

        self::assertSame('http://127.0.0.1:8000', $config->baseUrl);
        self::assertTrue($config->headless);
        self::assertNull($config->firefoxBinary);
        self::assertSame(5000, $config->timeouts->default);
        self::assertSame(1280, $config->viewport->width);
    }

    public function test_resolve_url_joins_a_path_to_the_base(): void
    {
        $config = new Configuration(baseUrl: 'http://localhost:9000');

        self::assertSame('http://localhost:9000/login', $config->resolveUrl('/login'));
        self::assertSame('http://localhost:9000/login', $config->resolveUrl('login'));
        self::assertSame('http://localhost:9000/', $config->resolveUrl('/'));
    }

    public function test_resolve_url_passes_absolute_urls_through(): void
    {
        $config = new Configuration(baseUrl: 'http://localhost:9000');

        self::assertSame('https://example.com/x', $config->resolveUrl('https://example.com/x'));
    }

    public function test_it_rejects_an_invalid_base_url(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Configuration(baseUrl: 'not a url');
    }

    public function test_from_array_maps_keys(): void
    {
        $config = Configuration::fromArray([
            'base_url' => 'http://localhost:3000',
            'headless' => false,
            'timeout' => ['navigation' => 30_000],
            'viewport' => ['width' => 1920],
        ]);

        self::assertSame('http://localhost:3000', $config->baseUrl);
        self::assertFalse($config->headless);
        self::assertSame(30_000, $config->timeouts->navigation);
        self::assertSame(1920, $config->viewport->width);
    }

    public function test_from_environment_reads_env_vars(): void
    {
        $originalUrl = getenv('TETRYON_BASE_URL');
        $originalHeadless = getenv('TETRYON_HEADLESS');

        putenv('TETRYON_BASE_URL=http://localhost:1234');
        putenv('TETRYON_HEADLESS=false');

        try {
            $config = Configuration::fromEnvironment();
            self::assertSame('http://localhost:1234', $config->baseUrl);
            self::assertFalse($config->headless);
        } finally {
            $this->restoreEnv('TETRYON_BASE_URL', $originalUrl);
            $this->restoreEnv('TETRYON_HEADLESS', $originalHeadless);
        }
    }

    private function restoreEnv(string $key, string|false $original): void
    {
        if ($original === false) {
            putenv($key);
        } else {
            putenv("{$key}={$original}");
        }
    }
}
