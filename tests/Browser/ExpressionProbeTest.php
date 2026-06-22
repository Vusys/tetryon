<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Exercises the JavaScript state probes (#82) — waitForExpression,
 * assertExpression, assertExpressionEquals — against page state that is never
 * rendered as DOM text.
 */
final class ExpressionProbeTest extends StaticSiteTestCase
{
    public function test_assert_expression_reads_unrendered_state(): void
    {
        $this->browser()
            ->visit('/probe.html')
            ->assertExpression('window.appState.total === 42');
    }

    public function test_assert_expression_equals_diffs_a_value(): void
    {
        $this->browser()
            ->visit('/probe.html')
            ->assertExpressionEquals('window.appState.label', 'hidden-state')
            ->assertExpressionEquals('window.appState.total', 42);
    }

    public function test_wait_for_expression_polls_until_an_async_flag_flips(): void
    {
        $this->browser()
            ->visit('/probe.html')
            ->waitForExpression('window.appState.ready === true')
            ->assertExpression('window.appState.ready');
    }
}
