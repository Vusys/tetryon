<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit;

use Attribute;

/**
 * Record this test (or every test in this class) from the moment its browser
 * starts — the same as calling {@see Browser::recording()} first thing, but
 * before any setup helper has had a chance to log in or visit a page. On a
 * method it wins over one on the class.
 *
 * ```php
 * #[Record('A user reorders fields by dragging')]
 * public function test_user_can_reorder_fields(): void
 * ```
 */
#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
final readonly class Record
{
    public function __construct(public ?string $title = null) {}
}
