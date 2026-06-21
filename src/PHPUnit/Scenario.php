<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

/**
 * The given/when/then form of the natural-language layer — a thin, readable
 * wrapper that forwards each clause to {@see Browser::step()}. The clause verbs
 * (`given`/`when`/`and`/`but`/`then`) are interchangeable; they read as English,
 * they do not change behaviour.
 */
final readonly class Scenario
{
    public function __construct(private Browser $browser) {}

    public function given(string $step): self
    {
        return $this->run($step);
    }

    public function when(string $step): self
    {
        return $this->run($step);
    }

    public function and(string $step): self
    {
        return $this->run($step);
    }

    public function but(string $step): self
    {
        return $this->run($step);
    }

    public function then(string $step): self
    {
        return $this->run($step);
    }

    public function browser(): Browser
    {
        return $this->browser;
    }

    private function run(string $step): self
    {
        $this->browser->step($step);

        return $this;
    }
}
