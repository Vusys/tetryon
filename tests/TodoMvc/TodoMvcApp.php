<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\TodoMvc;

use InvalidArgumentException;

/**
 * A per-app profile for the TodoMVC compatibility suite: where the built app is
 * served from and the handful of DOM contract points that differ across the ten
 * frameworks. The shared scenario suite reads these so the scenarios themselves
 * stay identical across apps.
 *
 * `knownIssues` mirrors upstream TodoMVC's own `tests/knownIssues.js`: an honest
 * per-app skip list, keyed by the scenario's test-method name, with a reason.
 * Keeping the skips here rather than as conditionals smeared through the
 * scenarios makes the compatibility matrix fall out of the code.
 */
final readonly class TodoMvcApp
{
    /**
     * @param  string  $name  the framework marker on `<html data-framework>`
     * @param  string  $path  the app's served directory, relative to tests/Fixtures/todomvc/
     * @param  string  $toggleAllId  id of the toggle-all checkbox (`toggle-all` for most; `toggle-all-input` for vue; empty for angular/preact/javascript-es6, which give it no id)
     * @param  string  $allFilterHref  href of the "All" filter link (jquery uses `#/all`)
     * @param  bool  $usesShadowDom  whether the app renders into shadow roots (lit only)
     * @param  array<string, string>  $knownIssues  map of scenario method name => reason it is skipped
     */
    public function __construct(
        public string $name,
        public string $path,
        public string $toggleAllId = 'toggle-all',
        public string $allFilterHref = '#/',
        public bool $usesShadowDom = false,
        public array $knownIssues = [],
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('A TodoMVC app needs a non-empty framework name.');
        }
        if ($path === '') {
            throw new InvalidArgumentException("TodoMVC app \"{$name}\" needs a non-empty served path.");
        }
    }

    /**
     * The app's URL path, relative to the static-site server root
     * (tests/Fixtures/todomvc/), with a single trailing slash so `php -S`
     * serves the directory's index.
     */
    public function url(): string
    {
        return '/'.trim($this->path, '/').'/';
    }

    /**
     * A selector for the toggle-all checkbox: its `#id` when the app gives it
     * one, else the stable `.toggle-all` class (angular/preact/javascript-es6
     * render the input with no id). check()/uncheck() drive it directly, so the
     * per-app label text and any broken label association don't matter.
     */
    public function toggleAllSelector(): string
    {
        return $this->toggleAllId !== '' ? '#'.$this->toggleAllId : '.toggle-all';
    }

    public function knownIssue(string $method): ?string
    {
        return $this->knownIssues[$method] ?? null;
    }
}
