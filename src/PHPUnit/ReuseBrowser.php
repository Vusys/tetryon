<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use Attribute;

/**
 * Launch one browser for the whole test class instead of one per test (#78).
 * The class's `authenticate()` hook runs once, after launch, and the browser's
 * cookies are snapshotted the moment it returns. Before every test the browser
 * is restored to that snapshot and left on `about:blank`, so each test starts
 * from the same state without paying for a launch or a login. A browser that
 * stopped answering is relaunched and authenticated again.
 *
 * `storage: true` also snapshots the origin's localStorage and sessionStorage,
 * for apps that keep auth there. Reading and writing them needs a document on
 * the origin, so it costs a visit to the base URL on each restore.
 *
 * ```php
 * #[ReuseBrowser]
 * final class DashboardTest extends BrowserTestCase
 * {
 *     protected function authenticate(Browser $browser): void
 *     {
 *         $this->loginAs($this->admin);
 *     }
 * }
 * ```
 *
 * The {@see ReusesBrowser} trait does the same, for those who prefer a trait.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final readonly class ReuseBrowser
{
    public function __construct(public bool $storage = false) {}
}
