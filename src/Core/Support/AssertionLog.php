<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

use Vusys\Tetryon\PHPUnit\Browser;

/**
 * Every assertion a {@see Browser} has performed since
 * the last drain, in call order. A plain mutable collaborator rather than a
 * property on `Browser` itself, since `Browser` is a readonly class.
 */
final class AssertionLog
{
    /** @var list<string> */
    private array $entries = [];

    public function record(string $description): void
    {
        $this->entries[] = $description;
    }

    /**
     * @return list<string>
     */
    public function drain(): array
    {
        $entries = $this->entries;
        $this->entries = [];

        return $entries;
    }
}
