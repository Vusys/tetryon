<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel;

use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Config\Timeouts;
use Vusys\Tetryon\Core\Config\Viewport;

/**
 * Builds a {@see Configuration} from a Laravel app's `tetryon` config. Each
 * value is read defensively so a misconfigured key falls back to a default
 * rather than blowing up.
 */
final class LaravelConfiguration
{
    public static function resolve(): Configuration
    {
        return new Configuration(
            baseUrl: self::string('tetryon.base_url', 'http://127.0.0.1:8000'),
            headless: self::bool('tetryon.headless', true),
            firefoxBinary: self::nullableString('tetryon.firefox_binary'),
            timeouts: new Timeouts(
                self::int('tetryon.timeout.default', 5000),
                self::int('tetryon.timeout.navigation', 15000),
                self::int('tetryon.timeout.assertion', 5000),
            ),
            viewport: new Viewport(
                self::int('tetryon.viewport.width', 1280),
                self::int('tetryon.viewport.height', 720),
            ),
            selectorTestAttributes: self::stringList('tetryon.selectors.test_attributes', ['data-testid', 'data-test', 'data-cy']),
            artifactsPath: self::string('tetryon.artifacts.path', 'tests/Browser/Artifacts'),
        );
    }

    private static function string(string $key, string $default): string
    {
        $value = config($key, $default);

        return is_string($value) ? $value : $default;
    }

    private static function nullableString(string $key): ?string
    {
        $value = config($key);

        return is_string($value) && $value !== '' ? $value : null;
    }

    private static function bool(string $key, bool $default): bool
    {
        $value = config($key, $default);

        return is_bool($value) ? $value : $default;
    }

    private static function int(string $key, int $default): int
    {
        $value = config($key, $default);

        return is_int($value) ? $value : $default;
    }

    /**
     * @param  list<string>  $default
     * @return list<string>
     */
    private static function stringList(string $key, array $default): array
    {
        $value = config($key, $default);
        if (! is_array($value)) {
            return $default;
        }

        return array_values(array_filter($value, is_string(...)));
    }
}
