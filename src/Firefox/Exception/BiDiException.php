<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Exception;

/**
 * Transport- or protocol-level failure talking WebDriver BiDi to Firefox
 * (handshake failure, malformed frame, connection drop, decode error).
 *
 * Error *responses* to a command surface as {@see BiDiCommandException}.
 */
class BiDiException extends FirefoxException {}
