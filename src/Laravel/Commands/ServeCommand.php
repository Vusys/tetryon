<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel\Commands;

use Illuminate\Console\Command;

/**
 * `php artisan tetryon:serve` — a thin convenience wrapper around `artisan
 * serve` for the "you run the app, the package drives the browser" workflow.
 */
final class ServeCommand extends Command
{
    protected $signature = 'tetryon:serve {--host=127.0.0.1} {--port=8000}';

    protected $description = 'Serve the application for browser testing (wraps `artisan serve`).';

    public function handle(): int
    {
        return $this->call('serve', [
            '--host' => $this->option('host'),
            '--port' => $this->option('port'),
        ]);
    }
}
