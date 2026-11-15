<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Browser;

use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\Record;
use Vusys\Tetryon\PHPUnit\Report\Moment;

/**
 * #[Record] turns recording on as the browser starts, before any setup, and
 * unrecorded() keeps setup out of the story.
 */
#[Record]
final class RecordAttributeTest extends StaticSiteTestCase
{
    public function test_a_class_attribute_records_from_the_first_command(): void
    {
        $browser = $this->browser();
        self::assertTrue($browser->isRecording());

        $recording = $browser->visit('/index.html')->assertSee('Hello, Tetryon')
            ->finishedRecording('RecordAttributeTest::test', true, null, 'Fallback title');

        self::assertSame('Fallback title', $recording?->title);
        self::assertSame('assertSee("Hello, Tetryon")', $recording->moments[0]->caption);
    }

    #[Record('Its own title')]
    public function test_a_method_attribute_wins_over_the_class(): void
    {
        $recording = $this->browser()->visit('/index.html')
            ->finishedRecording('RecordAttributeTest::test', true, null, 'Fallback title');

        self::assertSame('Its own title', $recording?->title);
    }

    public function test_unrecorded_setup_leaves_nothing_in_the_recording(): void
    {
        $recording = $this->browser()
            ->unrecorded(fn (Browser $b): Browser => $b->visit('/index.html')->assertSee('Hello, Tetryon'))
            ->finishedRecording('RecordAttributeTest::test', true, null, 'Fallback title');

        $captions = array_map(static fn (Moment $moment): string => $moment->caption, $recording->moments ?? []);
        self::assertSame(['Passed'], $captions);
    }
}
