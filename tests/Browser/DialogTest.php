<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Core\Config\Timeouts;
use Vusys\Tetryon\Core\Dialog\UnhandledDialogException;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Native dialogs against real Firefox (issue #118): a journey through
 * `window.confirm` can be driven in either direction, `window.prompt` can be
 * answered, the wording can be asserted, and a dialog nobody arranged an answer
 * for fails loudly instead of wedging the session.
 */
final class DialogTest extends StaticSiteTestCase
{
    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        // Keeps the unhandled-dialog case off the 5s default: it must fail on
        // the action, not sit out a timeout.
        return new Configuration(baseUrl: $baseUrl, timeouts: new Timeouts(default: 2000));
    }

    public function test_a_confirm_can_be_accepted(): void
    {
        $this->browser()
            ->visit('/dialogs.html')
            ->acceptDialog()
            ->press('Delete preset')
            ->assertSee('deleted');
    }

    public function test_a_confirm_can_be_dismissed(): void
    {
        $this->browser()
            ->visit('/dialogs.html')
            ->dismissDialog()
            ->press('Delete preset')
            ->assertSee('kept');
    }

    public function test_an_alert_can_be_accepted_and_the_journey_continues(): void
    {
        $this->browser()
            ->visit('/dialogs.html')
            ->acceptDialog()
            ->press('Notify')
            ->assertSee('acknowledged');
    }

    public function test_a_prompt_can_be_answered_with_text(): void
    {
        $this->browser()
            ->visit('/dialogs.html')
            ->typeInDialog('Weekly')
            ->press('Rename preset')
            ->assertSee('renamed to Weekly');
    }

    public function test_a_prompt_can_be_cancelled(): void
    {
        $this->browser()
            ->visit('/dialogs.html')
            ->dismissDialog()
            ->press('Rename preset')
            ->assertSee('cancelled');
    }

    public function test_the_dialog_message_can_be_asserted(): void
    {
        $this->browser()
            ->visit('/dialogs.html')
            ->acceptDialog()
            ->press('Delete preset')
            ->assertDialogMessage("Delete the preset 'Nightly'?")
            ->assertSee('deleted');
    }

    public function test_an_expected_message_that_does_not_match_fails_on_the_action(): void
    {
        $this->expectException(UnhandledDialogException::class);
        $this->expectExceptionMessage('Expected a dialog saying "Delete everything"');

        $this->browser()
            ->visit('/dialogs.html')
            ->acceptDialog('Delete everything')
            ->press('Delete preset');
    }

    public function test_an_unexpected_dialog_fails_on_the_action_instead_of_hanging(): void
    {
        $this->expectException(UnhandledDialogException::class);
        $this->expectExceptionMessage("An unhandled dialog appeared (confirm: \"Delete the preset 'Nightly'?\")");

        $this->browser()
            ->visit('/dialogs.html')
            ->press('Delete preset');
    }

    public function test_the_session_survives_an_unexpected_dialog(): void
    {
        $browser = $this->browser()->visit('/dialogs.html');

        try {
            $browser->press('Delete preset');
            self::fail('Expected an UnhandledDialogException.');
        } catch (UnhandledDialogException) {
            // The dialog was dismissed on the way out, so the session is usable.
        }

        $browser->assertSee('kept')->assertTitleIs('Native dialogs');
    }

    public function test_an_arranged_answer_is_one_shot(): void
    {
        $browser = $this->browser()
            ->visit('/dialogs.html')
            ->acceptDialog()
            ->press('Delete preset')
            ->assertSee('deleted');

        $this->expectException(UnhandledDialogException::class);
        $this->expectExceptionMessage('An unhandled dialog appeared');

        $browser->press('Delete preset');
    }
}
