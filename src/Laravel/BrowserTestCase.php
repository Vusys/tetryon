<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;
use RuntimeException;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\InteractsWithBrowser;

/**
 * Laravel-flavoured browser test case. Boots the application (so factories, the
 * database, and other Laravel testing affordances are available) and wires
 * `$this->browser()` from the published `tetryon` config. The browser drives a
 * separately-served instance of the app — start it with `php artisan serve` (or
 * `tetryon:serve`) and point `base_url` at it.
 */
abstract class BrowserTestCase extends FoundationTestCase
{
    use InteractsWithBrowser;

    #[\Override]
    public function createApplication(): Application
    {
        $app = require Application::inferBasePath().'/bootstrap/app.php';
        if (! $app instanceof Application) {
            throw new RuntimeException('bootstrap/app.php did not return a Laravel application.');
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Log a user into the browser's session via Tetryon's testing-only login
     * route, then return the browser to continue the chain.
     */
    public function loginAs(Authenticatable $user, ?string $guard = null): Browser
    {
        $id = $user->getAuthIdentifier();
        $segment = rawurlencode(is_scalar($id) ? (string) $id : '');
        $path = '/_tetryon/login/'.$segment.($guard !== null ? '/'.rawurlencode($guard) : '');

        return $this->browser()->visit($path);
    }

    protected function browserConfiguration(): Configuration
    {
        return LaravelConfiguration::resolve();
    }
}
