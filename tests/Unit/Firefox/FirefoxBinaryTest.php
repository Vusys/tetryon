<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Exception\FirefoxBinaryNotFoundException;
use Vusys\Tetryon\Firefox\FirefoxBinary;

#[CoversClass(FirefoxBinary::class)]
#[CoversClass(FirefoxBinaryNotFoundException::class)]
final class FirefoxBinaryTest extends TestCase
{
    public function test_default_candidates_expand_every_path_directory(): void
    {
        $candidates = FirefoxBinary::defaultCandidates('Linux', '/usr/bin:/usr/local/bin');

        self::assertContains('/usr/bin/firefox', $candidates);
        self::assertContains('/usr/bin/firefox-esr', $candidates);
        self::assertContains('/usr/local/bin/firefox', $candidates);
        self::assertNotContains('/Applications/Firefox.app/Contents/MacOS/firefox', $candidates);
    }

    public function test_default_candidates_add_the_macos_app_bundle(): void
    {
        $candidates = FirefoxBinary::defaultCandidates('Darwin', '/usr/bin');

        self::assertContains('/Applications/Firefox.app/Contents/MacOS/firefox', $candidates);
    }

    public function test_locate_prefers_an_executable_override(): void
    {
        $binary = new FirefoxBinary(static fn (string $path): bool => $path === '/opt/ff/firefox');

        self::assertSame('/opt/ff/firefox', $binary->locate('/opt/ff/firefox', ['/usr/bin/firefox']));
    }

    public function test_locate_falls_through_to_the_first_executable_candidate(): void
    {
        $binary = new FirefoxBinary(static fn (string $path): bool => $path === '/usr/bin/firefox');

        self::assertSame('/usr/bin/firefox', $binary->locate(null, ['/nope/firefox', '/usr/bin/firefox']));
    }

    public function test_locate_throws_with_guidance_when_nothing_matches(): void
    {
        $binary = new FirefoxBinary(static fn (string $path): bool => false);

        $this->expectException(FirefoxBinaryNotFoundException::class);
        $this->expectExceptionMessage('TETRYON_FIREFOX_BINARY');

        $binary->locate('/x/firefox', ['/y/firefox']);
    }
}
