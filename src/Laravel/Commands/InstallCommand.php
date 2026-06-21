<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * `php artisan tetryon:install` — publish the config and scaffold a first
 * browser test so a fresh app is ready to run `--testsuite=Browser`.
 */
final class InstallCommand extends Command
{
    protected $signature = 'tetryon:install';

    protected $description = 'Publish the Tetryon config and scaffold a browser test.';

    public function handle(Filesystem $files): int
    {
        $this->call('vendor:publish', ['--tag' => 'tetryon-config']);

        $directory = base_path('tests/Browser');
        $files->ensureDirectoryExists($directory.'/Artifacts');

        $this->scaffold($files, $directory.'/Artifacts/.gitignore', "*\n!.gitignore\n");
        $this->scaffold($files, $directory.'/ExampleTest.php', $this->exampleTest());

        $this->info('Tetryon installed. Run: php artisan test --testsuite=Browser');

        return self::SUCCESS;
    }

    private function scaffold(Filesystem $files, string $path, string $contents): void
    {
        if ($files->exists($path)) {
            $this->line("  <fg=yellow>exists</>  {$path}");

            return;
        }

        $files->put($path, $contents);
        $this->line("  <fg=green>created</> {$path}");
    }

    private function exampleTest(): string
    {
        return <<<'PHP'
            <?php

            declare(strict_types=1);

            namespace Tests\Browser;

            use Vusys\Tetryon\Laravel\BrowserTestCase;

            final class ExampleTest extends BrowserTestCase
            {
                public function test_the_application_loads(): void
                {
                    $this->browser()
                        ->visit('/')
                        ->assertSee('Laravel');
                }
            }

            PHP;
    }
}
