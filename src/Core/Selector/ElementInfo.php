<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Selector;

/**
 * The shape of a resolved element — its tag, resolved input `type`, and whether
 * it is `contenteditable` — used to decide whether a form verb can drive it and
 * to describe it in {@see UndrivableElementException} when it can't.
 */
final readonly class ElementInfo
{
    public function __construct(
        public string $tag,
        public string $type,
        public bool $editable,
    ) {}

    public static function fromJson(string $json): self
    {
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            return new self('', '', false);
        }

        return new self(
            is_string($decoded['tag'] ?? null) ? $decoded['tag'] : '',
            is_string($decoded['type'] ?? null) ? $decoded['type'] : '',
            ($decoded['editable'] ?? null) === true,
        );
    }

    /**
     * A short, human description for error messages: `input type="text"`,
     * `div contenteditable`, or just the tag.
     */
    public function describe(): string
    {
        if ($this->tag === 'input' && $this->type !== '') {
            return 'input type="'.$this->type.'"';
        }

        if ($this->editable) {
            return ($this->tag === '' ? 'element' : $this->tag).' contenteditable';
        }

        return $this->tag === '' ? 'element' : $this->tag;
    }
}
