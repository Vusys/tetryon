<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Vusys\Tetryon\Firefox\Bidi\BiDiUrlParser;
use Vusys\Tetryon\Firefox\Exception\FirefoxException;

/**
 * Owns a headless Firefox process: launch with a throwaway profile and the
 * remote agent enabled, discover the BiDi WebSocket URL from stderr, and tear
 * everything down without leaking processes or temp dirs.
 *
 * `--new-instance` plus a dedicated `--profile` ensures we never attach to (or
 * kill) the user's own Firefox; teardown only ever signals our own PID.
 */
final class FirefoxProcess
{
    /**
     * @param  resource  $process
     */
    private function __construct(
        private $process,
        public readonly int $pid,
        public readonly string $bidiUrl,
        public readonly string $stderrPath,
        private readonly TemporaryProfile $profile,
        private readonly bool $preserveProfile,
    ) {}

    public static function launch(LaunchOptions $options, ?LoggerInterface $logger = null): self
    {
        $logger ??= new NullLogger;
        $binary = $options->binary ?? new FirefoxBinary()->locate(self::envBinary());
        $profile = TemporaryProfile::create();
        $stderrPath = $profile->path.'/tetryon-stderr.log';
        $stdoutPath = $profile->path.'/tetryon-stdout.log';

        $arguments = [
            $binary,
            ...($options->headless ? ['--headless'] : []),
            '--no-remote',
            '--new-instance',
            '--remote-debugging-port=0',
            '--profile', $profile->path,
            ...$options->extraArguments,
            'about:blank',
        ];

        // Redirect output to files (not pipes) so a chatty browser can never
        // fill an unread pipe buffer and deadlock; the stderr file doubles as
        // a diagnostic artifact.
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['file', $stdoutPath, 'wb'],
            2 => ['file', $stderrPath, 'wb'],
        ];

        $logger->info('Launching Firefox: {binary}', ['binary' => $binary]);

        $pipes = [];
        $process = proc_open($arguments, $descriptors, $pipes);
        if (! is_resource($process)) {
            $profile->delete();
            throw new FirefoxException('Failed to start Firefox via proc_open().');
        }

        if (isset($pipes[0]) && is_resource($pipes[0])) {
            fclose($pipes[0]);
        }

        $pid = proc_get_status($process)['pid'];

        try {
            $url = self::awaitBidiUrl($process, $stderrPath, $options->startupTimeout);
        } catch (Throwable $e) {
            self::terminate($process);
            if (! $options->preserveProfile) {
                $profile->delete();
            }
            throw $e;
        }

        $logger->info('Firefox BiDi ready at {url} (pid {pid})', ['url' => $url, 'pid' => $pid]);

        return new self($process, $pid, $url, $stderrPath, $profile, $options->preserveProfile);
    }

    public function stop(): void
    {
        self::terminate($this->process);
        if (! $this->preserveProfile) {
            $this->profile->delete();
        }
    }

    public function stderr(): string
    {
        $contents = @file_get_contents($this->stderrPath);

        return $contents === false ? '' : $contents;
    }

    /**
     * @param  resource  $process
     */
    private static function awaitBidiUrl($process, string $stderrPath, float $timeout): string
    {
        $deadline = microtime(true) + $timeout;

        while (microtime(true) < $deadline) {
            $log = @file_get_contents($stderrPath);
            if (is_string($log)) {
                $url = BiDiUrlParser::fromStartupLog($log);
                if ($url !== null) {
                    return $url;
                }
            }

            if (! self::isRunning($process)) {
                $tail = @file_get_contents($stderrPath);
                throw new FirefoxException(
                    'Firefox exited before advertising a BiDi endpoint. stderr: '
                    .($tail === false || $tail === '' ? '(empty)' : $tail),
                );
            }

            usleep(50_000);
        }

        throw new FirefoxException("Firefox did not advertise a BiDi endpoint within {$timeout}s.");
    }

    /**
     * @param  resource  $process
     */
    private static function terminate($process): void
    {
        if (self::isRunning($process)) {
            proc_terminate($process);

            $deadline = microtime(true) + 5.0;
            while (self::isRunning($process) && microtime(true) < $deadline) {
                usleep(50_000);
            }

            if (self::isRunning($process)) {
                proc_terminate($process, 9);
            }
        }

        proc_close($process);
    }

    /**
     * @param  resource  $process
     *
     * @phpstan-impure  the process can exit between calls, so the result varies
     */
    private static function isRunning($process): bool
    {
        return proc_get_status($process)['running'];
    }

    private static function envBinary(): ?string
    {
        $env = getenv('TETRYON_FIREFOX_BINARY');

        return $env === false || $env === '' ? null : $env;
    }
}
