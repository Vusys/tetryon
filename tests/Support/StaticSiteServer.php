<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Support;

use RuntimeException;

/**
 * Serves a directory of static files over PHP's built-in web server on a free
 * port — the "bring your own server" model the package targets, with zero
 * extra tooling. Used by the browser smoke tests and reusable in CI.
 */
final class StaticSiteServer
{
    /**
     * @param  resource  $process
     */
    private function __construct(
        private $process,
        public readonly string $baseUrl,
    ) {}

    public static function start(string $documentRoot): self
    {
        $host = '127.0.0.1';
        $port = self::reserveFreePort($host);

        $pipes = [];
        $process = proc_open(
            [PHP_BINARY, '-S', "{$host}:{$port}", '-t', $documentRoot],
            [0 => ['pipe', 'r'], 1 => ['file', '/dev/null', 'wb'], 2 => ['file', '/dev/null', 'wb']],
            $pipes,
        );
        if (! is_resource($process)) {
            throw new RuntimeException('Could not start the static-site server.');
        }
        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        self::awaitReachable($host, $port);

        return new self($process, "http://{$host}:{$port}");
    }

    public function stop(): void
    {
        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process);
        }
        proc_close($this->process);
    }

    private static function reserveFreePort(string $host): int
    {
        $errno = 0;
        $errstr = '';
        $socket = stream_socket_server("tcp://{$host}:0", $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("Could not reserve a port: {$errstr} ({$errno}).");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);
        if ($name === false) {
            throw new RuntimeException('Could not determine the reserved port.');
        }

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }

    private static function awaitReachable(string $host, int $port): void
    {
        $deadline = microtime(true) + 10.0;
        while (microtime(true) < $deadline) {
            $errno = 0;
            $errstr = '';
            $client = @fsockopen($host, $port, $errno, $errstr, 0.2);
            if ($client !== false) {
                fclose($client);

                return;
            }
            usleep(50_000);
        }

        throw new RuntimeException("Static-site server never became reachable on {$host}:{$port}.");
    }
}
