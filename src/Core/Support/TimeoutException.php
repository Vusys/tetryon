<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Core\Support;

use RuntimeException;

/**
 * An explicit wait (`waitForText`, `waitForUrl`, …) did not see its condition
 * become true before the timeout.
 */
final class TimeoutException extends RuntimeException {}
