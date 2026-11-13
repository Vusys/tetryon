<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use Throwable;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;
use Vusys\Tetryon\Core\Diagnostics\FailureReport;
use Vusys\Tetryon\Core\Support\Slug;
use Vusys\Tetryon\Firefox\ConsoleMessage;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\NetworkRecord;

/**
 * Captures the diagnostic bundle when a browser test fails — screenshot, page
 * HTML, current URL, console logs, the BiDi command trace, browser stderr, and
 * viewport — into a per-test artifact directory, and returns a human-readable
 * report pointing at them. Good errors are the product, not polish.
 *
 * Capture and writing are split so the same {@see ArtifactBag} can also back
 * the HTML test report's diagnostics panel instead of querying the driver a
 * second time — see {@see captureBag()}.
 */
final class FailureArtifacts
{
    public static function captureBag(FirefoxBiDiDriver $driver): ArtifactBag
    {
        $url = self::guard(static fn (): string => $driver->currentUrl()) ?? '(unknown)';
        $screenshot = self::guard(static fn (): string => $driver->screenshot());
        $html = self::guard(static fn (): mixed => $driver->evaluateScript('document.documentElement.outerHTML'));

        $console = array_map(
            static fn (ConsoleMessage $message): string => "[{$message->level}] {$message->source}: {$message->text}",
            self::guard(static fn (): array => $driver->consoleMessages()) ?? [],
        );

        $network = array_map(
            static fn (NetworkRecord $record): string => sprintf(
                '%s %s %s',
                $record->status === null ? '(pending)' : (string) $record->status,
                $record->method,
                $record->url,
            ),
            self::guard(static fn (): array => $driver->networkLog()) ?? [],
        );

        return new ArtifactBag(
            url: $url,
            screenshotPng: is_string($screenshot) ? $screenshot : null,
            html: is_string($html) ? $html : null,
            consoleLines: $console,
            networkLines: $network,
            trace: (string) $driver->trace(),
            browserStderr: $driver->browserStderr(),
        );
    }

    public static function write(ArtifactBag $bag, string $directory, Configuration $configuration): string
    {
        if (! is_dir($directory) && ! @mkdir($directory, 0o777, true) && ! is_dir($directory)) {
            return "Tetryon: could not create the artifact directory \"{$directory}\".";
        }

        $paths = [];

        if ($bag->screenshotPng !== null) {
            file_put_contents("{$directory}/screenshot.png", $bag->screenshotPng);
            $paths['Screenshot'] = "{$directory}/screenshot.png";
        }

        if ($bag->html !== null) {
            file_put_contents("{$directory}/page.html", $bag->html);
            $paths['HTML'] = "{$directory}/page.html";
        }

        file_put_contents("{$directory}/console.log", implode("\n", $bag->consoleLines));
        $paths['Console'] = "{$directory}/console.log";

        file_put_contents("{$directory}/network.log", implode("\n", $bag->networkLines));
        $paths['Network'] = "{$directory}/network.log";

        file_put_contents("{$directory}/trace.log", $bag->trace);
        $paths['Trace'] = "{$directory}/trace.log";

        file_put_contents("{$directory}/browser-stderr.log", $bag->browserStderr);

        file_put_contents(
            "{$directory}/info.txt",
            "URL: {$bag->url}\nViewport: {$configuration->viewport->width}x{$configuration->viewport->height}\n",
        );

        return FailureReport::render($bag, $paths);
    }

    public static function directoryFor(string $basePath, string $testId): string
    {
        return rtrim($basePath, '/').'/'.Slug::forTestId($testId);
    }

    /**
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T|null
     */
    private static function guard(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (Throwable) {
            return null;
        }
    }
}
