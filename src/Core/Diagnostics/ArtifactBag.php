<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Diagnostics;

/**
 * The diagnostic bundle captured once when a browser test fails — screenshot,
 * page HTML, current URL, console/network logs, the BiDi trace, and browser
 * stderr — shared by the plain-text failure report and the HTML test report
 * so neither has to query the driver a second time.
 */
final readonly class ArtifactBag
{
    /**
     * @param  list<string>  $consoleLines
     * @param  list<string>  $networkLines
     */
    public function __construct(
        public string $url,
        public ?string $screenshotPng,
        public ?string $html,
        public array $consoleLines,
        public array $networkLines,
        public string $trace,
        public string $browserStderr,
    ) {}
}
