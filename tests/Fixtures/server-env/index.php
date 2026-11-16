<?php

declare(strict_types=1);

// Echoes the env a managed server was started with (ManagedServerTest).
header('Content-Type: text/plain');
echo getenv('TETRYON_PROBE') ?: 'unset';
