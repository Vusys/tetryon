<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Resolution pierces open shadow roots — for CSS-based targets (test attributes,
 * placeholder, name, explicit CSS/id) (#151) and for the text/label strategies
 * (button/link text, label association) via a JS matcher, since XPath can't cross
 * shadow boundaries (#162). So a web-component app in nested shadow roots is
 * drivable behaviourally.
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

    public function test_click_by_link_text_pierces_shadow(): void
    {
        $this->browser()
            ->visit('/shadow.html')
            ->click('Go')
            ->assertSee('linked');
    }

    public function test_press_by_button_text_pierces_shadow(): void
    {
        $this->browser()
            ->visit('/shadow.html')
            ->fill('@email', 'ada@example.com')
            ->press('Save')
            ->assertSee('saved:ada@example.com');
    }

    public function test_check_by_sibling_label_pierces_shadow(): void
    {
        $this->browser()
            ->visit('/shadow.html')
            ->check('Accept terms')
            ->assertChecked('.accept')
            ->assertSee('accepted');
    }
}
