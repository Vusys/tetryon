<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Dialog;

/**
 * What a test has arranged to happen to the *next* dialog: accept or dismiss
 * it, optionally with text for a `window.prompt`, and optionally asserting the
 * wording — the message of a destructive confirmation is often the thing worth
 * testing.
 *
 * One-shot by design. A dialog blocks the page until it is answered, so the
 * answer has to be arranged before the action that opens it; leaving a standing
 * policy in place instead would silently swallow the *second*, unexpected
 * dialog, which is the case this whole feature exists to make loud.
 */
final readonly class DialogExpectation
{
    public function __construct(
        public bool $accept,
        public ?string $text = null,
        public ?string $expectedMessage = null,
    ) {}

    /**
     * The complaint to raise when the dialog that arrived isn't the one the test
     * said it was expecting, or null when it matches (or nothing was asserted).
     */
    public function mismatch(Dialog $dialog): ?string
    {
        if ($this->expectedMessage === null || str_contains($dialog->message, $this->expectedMessage)) {
            return null;
        }

        return sprintf(
            'Expected a dialog saying "%s", but the %s said "%s".',
            $this->expectedMessage,
            $dialog->type->value,
            $dialog->message,
        );
    }
}
