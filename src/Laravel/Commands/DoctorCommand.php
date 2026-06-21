<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel\Commands;

use Illuminate\Console\Command;
use Vusys\Tetryon\Console\Doctor;
use Vusys\Tetryon\Laravel\LaravelConfiguration;

/**
 * `php artisan tetryon:doctor` — the preflight environment check, using the
 * app's `tetryon` config.
 */
final class DoctorCommand extends Command
{
    protected $signature = 'tetryon:doctor';

    protected $description = 'Check that the environment is ready for browser tests.';

    public function handle(): int
    {
        $doctor = Doctor::inspect(LaravelConfiguration::resolve());

        $this->line($doctor->report());

        return $doctor->passed() ? self::SUCCESS : self::FAILURE;
    }
}
