<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use PHPUnit\Framework\Attributes\Group;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\SlideshowRecorder;

/**
 * Spike for issue #102: instruments two of the shared TodoMVC scenarios with
 * {@see SlideshowRecorder} to validate captions, timing, and compositing
 * against a real app's DOM before committing to the API shape more broadly.
 * Runs against the plainest app (javascript-es6) since the point here is the
 * recorder, not cross-framework coverage — that's already exercised by the
 * rest of the TodoMvc suite.
 *
 * Recording is soft-optional (issue #102, Decision 3): each test's real
 * assertions pass or fail on their own; if magick/ffmpeg aren't on PATH, or
 * compositing fails, {@see SlideshowRecorder::render()} returns null and the
 * reason is logged to stderr rather than failing the test.
 */
#[Group('todomvc')]
final class SlideshowRecorderSpikeTest extends TodoMvcTestCase
{
    #[Override]
    protected function app(): TodoMvcApp
    {
        // This class extends TodoMvcTestCase, so it inherits the full shared
        // behavioural suite alongside the two spike scenarios below — same
        // known es6 deviations as JavascriptEs6Test (see #143 and its
        // toggle-all note there) apply here too.
        return new TodoMvcApp(
            name: 'javascript-es6',
            path: 'examples/javascript-es6/dist/',
            toggleAllId: '',
            knownIssues: [
                'test_enter_saves_an_edit' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_editing_to_empty_destroys_the_item' => 'edit commits on blur, which headless Firefox does not fire (#143)',
                'test_toggle_all_completes_and_uncompletes_everything' => 'toggle-all handler is on the label click, not the input change; a synthetic input click does not trigger it',
            ],
        );
    }

    public function test_recording_adding_a_todo_clears_the_input_and_counts_it(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder($browser, 'Adding a todo', totalSteps: 2);

        $recorder->type($browser, 'What needs to be done?', 'Buy milk', 'Type "Buy milk"');
        $recorder->step('Press Enter', function () use ($browser): void {
            $browser->pressKey('Enter');
        });

        $browser->assertValue('.new-todo', '')->assertSee('Buy milk')->assertSee('1 item left');

        $this->reportRecording($recorder, 'adding-a-todo');
    }

    public function test_recording_double_click_enters_edit_mode_and_focuses_the_input(): void
    {
        $browser = $this->newTodo($this->visitApp(), 'Buy milk');
        $recorder = $this->recorder($browser, 'Editing a todo', totalSteps: 1);

        $recorder->step('Double-click the todo', function () use ($browser): void {
            $browser->doubleClick('Buy milk');
        });

        $browser->assertFocused('.editing .edit');

        $this->reportRecording($recorder, 'double-click-to-edit');
    }

    private function recorder(Browser $browser, string $title, int $totalSteps): SlideshowRecorder
    {
        return new SlideshowRecorder($this->driver(), $this->browserConfiguration()->artifactsPath, $title, $totalSteps);
    }

    private function reportRecording(SlideshowRecorder $recorder, string $name): void
    {
        $path = $recorder->render($this->browserConfiguration()->artifactsPath."/slideshow-{$name}.mp4");

        if ($path === null) {
            fwrite(STDERR, "\n".$recorder->skipReason()."\n");

            return;
        }

        self::assertFileExists($path);
    }
}
