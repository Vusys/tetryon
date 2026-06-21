<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * Drives a real Vue 3 single-page app (served as static files, no build step)
 * to prove Tetryon handles client-side rendering — reactive updates, async
 * data, and form validation — with no manual waits.
 */
final class VueAppTest extends StaticSiteTestCase
{
    public function test_reactivity_async_and_form_validation(): void
    {
        $this->browser()
            ->visit('/vue-app/index.html')
            // reactive counter + conditional rendering
            ->assertDontSee('Clicked')
            ->press('Increment')
            ->assertSee('Clicked 1 times')
            // async-loaded list (renders ~400ms later — auto-waited)
            ->press('Load users')
            ->assertSee('Ada Lovelace')
            ->assertSee('Grace Hopper')
            // reactive form validation as the model changes
            ->fill('Email', 'bad')
            ->assertSee('Invalid email')
            ->fill('Email', 'ada@example.com')
            ->assertDontSee('Invalid email')
            ->press('Sign up')
            ->assertSee('Welcome, ada@example.com');
    }

    public function test_client_side_view_switching(): void
    {
        $this->browser()
            ->visit('/vue-app/index.html')
            ->assertSee('Increment')
            ->click('About')
            ->assertSee('About this app')
            ->assertDontSee('Increment')   // home view unmounted, no page reload
            ->click('Home')
            ->assertSee('Increment');
    }
}
