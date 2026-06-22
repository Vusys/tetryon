<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

/**
 * The text verbs drive `contenteditable` editors, not just native inputs (#80):
 * fill replaces, type appends, clear empties, and value() reads the text back.
 */
final class ContentEditableTest extends StaticSiteTestCase
{
    public function test_fill_sets_the_editor_text(): void
    {
        $browser = $this->browser()->visit('/editor.html');
        $browser->fill('@bio', 'Hello from Tetryon');

        self::assertSame('Hello from Tetryon', $browser->value('@bio'));
        $browser->assertSee('Hello from Tetryon'); // input event fired → mirror updated
    }

    public function test_fill_replaces_existing_content(): void
    {
        $browser = $this->browser()->visit('/editor.html');
        $browser->fill('@note', 'Replaced');

        self::assertSame('Replaced', $browser->value('@note'));
    }

    public function test_type_inserts_text_without_clearing_first(): void
    {
        $browser = $this->browser()->visit('/editor.html');
        $browser->type('@bio', 'typed text');

        self::assertSame('typed text', $browser->value('@bio'));
    }

    public function test_clear_empties_the_editor(): void
    {
        $browser = $this->browser()->visit('/editor.html');
        $browser->clear('@note');

        self::assertSame('', $browser->value('@note'));
    }
}
