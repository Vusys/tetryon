<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Dialog;

use RuntimeException;

/**
 * A native dialog opened that the test had not arranged an answer for, or the
 * dialog that opened was not the one the test said it was expecting.
 *
 * A dialog blocks the page, so without this the session simply stops responding
 * and every later command in the test times out with nothing to point at. The
 * dialog is dismissed first and the failure raised immediately after, so a hang
 * with no message becomes a one-line diagnosis.
 */
final class UnhandledDialogException extends RuntimeException
{
    public static function unexpected(Dialog $dialog): self
    {
        return new self(sprintf(
            'An unhandled dialog appeared (%s). It was dismissed so the session could continue.'
            .' Arrange an answer before the action that opens it: acceptDialog(), dismissDialog(),'
            .' or typeInDialog("...").',
            $dialog->describe(),
        ));
    }

    public static function mismatched(string $complaint): self
    {
        return new self($complaint.' It was answered anyway so the session could continue.');
    }
}
