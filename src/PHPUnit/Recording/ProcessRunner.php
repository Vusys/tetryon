<?php

declare(strict_types=1);

namespace Vusys\Tetryon\PHPUnit\Recording;

use Vusys\Tetryon\PHPUnit\Recording\Exception\RecordingException;

/**
 * Runs `magick`/`ffmpeg` invocations via `proc_open` with an argv array —
 * never a shell string — so a caption or field value containing shell
 * metacharacters can never break out of the command.
 */
final class ProcessRunner
{
    /**
     * @param  list<string>  $command
     */
    public static function run(array $command, ?string $workingDirectory = null): string
    {
        $descriptors = [1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes, $workingDirectory);
        if (! is_resource($process)) {
            throw new RecordingException('Failed to start process: '.implode(' ', $command));
        }

        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0) {
            $detail = trim($stderr !== false && $stderr !== '' ? $stderr : (string) $stdout);

            throw new RecordingException(sprintf(
                '"%s" exited with code %d: %s',
                $command[0] ?? '(unknown)',
                $exitCode,
                $detail === '' ? '(no output)' : $detail,
            ));
        }

        return $stdout === false ? '' : $stdout;
    }
}
