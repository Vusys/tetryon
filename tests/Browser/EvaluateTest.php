<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Exercises the public evaluate() escape hatch against real Firefox: plain
 * expressions, awaited promises, and the protected driver() accessor.
 */
final class EvaluateTest extends StaticSiteTestCase
{
    public function test_it_evaluates_a_plain_expression(): void
    {
        $title = $this->browser()->visit('/index.html')->evaluate('document.title');

        self::assertSame('Tetryon Spike', $title);
    }

    public function test_it_awaits_a_promise(): void
    {
        $value = $this->browser()
            ->visit('/index.html')
            ->evaluate('(async () => { await Promise.resolve(); return 1 + 2; })()');

        self::assertSame(3, $value);
    }

    public function test_it_can_mutate_page_state(): void
    {
        $browser = $this->browser()->visit('/index.html');
        $browser->evaluate('document.querySelector("[data-testid=heading]").textContent = "Changed by JS"');

        $browser->assertSee('Changed by JS');
    }

    public function test_driver_accessor_returns_the_running_driver(): void
    {
        $this->browser()->visit('/index.html');

        self::assertSame('Tetryon Spike', $this->driver()->title());
    }
}
