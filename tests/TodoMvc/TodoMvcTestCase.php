<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use Override;
use Vusys\Tetryon\Core\Config\Configuration;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;
use Vusys\Tetryon\PHPUnit\Browser;
use Vusys\Tetryon\PHPUnit\BrowserTestCase;
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

        // An app that renders into shadow roots (lit) is unreachable by the
        // light-DOM selector strategy, so only the framework-marker smoke test
        // (which reads the light-DOM <html>) can run; the behavioural scenarios
        // are all blocked on the same cause (#151). Skip them in one place rather
        // than repeating the reason across every knownIssues entry.
        if ($this->app()->usesShadowDom && $this->name() !== 'test_the_app_loads_with_its_framework_marker') {
            self::markTestSkipped("{$this->app()->name} renders into shadow roots, unreachable by the current selector strategy (#151).");
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
    // `a.selected`. The `// falls back` notes mark the two API gaps this suite
    // surfaced (#141 assertFocused, #142 blur).

    protected function newTodo(Browser $browser, string $text): Browser
    {
        return $browser->fill('What needs to be done?', $text)->pressKey('Enter');
    }

    public function test_empty_state_hides_the_main_and_footer(): void
    {
        $this->visitApp()
            ->assertMissing('.main')
            ->assertMissing('.footer');
    }

    public function test_adding_a_todo_clears_the_input_and_counts_it(): void
    {
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->assertValue('.new-todo', '')
            ->assertSee('Buy milk')
            ->assertSee('1 item left');
    }

    public function test_the_counter_pluralises(): void
    {
        $browser = $this->visitApp();
        $this->newTodo($browser, 'Buy milk');
        $this->newTodo($browser, 'Walk the dog')->assertSee('2 items left');
    }

    public function test_input_is_trimmed_and_whitespace_only_is_rejected(): void
    {
        $browser = $this->visitApp();
        $this->newTodo($browser, '   Buy milk   ')
            ->assertSee('1 item left')
            ->assertExpression("[...document.querySelectorAll('.todo-list li label')].some(l => l.textContent.trim() === 'Buy milk')");
        $this->newTodo($browser, '     ')->assertSee('1 item left');
    }

    public function test_toggling_a_todo_completes_it_and_updates_the_count(): void
    {
        $browser = $this->visitApp();
        $this->newTodo($browser, 'Buy milk');
        $this->newTodo($browser, 'Walk the dog')
            ->check('Buy milk')
            ->assertSee('1 item left')
            ->assertExpression("[...document.querySelectorAll('.todo-list li')].some(li => li.classList.contains('completed') && li.textContent.includes('Buy milk'))");
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
        $this->newTodo($browser, 'Buy milk');
        $this->newTodo($browser, 'Walk the dog')
            ->check($toggleAll)
            ->assertSee('0 items left')
            ->uncheck($toggleAll)
            ->assertSee('2 items left');
    }

    public function test_double_click_enters_edit_mode_and_focuses_the_input(): void
    {
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->doubleClick('Buy milk')
            ->assertExpression("[...document.querySelectorAll('.todo-list li')].some(li => li.classList.contains('editing') && li.textContent.includes('Buy milk'))")
            // falls back to evaluate() — no assertFocused() verb yet, see #141
            ->assertExpression("document.activeElement === document.querySelector('.todo-list li.editing .edit')");
    }

    public function test_enter_saves_an_edit(): void
    {
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->doubleClick('Buy milk')
            ->fill('.todo-list li.editing .edit', 'Buy oat milk')
            ->pressKey('Enter')
            ->assertSee('Buy oat milk')
            ->assertDontSee('Buy milk');
    }

    public function test_escape_discards_an_edit(): void
    {
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->doubleClick('Buy milk')
            ->fill('.todo-list li.editing .edit', 'Discarded change')
            ->pressKey('Escape')
            ->assertSee('Buy milk')
            ->assertDontSee('Discarded change');
    }

    public function test_blur_saves_an_edit(): void
    {
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->doubleClick('Buy milk')
            ->fill('.todo-list li.editing .edit', 'Buy oat milk')
            // falls back to pressKey('Tab') as a blur stand-in — no blur() verb yet, see #142
            ->pressKey('Tab')
            ->assertSee('Buy oat milk');
    }

    public function test_editing_to_empty_destroys_the_item(): void
    {
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->doubleClick('Buy milk')
            ->fill('.todo-list li.editing .edit', '')
            ->pressKey('Enter')
            ->assertDontSee('Buy milk')
            ->assertMissing('.todo-list li');
    }

    public function test_hovering_reveals_destroy_and_clicking_removes_the_item(): void
    {
        $browser = $this->visitApp();
        $this->newTodo($browser, 'Buy milk');
        $this->newTodo($browser, 'Walk the dog')
            ->hover('Walk the dog')
            // .destroy carries no accessible text, so the CSS contract is the only
            // handle; only the hovered row's button is clickable, so it wins.
            ->click('.todo-list li .destroy')
            ->assertDontSee('Walk the dog')
            ->assertSee('Buy milk');
    }

    public function test_filters_move_the_selection_and_filter_the_list(): void
    {
        $browser = $this->visitApp();
        $this->newTodo($browser, 'Buy milk');
        $this->newTodo($browser, 'Walk the dog')
            ->check('Buy milk')
            ->click('Active')
            ->assertSee('Walk the dog')->assertDontSee('Buy milk')
            ->assertExpression("document.querySelector('.filters a.selected').textContent.trim() === 'Active'")
            ->click('Completed')
            ->assertSee('Buy milk')->assertDontSee('Walk the dog')
            ->click('All')
            ->assertSee('Buy milk')->assertSee('Walk the dog');
    }

    public function test_clear_completed_removes_completed_then_hides_itself(): void
    {
        $browser = $this->visitApp();
        $this->newTodo($browser, 'Buy milk');
        $this->newTodo($browser, 'Walk the dog')
            ->check('Buy milk')
            ->press('Clear completed')
            ->assertDontSee('Buy milk')
            ->assertSee('Walk the dog')
            ->assertMissing('.clear-completed');
    }

    public function test_the_active_filter_survives_a_reload(): void
    {
        // The apps we ship don't persist todos (see the epic), so this asserts
        // the hash route itself survives a reload — assertable now that
        // currentPath() keeps the fragment (#140) — not the filtered list.
        $this->newTodo($this->visitApp(), 'Buy milk')
            ->click('Completed')
            ->assertPathIs($this->app()->url().'#/completed')
            ->refresh()
            ->assertPathIs($this->app()->url().'#/completed');
    }
}
