<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
use Vusys\Tetryon\PHPUnit\SlideshowRecorder;
use Vusys\Tetryon\Tests\Support\StaticSiteServer;

/**
 * Base for the TodoMVC cross-framework compatibility suite. One `php -S` server
 * serves every fetched app from tests/Fixtures/todomvc/, and each concrete
 * subclass names the app it drives via {@see app()}. The shared behavioural
 * scenarios live here so they stay byte-identical across the ten frameworks;
 * per-app deviations are declared as `knownIssues` on the {@see TodoMvcApp}.
 *
 * Skips (never fails) when Firefox is absent or the fixtures have not been
 * fetched, so the opt-in `TodoMvc` suite is green on a machine that has run
 * neither.
 *
 * Every scenario with an action to show is instrumented with
 * {@see SlideshowRecorder} (issue #102's spike): living here once, that
 * instrumentation runs for all ten apps, and — with `TETRYON_RECORD_SUITE=1`
 * — combines into one video of the whole compatibility suite passing.
 */
abstract class TodoMvcTestCase extends BrowserTestCase
{
    protected ?StaticSiteServer $server = null;

    private const string FIXTURES = __DIR__.'/../Fixtures/todomvc';

    abstract protected function app(): TodoMvcApp;

    protected function setUp(): void
    {
        try {
            new FirefoxBinary()->locate(getenv('TETRYON_FIREFOX_BINARY') ?: null);
        } catch (FirefoxBinaryNotFoundException) {
            self::markTestSkipped('Firefox is not installed; skipping TodoMVC compatibility test.');
        }

        if (! is_dir(self::FIXTURES)) {
            self::markTestSkipped('TodoMVC fixtures are missing; run `composer todomvc:fetch` first.');
        }

        $reason = $this->app()->knownIssue($this->name());
        if ($reason !== null) {
            self::markTestSkipped("Known issue on {$this->app()->name}: {$reason}");
        }

        $this->server = StaticSiteServer::start(self::FIXTURES);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
    }

    /**
     * Navigate to the app under test and return the browser, ready for the
     * scenario to act on.
     */
    protected function visitApp(): Browser
    {
        return $this->browser()->visit($this->app()->url());
    }

    #[Override]
    protected function browserConfiguration(): Configuration
    {
        $baseUrl = $this->server instanceof StaticSiteServer ? $this->server->baseUrl : 'http://127.0.0.1:8000';

        return new Configuration(baseUrl: $baseUrl);
    }

    public function test_the_app_loads_with_its_framework_marker(): void
    {
        $this->visitApp()->assertExpressionEquals(
            'document.documentElement.dataset.framework',
            $this->app()->name,
            "Expected the {$this->app()->name} app to load with its data-framework marker.",
        );
    }

    // ── Shared behavioural scenarios (from upstream app-spec.md) ─────────────
    //
    // Written behaviourally — fill() by placeholder, press()/click() by text,
    // check()/doubleClick() by the todo's text — because the point of this suite
    // is to exercise the selector strategy, not just the driver. State that has
    // no accessible text (a completed row, the edit box, the selection) is read
    // via the stable TodoMVC CSS contract: `.new-todo`, `.todo-list li`, `.edit`,
    // `.destroy`, `.todo-count`, `.clear-completed`; `li.completed`, `li.editing`,
    // `a.selected`. Those state reads go through pierce() so they see into shadow
    // roots (Lit) as well as the light DOM (#165).

    protected function newTodo(Browser $browser, string $text): Browser
    {
        return $browser->fill('What needs to be done?', $text)->pressKey('Enter');
    }

    /**
     * The recorded equivalent of {@see newTodo()} — types the field via the
     * recorder (one step) and presses Enter as a second, separately timed
     * step, so the slideshow shows the text being typed before it commits.
     */
    private function recordNewTodo(SlideshowRecorder $recorder, Browser $browser, string $text): Browser
    {
        $recorder->type($browser, 'What needs to be done?', $text, "Type \"{$text}\"");
        $recorder->step('Press Enter', function () use ($browser): void {
            $browser->pressKey('Enter');
        });

        return $browser;
    }

    /**
     * The recorded equivalent of a bare `doubleClick($text)` — a single timed
     * step, factored out because five scenarios open the same edit box.
     */
    private function recordDoubleClickToEdit(SlideshowRecorder $recorder, Browser $browser, string $text): Browser
    {
        $recorder->step("Double-click \"{$text}\"", function () use ($browser, $text): void {
            $browser->doubleClick($text);
        });

        return $browser;
    }

    /**
     * A JS expression yielding an array of elements matching $selector across the
     * light DOM **and** every open shadow root. `document.querySelectorAll` in
     * page script stops at shadow boundaries, so a scenario's state assertions
     * would see nothing in a web-component app (Lit) without this (#165). For a
     * light-DOM app it is exactly `querySelectorAll`.
     */
    protected function pierce(string $selector): string
    {
        $sel = json_encode($selector, JSON_THROW_ON_ERROR);

        return "(()=>{const out=[];const walk=r=>{out.push(...r.querySelectorAll({$sel}));"
            ."r.querySelectorAll('*').forEach(e=>{if(e.shadowRoot)walk(e.shadowRoot);});};walk(document);return out;})()";
    }

    public function test_empty_state_hides_the_main_and_footer(): void
    {
        $this->visitApp()
            ->assertMissing('.main')
            ->assertMissing('.footer');
    }

    public function test_adding_a_todo_clears_the_input_and_counts_it(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Adding a todo', totalSteps: 2);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');

        $browser->assertValue('.new-todo', '')->assertSee('Buy milk');
        $recorder->assert('Sees "1 item left"', function () use ($browser): void {
            $browser->assertSee('1 item left');
        });
    }

    public function test_the_counter_pluralises(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Counter pluralises', totalSteps: 4);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordNewTodo($recorder, $browser, 'Walk the dog');

        $recorder->assert('Sees "2 items left"', function () use ($browser): void {
            $browser->assertSee('2 items left');
        });
    }

    public function test_input_is_trimmed_and_whitespace_only_is_rejected(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Trims input, rejects whitespace-only', totalSteps: 4);

        $this->recordNewTodo($recorder, $browser, '   Buy milk   ');
        $browser->assertSee('1 item left');
        $recorder->assert('Confirms "Buy milk" was trimmed', function () use ($browser): void {
            $browser->assertExpression($this->pierce('label').".some(l => l.textContent.trim() === 'Buy milk')");
        });

        $this->recordNewTodo($recorder, $browser, '     ');
        $browser->assertSee('1 item left');
    }

    public function test_toggling_a_todo_completes_it_and_updates_the_count(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Toggling completes a todo', totalSteps: 5);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordNewTodo($recorder, $browser, 'Walk the dog');
        $recorder->step('Check "Buy milk"', function () use ($browser): void {
            $browser->check('Buy milk');
        });

        $browser->assertSee('1 item left');
        $recorder->assert('Confirms "Buy milk" is completed', function () use ($browser): void {
            $browser->assertExpression($this->pierce('li').".some(li => li.classList.contains('completed') && li.textContent.includes('Buy milk'))");
        });
    }

    public function test_toggle_all_completes_and_uncompletes_everything(): void
    {
        // The visible control is a label whose text differs per app ("Mark all as
        // complete" vs "Toggle All Input") and whose `for=` often points at a
        // hidden input; driving the checkbox directly by its stable selector
        // sidesteps both. The real input is visually hidden, so check()/uncheck()
        // (synthetic click) is what reaches it (#139).
        $toggleAll = $this->app()->toggleAllSelector();
        $browser = $this->visitApp();
        $recorder = $this->recorder('Toggle all completes and uncompletes', totalSteps: 6);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordNewTodo($recorder, $browser, 'Walk the dog');

        $recorder->step('Check "toggle all"', function () use ($browser, $toggleAll): void {
            $browser->check($toggleAll);
        });
        $recorder->assert('Sees "0 items left"', function () use ($browser): void {
            $browser->assertSee('0 items left');
        });

        $recorder->step('Uncheck "toggle all"', function () use ($browser, $toggleAll): void {
            $browser->uncheck($toggleAll);
        });
        $browser->assertSee('2 items left');
    }

    public function test_double_click_enters_edit_mode_and_focuses_the_input(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Double-click enters edit mode', totalSteps: 3);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordDoubleClickToEdit($recorder, $browser, 'Buy milk');

        $browser->assertExpression($this->pierce('li').".some(li => li.classList.contains('editing') && li.textContent.includes('Buy milk'))");
        $recorder->assert('Confirms the edit field is focused', function () use ($browser): void {
            $browser->assertFocused('.editing .edit');
        });
    }

    public function test_enter_saves_an_edit(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Enter saves an edit', totalSteps: 5);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordDoubleClickToEdit($recorder, $browser, 'Buy milk');
        $recorder->type($browser, '.editing .edit', 'Buy oat milk', 'Edit to "Buy oat milk"');
        $recorder->step('Press Enter', function () use ($browser): void {
            $browser->pressKey('Enter');
        });

        $browser->assertDontSee('Buy milk');
        $recorder->assert('Sees "Buy oat milk"', function () use ($browser): void {
            $browser->assertSee('Buy oat milk');
        });
    }

    public function test_escape_discards_an_edit(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Escape discards an edit', totalSteps: 5);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordDoubleClickToEdit($recorder, $browser, 'Buy milk');
        $recorder->type($browser, '.editing .edit', 'Discarded change', 'Edit to "Discarded change"');
        $recorder->step('Press Escape', function () use ($browser): void {
            $browser->pressKey('Escape');
        });

        $browser->assertSee('Buy milk');
        $recorder->assert('Confirms the edit was discarded', function () use ($browser): void {
            $browser->assertDontSee('Discarded change');
        });
    }

    public function test_blur_saves_an_edit(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Blur saves an edit', totalSteps: 5);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordDoubleClickToEdit($recorder, $browser, 'Buy milk');
        $recorder->type($browser, '.editing .edit', 'Buy oat milk', 'Edit to "Buy oat milk"');
        $recorder->step('Blur the field', function () use ($browser): void {
            $browser->blur('.editing .edit');
        });

        $recorder->assert('Sees "Buy oat milk"', function () use ($browser): void {
            $browser->assertSee('Buy oat milk');
        });
    }

    public function test_editing_to_empty_destroys_the_item(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Editing to empty destroys the item', totalSteps: 5);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordDoubleClickToEdit($recorder, $browser, 'Buy milk');
        $recorder->type($browser, '.editing .edit', '', 'Clear the field');
        $recorder->step('Press Enter', function () use ($browser): void {
            $browser->pressKey('Enter');
        });

        $browser->assertDontSee('Buy milk');
        $recorder->assert('Confirms the todo was destroyed', function () use ($browser): void {
            $browser->assertMissing('.todo-list li');
        });
    }

    public function test_hovering_reveals_destroy_and_clicking_removes_the_item(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Hover reveals destroy', totalSteps: 6);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordNewTodo($recorder, $browser, 'Walk the dog');

        $recorder->step('Hover "Walk the dog"', function () use ($browser): void {
            $browser->hover('Walk the dog');
        });
        // .destroy carries no accessible text, so the CSS contract is the only
        // handle; only the hovered row's button is clickable, so it wins.
        $recorder->step('Click the destroy button', function () use ($browser): void {
            $browser->click('.destroy');
        });

        $browser->assertSee('Buy milk');
        $recorder->assert('Confirms "Walk the dog" was removed', function () use ($browser): void {
            $browser->assertDontSee('Walk the dog');
        });
    }

    public function test_filters_move_the_selection_and_filter_the_list(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Filters move the selection', totalSteps: 8);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordNewTodo($recorder, $browser, 'Walk the dog');

        $recorder->step('Check "Buy milk"', function () use ($browser): void {
            $browser->check('Buy milk');
        });
        $recorder->step('Click "Active"', function () use ($browser): void {
            $browser->click('Active');
        });
        $browser->assertSee('Walk the dog')->assertDontSee('Buy milk');
        $recorder->assert('Confirms "Active" is the selected filter', function () use ($browser): void {
            $browser->assertExpression($this->pierce('.filters a.selected')."[0]?.textContent.trim() === 'Active'");
        });

        $recorder->step('Click "Completed"', function () use ($browser): void {
            $browser->click('Completed');
        });
        $browser->assertSee('Buy milk')->assertDontSee('Walk the dog');

        $recorder->step('Click "All"', function () use ($browser): void {
            $browser->click('All');
        });
        $browser->assertSee('Buy milk')->assertSee('Walk the dog');
    }

    public function test_clear_completed_removes_completed_then_hides_itself(): void
    {
        $browser = $this->visitApp();
        $recorder = $this->recorder('Clear completed', totalSteps: 6);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $this->recordNewTodo($recorder, $browser, 'Walk the dog');

        $recorder->step('Check "Buy milk"', function () use ($browser): void {
            $browser->check('Buy milk');
        });
        $recorder->step('Press "Clear completed"', function () use ($browser): void {
            $browser->press('Clear completed');
        });

        $browser->assertDontSee('Buy milk')->assertSee('Walk the dog');
        $recorder->assert('Confirms "Clear completed" hides itself', function () use ($browser): void {
            $browser->assertMissing('.clear-completed');
        });
    }

    public function test_the_active_filter_survives_a_reload(): void
    {
        // The apps we ship don't persist todos (see the epic), so this asserts
        // the hash route itself survives a reload — assertable now that
        // currentPath() keeps the fragment (#140) — not the filtered list.
        $browser = $this->visitApp();
        $recorder = $this->recorder('Active filter survives a reload', totalSteps: 4);

        $this->recordNewTodo($recorder, $browser, 'Buy milk');
        $recorder->step('Click "Completed"', function () use ($browser): void {
            $browser->click('Completed');
        });
        $browser->assertPathIs($this->app()->url().'#/completed');

        $recorder->step('Refresh the page', function () use ($browser): void {
            $browser->refresh();
        });
        $recorder->assert('Confirms the filter survived the reload', function () use ($browser): void {
            $browser->assertPathIs($this->app()->url().'#/completed');
        });
    }
}
