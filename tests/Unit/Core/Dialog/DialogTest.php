<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Dialog;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Dialog\Dialog;
use Vusys\Tetryon\Core\Dialog\DialogExpectation;
use Vusys\Tetryon\Core\Dialog\DialogType;
use Vusys\Tetryon\Core\Dialog\UnhandledDialogException;

#[CoversClass(Dialog::class)]
#[CoversClass(DialogExpectation::class)]
#[CoversClass(DialogType::class)]
#[CoversClass(UnhandledDialogException::class)]
final class DialogTest extends TestCase
{
    public function test_it_reads_each_dialog_type(): void
    {
        self::assertSame(DialogType::Confirm, DialogType::fromDriver('confirm'));
        self::assertSame(DialogType::Prompt, DialogType::fromDriver('prompt'));
        self::assertSame(DialogType::BeforeUnload, DialogType::fromDriver('beforeunload'));
    }

    public function test_an_unrecognised_type_still_describes_the_dialog(): void
    {
        self::assertSame(DialogType::Alert, DialogType::fromDriver('something-new'));
        self::assertSame(DialogType::Alert, DialogType::fromDriver(null));
    }

    public function test_it_describes_itself_with_and_without_a_message(): void
    {
        self::assertSame(
            'confirm: "Delete the preset?"',
            new Dialog(DialogType::Confirm, 'Delete the preset?')->describe(),
        );
        self::assertSame('beforeunload', new Dialog(DialogType::BeforeUnload, '')->describe());
    }

    public function test_an_expectation_without_a_message_never_complains(): void
    {
        $expectation = new DialogExpectation(accept: true);

        self::assertNull($expectation->mismatch(new Dialog(DialogType::Confirm, 'anything at all')));
    }

    public function test_an_expected_message_matches_on_a_substring(): void
    {
        $expectation = new DialogExpectation(accept: true, expectedMessage: 'Delete the preset');

        self::assertNull($expectation->mismatch(new Dialog(DialogType::Confirm, "Delete the preset 'Nightly'?")));
    }

    public function test_a_mismatch_names_both_wordings(): void
    {
        $expectation = new DialogExpectation(accept: false, expectedMessage: 'Delete everything');

        $mismatch = $expectation->mismatch(new Dialog(DialogType::Confirm, 'Delete one thing'));

        self::assertNotNull($mismatch);
        self::assertStringContainsString('Delete everything', $mismatch);
        self::assertStringContainsString('Delete one thing', $mismatch);
        self::assertStringContainsString('confirm', $mismatch);
    }

    public function test_an_unexpected_dialog_says_what_appeared_and_what_to_do(): void
    {
        $message = UnhandledDialogException::unexpected(
            new Dialog(DialogType::Confirm, 'Delete the preset?'),
        )->getMessage();

        self::assertStringContainsString('An unhandled dialog appeared (confirm: "Delete the preset?")', $message);
        self::assertStringContainsString('acceptDialog()', $message);
    }

    public function test_a_mismatched_dialog_says_the_session_continued(): void
    {
        $message = UnhandledDialogException::mismatched('Expected a dialog saying "x".')->getMessage();

        self::assertStringContainsString('Expected a dialog saying "x".', $message);
        self::assertStringContainsString('answered anyway', $message);
    }
}
