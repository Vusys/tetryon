<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Server;

/**
 * How to start the app server a {@see ManagedServer} runs for this process
 * (#74). `{port}` and `{token}` in the command and in the extra environment
 * are replaced with the worker's port and parallel token.
 */
final readonly class ServerSettings
{
    /**
     * @param  array<string, string>  $env  set on the server on top of the inherited environment
     */
    public function __construct(
        public string $command,
        public string $host = '127.0.0.1',
        public int $basePort = 8100,
        public string $readyPath = '/',
        public int $readyTimeoutMs = 15000,
        public array $env = [],
        public ?string $workingDirectory = null,
    ) {}

    /** Paratest's TEST_TOKEN, or 0 outside a parallel run. */
    public static function token(): int
    {
        $token = getenv('TEST_TOKEN');

        return is_string($token) && ctype_digit($token) ? (int) $token : 0;
    }

    public function port(): int
    {
        return $this->basePort + self::token();
    }

    public function baseUrl(): string
    {
        return 'http://'.$this->host.':'.$this->port();
    }

    public function expand(string $template): string
    {
        return strtr($template, ['{port}' => (string) $this->port(), '{token}' => (string) self::token(), '{host}' => $this->host]);
    }
}
