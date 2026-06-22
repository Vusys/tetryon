<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use Throwable;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\ConsoleMessage;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\NetworkRecord;

/**
 * Captures the diagnostic bundle when a browser test fails — screenshot, page
 * HTML, current URL, console logs, the BiDi command trace, browser stderr, and
 * viewport — into a per-test artifact directory, and returns a human-readable
 * report pointing at them. Good errors are the product, not polish.
 */
final readonly class FailureArtifacts
{
    public function __construct(private string $basePath) {}

    public function capture(FirefoxBiDiDriver $driver, Configuration $configuration, string $testId): string
    {
        $directory = self::directoryFor($this->basePath, $testId);
        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            return "Tetryon: could not create the artifact directory \"{$directory}\".";
        }

        $url = $this->guard(static fn (): string => $driver->currentUrl()) ?? '(unknown)';
        $paths = [];

        $screenshot = $this->guard(static fn (): string => $driver->screenshot());
        if (is_string($screenshot)) {
            file_put_contents("{$directory}/screenshot.png", $screenshot);
            $paths['Screenshot'] = "{$directory}/screenshot.png";
        }

        $html = $this->guard(static fn (): mixed => $driver->evaluateScript('document.documentElement.outerHTML'));
        if (is_string($html)) {
            file_put_contents("{$directory}/page.html", $html);
            $paths['HTML'] = "{$directory}/page.html";
        }

        $console = array_map(
            static fn (ConsoleMessage $message): string => "[{$message->level}] {$message->source}: {$message->text}",
            $this->guard(static fn (): array => $driver->consoleMessages()) ?? [],
        );
        file_put_contents("{$directory}/console.log", implode("\n", $console));
        $paths['Console'] = "{$directory}/console.log";

        $network = array_map(
            static fn (NetworkRecord $record): string => sprintf(
                '%s %s %s',
                $record->status === null ? '(pending)' : (string) $record->status,
                $record->method,
                $record->url,
            ),
            $this->guard(static fn (): array => $driver->networkLog()) ?? [],
        );
        file_put_contents("{$directory}/network.log", implode("\n", $network));
        $paths['Network'] = "{$directory}/network.log";

        file_put_contents("{$directory}/trace.log", (string) $driver->trace());
        $paths['Trace'] = "{$directory}/trace.log";

        file_put_contents("{$directory}/browser-stderr.log", $driver->browserStderr());

        file_put_contents(
            "{$directory}/info.txt",
            "URL: {$url}\nViewport: {$configuration->viewport->width}x{$configuration->viewport->height}\n",
        );

        return $this->report($url, $paths);
    }

    public static function directoryFor(string $basePath, string $testId): string
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]+/', '_', $testId);
        $safe = $safe === null ? '' : trim($safe, '_');

        return rtrim($basePath, '/').'/'.($safe === '' ? 'test' : $safe);
    }

    /**
     * @param  array<string, string>  $paths
     */
    private function report(string $url, array $paths): string
    {
        $lines = ['', 'Tetryon browser diagnostics', '', 'Current URL:', "  {$url}", '', 'Artifacts:'];
        foreach ($paths as $label => $path) {
            $lines[] = "  {$label}: {$path}";
        }

        return implode("\n", $lines);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T|null
     */
    private function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable) {
            return null;
        }
    }
}
