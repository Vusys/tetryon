<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Config;

use InvalidArgumentException;

/**
 * Top-level package configuration. Framework-agnostic: built from explicit
 * values, an array, or the environment. The PHPUnit and Laravel layers adapt
 * their own config sources into this.
 */
final readonly class Configuration
{
    public function __construct(
        public string $baseUrl = 'http://127.0.0.1:8000',
        public bool $headless = true,
        public ?string $firefoxBinary = null,
        public Timeouts $timeouts = new Timeouts,
        public Viewport $viewport = new Viewport,
    ) {
        if (filter_var($baseUrl, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException("Configured base_url \"{$baseUrl}\" is not a valid URL.");
        }
    }

    /**
     * @param  array{base_url?: string, headless?: bool, firefox_binary?: string|null, timeout?: array{default?: int, navigation?: int, assertion?: int}, viewport?: array{width?: int, height?: int}}  $config
     */
    public static function fromArray(array $config): self
    {
        return new self(
            baseUrl: $config['base_url'] ?? 'http://127.0.0.1:8000',
            headless: $config['headless'] ?? true,
            firefoxBinary: $config['firefox_binary'] ?? null,
            timeouts: Timeouts::fromArray($config['timeout'] ?? []),
            viewport: Viewport::fromArray($config['viewport'] ?? []),
        );
    }

    public static function fromEnvironment(): self
    {
        return new self(
            baseUrl: self::env('TETRYON_BASE_URL') ?? 'http://127.0.0.1:8000',
            headless: self::boolEnv('TETRYON_HEADLESS', true),
            firefoxBinary: self::env('TETRYON_FIREFOX_BINARY'),
        );
    }

    /**
     * Resolve a path or absolute URL against the base URL.
     */
    public function resolveUrl(string $pathOrUrl): string
    {
        if (preg_match('#^[a-z][a-z0-9+.-]*://#i', $pathOrUrl) === 1) {
            return $pathOrUrl;
        }

        return rtrim($this->baseUrl, '/').'/'.ltrim($pathOrUrl, '/');
    }

    private static function env(string $key): ?string
    {
        $value = getenv($key);

        return $value === false || $value === '' ? null : $value;
    }

    private static function boolEnv(string $key, bool $default): bool
    {
        $value = self::env($key);

        return $value === null ? $default : filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }
}
