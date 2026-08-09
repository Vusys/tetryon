<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Resolution pierces open shadow roots for CSS-based targets — test attributes,
 * placeholder, name, explicit CSS/id — so a web-component app rendered into
 * nested shadow roots is drivable (#151). XPath strategies (label/button/link
 * text) still don't pierce.
 */
final class ShadowDomTest extends StaticSiteTestCase
{
    public function test_drives_elements_inside_nested_shadow_roots_by_test_attribute(): void
    {
        $this->browser()
            ->visit('/shadow.html')
            ->fill('@email', 'ada@example.com')
            ->click('@save')
            ->assertSee('saved:ada@example.com');
    }

    public function test_fill_by_placeholder_pierces_shadow(): void
    {
        $this->browser()
            ->visit('/shadow.html')
            ->fill('Email', 'grace@example.com')
            ->click('@save')
            ->assertSee('saved:grace@example.com');
    }
}
