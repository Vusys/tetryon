<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Laravel;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Laravel\BrowserTestCase;

final class RefusesTransactionIsolationTest extends TestCase
{
    public function test_refresh_database_is_refused_with_the_fix(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('uses RefreshDatabase, which wraps each test in a transaction the served app can never see. Browser tests need committed data: use DatabaseTruncation (or DatabaseMigrations) instead.');

        BrowserTestCase::refuseTransactionIsolation(UsesRefreshDatabase::class);
    }

    public function test_database_transactions_is_refused(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('uses DatabaseTransactions');

        BrowserTestCase::refuseTransactionIsolation(UsesDatabaseTransactions::class);
    }

    public function test_truncation_is_fine(): void
    {
        BrowserTestCase::refuseTransactionIsolation(UsesDatabaseTruncation::class);

        $this->addToAssertionCount(1);
    }
}

/** @internal */
abstract class UsesRefreshDatabase
{
    use RefreshDatabase;
}

/** @internal */
abstract class UsesDatabaseTransactions
{
    use DatabaseTransactions;
}

/** @internal */
abstract class UsesDatabaseTruncation
{
    use DatabaseTruncation;
}
