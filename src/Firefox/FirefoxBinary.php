<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

use Closure;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;

/**
 * Locates a Firefox executable: an explicit override first, then a list of
 * candidate paths (by default every directory on `$PATH` plus the macOS app
 * bundle). The "is this an executable file?" probe is injectable so the
 * resolution order can be tested without touching the filesystem.
 */
final readonly class FirefoxBinary
{
    /** @var Closure(string): bool */
    private Closure $isExecutable;

    /**
     * @param  (callable(string): bool)|null  $isExecutable
     */
    public function __construct(?callable $isExecutable = null)
    {
        $this->isExecutable = $isExecutable !== null
            ? Closure::fromCallable($isExecutable)
            : static fn (string $path): bool => is_file($path) && is_executable($path);
    }

    /**
     * @param  list<string>|null  $candidates  defaults to PATH + platform paths
     */
    public function locate(?string $override = null, ?array $candidates = null): string
    {
        $candidates ??= self::defaultCandidates(PHP_OS_FAMILY, (string) getenv('PATH'));

        $tried = [];
        if ($override !== null && $override !== '') {
            $tried[] = $override;
            if (($this->isExecutable)($override)) {
                return $override;
            }
        }

        foreach ($candidates as $candidate) {
            $tried[] = $candidate;
            if (($this->isExecutable)($candidate)) {
                return $candidate;
            }
        }

        throw FirefoxBinaryNotFoundException::afterTrying($tried);
    }

    /**
     * @return list<string>
     */
    public static function defaultCandidates(string $osFamily, string $path): array
    {
        $names = ['firefox', 'firefox-esr'];

        $candidates = [];
        foreach (explode(PATH_SEPARATOR, $path) as $dir) {
            if ($dir === '') {
                continue;
            }
            foreach ($names as $name) {
                $candidates[] = rtrim($dir, '/').'/'.$name;
            }
        }

        if ($osFamily === 'Darwin') {
            $candidates[] = '/Applications/Firefox.app/Contents/MacOS/firefox';
        }

        return $candidates;
    }
}
