<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Diagnostics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Core\Diagnostics\ArtifactBag;

#[CoversClass(ArtifactBag::class)]
final class ArtifactBagTest extends TestCase
{
    public function test_a_browser_that_never_left_the_blank_page_is_blank(): void
    {
        self::assertTrue($this->bag('about:blank')->isBlank());
    }

    public function test_a_visited_page_is_not_blank(): void
    {
        self::assertFalse($this->bag('http://127.0.0.1:8000/login')->isBlank());
    }

    public function test_console_or_network_activity_is_not_blank(): void
    {
        self::assertFalse($this->bag('about:blank', console: ['error: boom'])->isBlank());
        self::assertFalse($this->bag('about:blank', network: ['GET /api 500'])->isBlank());
    }

    /**
     * @param  list<string>  $console
     * @param  list<string>  $network
     */
    private function bag(string $url, array $console = [], array $network = []): ArtifactBag
    {
        return new ArtifactBag($url, null, null, $console, $network, '', '');
    }
}
