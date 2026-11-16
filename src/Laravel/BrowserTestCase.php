<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Laravel;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as FoundationTestCase;
use LogicException;
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
    protected function setUp(): void
    {
        self::refuseTransactionIsolation(static::class);

        parent::setUp();
    }

    /**
     * The served app reads the database over its own connection, so it never
     * sees rows a test wrote inside a transaction that is rolled back later.
     * Fail with the fix rather than let "my seeded user isn't there" puzzle
     * someone (#74).
     *
     * @param  class-string  $class
     */
    public static function refuseTransactionIsolation(string $class): void
    {
        foreach ([RefreshDatabase::class, DatabaseTransactions::class] as $trait) {
            if (in_array($trait, class_uses_recursive($class), true)) {
                throw new LogicException(sprintf(
                    '%s uses %s, which wraps each test in a transaction the served app can never see. Browser tests need committed data: use DatabaseTruncation (or DatabaseMigrations) instead.',
                    $class,
                    class_basename($trait),
                ));
            }
        }
    }

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

        // The testing-only login route is plumbing, never part of a recording.
        return $this->browser()->unrecorded(fn (Browser $browser): Browser => $browser->visit($path));
    }

    protected function browserConfiguration(): Configuration
    {
        return LaravelConfiguration::resolve();
    }
}
