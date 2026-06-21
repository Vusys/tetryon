<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Console;

use Vusys\Tetryon\Core\Config\Configuration;

/**
 * The small `tetryon` CLI. Deliberately tiny — no competing test runner, just
 * `doctor` (preflight checks) and `help`.
 *
 * @param  resource  $output
 */
final class Application
{
    /** @var resource */
    private $output;

    /**
     * @param  resource|null  $output
     */
    public function __construct($output = null)
    {
        $this->output = $output ?? STDOUT;
    }

    /**
     * @param  list<string>  $argv
     */
    public function run(array $argv): int
    {
        return match ($argv[1] ?? 'help') {
            'doctor' => $this->doctor(),
            default => $this->help(),
        };
    }

    private function doctor(): int
    {
        $doctor = Doctor::inspect(Configuration::fromEnvironment());
        fwrite($this->output, $doctor->report()."\n");

        return $doctor->passed() ? 0 : 1;
    }

    private function help(): int
    {
        fwrite($this->output, implode("\n", [
            'Tetryon — PHP-native browser testing for PHPUnit.',
            '',
            'Usage:',
            '  tetryon doctor    Check that the environment is ready for browser tests.',
            '  tetryon help      Show this help.',
            '',
            'Run tests through PHPUnit: vendor/bin/phpunit --testsuite Browser',
            '',
        ]));

        return 0;
    }
}
