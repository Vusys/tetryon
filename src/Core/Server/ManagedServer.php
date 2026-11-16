<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Server;

use RuntimeException;

/**
 * The app server Tetryon starts for this process when the server is managed
 * (#74). Under paratest each worker gets its own, on `base_port + TEST_TOKEN`,
 * running with the worker's environment, so the served app and the worker
 * agree on per-token settings like the database. Started on first use, reused
 * for every test in the process, stopped when the process exits.
 */
final class ManagedServer
{
    private static ?self $running = null;

    /** @param resource $process */
    private function __construct(
        private $process,
        public readonly string $baseUrl,
        private readonly string $stderrPath,
    ) {}

    /** Start this process's server if it isn't running, and return its base URL. */
    public static function ensure(ServerSettings $settings): string
    {
        $running = self::$running;
        if ($running instanceof self && $running->isRunning()) {
            return $running->baseUrl;
        }

        $running?->stop();
        $server = self::$running = self::start($settings);
        register_shutdown_function(static fn () => self::stopRunning());

        return $server->baseUrl;
    }

    public static function stopRunning(): void
    {
        self::$running?->stop();
        self::$running = null;
    }

    private static function start(ServerSettings $settings): self
    {
        $env = getenv();
        foreach ($settings->env as $name => $value) {
            $env[$name] = $settings->expand($value);
        }

        $stderrPath = (string) tempnam(sys_get_temp_dir(), 'tetryon-server-');
        $process = proc_open(
            $settings->expand($settings->command),
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => ['file', $stderrPath, 'w']],
            $pipes,
            $settings->workingDirectory,
            $env,
        );
        if (! is_resource($process)) {
            throw new RuntimeException("Tetryon could not start the app server: {$settings->command}");
        }

        $server = new self($process, $settings->baseUrl(), $stderrPath);
        $server->awaitReady($settings);

        return $server;
    }

    private function awaitReady(ServerSettings $settings): void
    {
        $url = rtrim($this->baseUrl, '/').'/'.ltrim($settings->readyPath, '/');
        $deadline = microtime(true) + $settings->readyTimeoutMs / 1000;
        $context = stream_context_create(['http' => ['timeout' => 1, 'ignore_errors' => true]]);

        while (microtime(true) < $deadline) {
            if (! $this->isRunning()) {
                break;
            }
            if (@file_get_contents($url, false, $context) !== false) {
                return;
            }
            usleep(100_000);
        }

        $stderr = trim((string) @file_get_contents($this->stderrPath));
        $this->stop();

        throw new RuntimeException(sprintf(
            "Tetryon's managed app server did not answer %s within %dms.%s",
            $url,
            $settings->readyTimeoutMs,
            $stderr === '' ? '' : "\nServer output:\n".$stderr,
        ));
    }

    /**
     * @return list<int> the process's descendants, parents before children
     */
    private static function descendants(int $pid): array
    {
        $found = [];
        $output = [];
        @exec('pgrep -P '.$pid.' 2>/dev/null', $output);
        foreach ($output as $line) {
            $child = (int) trim($line);
            if ($child > 0) {
                $found = [...$found, $child, ...self::descendants($child)];
            }
        }

        return $found;
    }

    private function isRunning(): bool
    {
        return is_resource($this->process) && proc_get_status($this->process)['running'];
    }

    private function stop(): void
    {
        if (is_resource($this->process)) {
            // The command runs under a shell, and artisan serve runs the PHP
            // server as its own child, so stop the whole tree, leaves first.
            $pids = self::descendants(proc_get_status($this->process)['pid']);
            foreach (array_reverse($pids) as $pid) {
                @exec('kill -TERM '.$pid.' 2>/dev/null');
            }
            proc_terminate($this->process);
            proc_close($this->process);
        }
        @unlink($this->stderrPath);
    }
}
