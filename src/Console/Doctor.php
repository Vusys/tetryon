<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Console;

use Throwable;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBiDiDriver;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\Firefox\LaunchOptions;

/**
 * Preflight checks for a Tetryon environment: PHP, extensions, Firefox,
 * a real headless launch + BiDi handshake, and a writable artifact dir.
 * Renders a readable report and reports whether everything is ready.
 */
final readonly class Doctor
{
    /**
     * @param  list<Check>  $checks
     */
    public function __construct(private array $checks) {}

    public static function inspect(Configuration $configuration): self
    {
        return new self([
            self::phpVersion(),
            self::extensions(),
            self::firefox($configuration),
            self::headlessLaunch($configuration),
            self::artifacts($configuration),
        ]);
    }

    public function passed(): bool
    {
        return array_all($this->checks, fn (Check $check): bool => $check->passed);
    }

    public function report(): string
    {
        $width = 0;
        foreach ($this->checks as $check) {
            $width = max($width, strlen($check->label));
        }

        $lines = ['Tetryon Doctor', ''];
        foreach ($this->checks as $check) {
            $status = $check->passed ? 'OK  ' : 'FAIL';
            $detail = $check->detail === '' ? '' : '  '.$check->detail;
            $lines[] = str_pad($check->label, $width).'   '.$status.$detail;
            if (! $check->passed && $check->fix !== null) {
                $lines[] = str_repeat(' ', $width + 3).'      → '.$check->fix;
            }
        }

        $lines[] = '';
        $lines[] = $this->passed() ? 'Ready.' : 'Not ready — fix the failures above.';

        return implode("\n", $lines);
    }

    private static function phpVersion(): Check
    {
        // Composer's `php: ^8.4` constraint already blocks installs on older
        // PHP, so by the time doctor runs the version is fine — just report it.
        return Check::pass('PHP', PHP_VERSION);
    }

    private static function extensions(): Check
    {
        $missing = array_values(array_filter(
            ['json', 'mbstring'],
            static fn (string $extension): bool => ! extension_loaded($extension),
        ));

        return $missing === []
            ? Check::pass('Extensions', 'json, mbstring')
            : Check::fail('Extensions', 'missing: '.implode(', ', $missing), 'Enable the missing PHP extensions.');
    }

    private static function firefox(Configuration $configuration): Check
    {
        try {
            $binary = new FirefoxBinary()->locate($configuration->firefoxBinary);

            return Check::pass('Firefox', $binary);
        } catch (FirefoxBinaryNotFoundException $e) {
            return Check::fail('Firefox', 'not found', $e->getMessage());
        }
    }

    private static function headlessLaunch(Configuration $configuration): Check
    {
        $driver = new FirefoxBiDiDriver(new LaunchOptions(
            headless: true,
            binary: $configuration->firefoxBinary,
        ));

        try {
            $driver->start();
            $driver->navigate('about:blank');

            return Check::pass('Headless launch');
        } catch (Throwable $e) {
            return Check::fail('Headless launch', $e->getMessage(), 'Check that Firefox can launch headless and expose WebDriver BiDi.');
        } finally {
            $driver->stop();
        }
    }

    private static function artifacts(Configuration $configuration): Check
    {
        $path = $configuration->artifactsPath;
        $existing = $path;
        while ($existing !== '' && ! is_dir($existing)) {
            $parent = dirname($existing);
            if ($parent === $existing) {
                break;
            }
            $existing = $parent;
        }

        return is_writable($existing === '' ? '.' : $existing)
            ? Check::pass('Artifacts', $path)
            : Check::fail('Artifacts', "not writable: {$path}", 'Make the artifact directory (or its parent) writable.');
    }
}
