<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

/**
 * Immutable launch configuration for a Firefox process.
 */
final readonly class LaunchOptions
{
    /**
     * @param  string|null  $binary  explicit binary path; null = auto-locate
     * @param  float  $startupTimeout  seconds to wait for the BiDi endpoint
     * @param  float  $connectTimeout  seconds to wait for the WebSocket connect
     * @param  bool  $preserveProfile  keep the temp profile for debugging
     * @param  list<string>  $extraArguments  extra Firefox CLI arguments
     */
    public function __construct(
        public bool $headless = true,
        public ?string $binary = null,
        public float $startupTimeout = 20.0,
        public float $connectTimeout = 10.0,
        public bool $preserveProfile = false,
        public array $extraArguments = [],
    ) {}
}
