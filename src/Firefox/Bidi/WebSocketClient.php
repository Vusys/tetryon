<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

use Closure;
use Vusys\Tetryon\Firefox\Exception\BiDiException;

/**
 * A minimal blocking WebSocket client over a stream resource — exactly enough
 * of RFC 6455 to talk to Firefox's BiDi endpoint, with no external library.
 *
 * The constructor takes an already-connected stream so framing can be tested
 * over a `stream_socket_pair()`; {@see connect()} is the production entry point
 * that opens the socket and performs the opening handshake.
 */
final class WebSocketClient implements MessageTransport
{
    /** @var Closure(): string */
    private readonly Closure $maskProvider;

    /**
     * @param  resource  $stream
     * @param  (callable(): string)|null  $maskProvider  returns a 4-byte masking key
     */
    public function __construct(private $stream, ?callable $maskProvider = null)
    {
        $this->maskProvider = $maskProvider !== null
            ? Closure::fromCallable($maskProvider)
            : static fn (): string => random_bytes(4);
    }

    /**
     * @param  (callable(): string)|null  $maskProvider
     */
    public static function connect(string $url, float $timeout = 10.0, ?callable $maskProvider = null): self
    {
        $parts = parse_url($url);
        if (! is_array($parts) || ! isset($parts['host'], $parts['port'])) {
            throw new BiDiException("Invalid BiDi WebSocket URL: \"{$url}\".");
        }

        $host = $parts['host'];
        $port = $parts['port'];
        $path = $parts['path'] ?? '/';

        $errno = 0;
        $errstr = '';
        $stream = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if ($stream === false) {
            throw new BiDiException("Could not connect to Firefox BiDi at {$host}:{$port}: {$errstr} ({$errno}).");
        }

        stream_set_timeout($stream, max(1, (int) ceil($timeout)));

        $client = new self($stream, $maskProvider);
        $client->performHandshake($host, $port, $path);

        return $client;
    }

    public function sendText(string $payload): void
    {
        $this->writeAll(Frame::encodeMasked(Frame::OP_TEXT, $payload, $this->newMask()));
    }

    /**
     * Reassemble the next application message, transparently answering pings
     * and discarding pongs.
     */
    public function receiveMessage(): string
    {
        $data = '';
        while (true) {
            [$fin, $opcode, $payload] = $this->readFrame();

            if ($opcode === Frame::OP_PING) {
                $this->writeAll(Frame::encodeMasked(Frame::OP_PONG, $payload, $this->newMask()));

                continue;
            }
            if ($opcode === Frame::OP_PONG) {
                continue;
            }
            if ($opcode === Frame::OP_CLOSE) {
                throw new BiDiException('Firefox closed the BiDi WebSocket.');
            }

            $data .= $payload;
            if ($fin) {
                return $data;
            }
        }
    }

    /**
     * True if at least one byte is readable within the timeout — used to drain
     * pushed events without blocking.
     */
    public function poll(float $seconds): bool
    {
        $read = [$this->stream];
        $write = null;
        $except = null;
        $sec = (int) $seconds;
        $usec = (int) (($seconds - $sec) * 1_000_000);

        $ready = @stream_select($read, $write, $except, $sec, $usec);

        return $ready !== false && $ready > 0;
    }

    public function close(): void
    {
        if (! is_resource($this->stream)) {
            return;
        }

        @fwrite($this->stream, Frame::encodeMasked(Frame::OP_CLOSE, '', $this->newMask()));
        fclose($this->stream);
    }

    private function performHandshake(string $host, int $port, string $path): void
    {
        $key = Handshake::generateKey();
        $this->writeAll(Handshake::request($host, $port, $path, $key));
        Handshake::verify($this->readUntilHeadersEnd(), $key);
    }

    /**
     * @return array{0: bool, 1: int, 2: string} fin, opcode, payload
     */
    private function readFrame(): array
    {
        $first = ord($this->readExact(1));
        $fin = ($first & 0x80) !== 0;
        $opcode = $first & 0x0F;

        $second = ord($this->readExact(1));
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;

        if ($length === 126) {
            $length = $this->readUint('n', 2);
        } elseif ($length === 127) {
            $length = $this->readUint('J', 8);
        }

        if ($masked) {
            throw new BiDiException('Firefox sent a masked frame, which violates RFC 6455.');
        }

        return [$fin, $opcode, $length > 0 ? $this->readExact($length) : ''];
    }

    private function readUint(string $format, int $bytes): int
    {
        $unpacked = unpack($format, $this->readExact($bytes));
        if ($unpacked === false || ! isset($unpacked[1]) || ! is_int($unpacked[1])) {
            throw new BiDiException('Malformed WebSocket frame length.');
        }

        return $unpacked[1];
    }

    private function readExact(int $bytes): string
    {
        $buffer = '';
        while (strlen($buffer) < $bytes) {
            $chunk = fread($this->stream, max(1, $bytes - strlen($buffer)));
            if ($chunk === false || $chunk === '') {
                $this->failOnDeadStream('reading from');

                continue;
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    private function readUntilHeadersEnd(): string
    {
        $buffer = '';
        while (! str_contains($buffer, "\r\n\r\n")) {
            $chunk = fread($this->stream, 1);
            if ($chunk === false || $chunk === '') {
                $this->failOnDeadStream('handshaking with');

                continue;
            }
            $buffer .= $chunk;
            if (strlen($buffer) > 16_384) {
                throw new BiDiException('WebSocket handshake response exceeded 16 KiB.');
            }
        }

        return $buffer;
    }

    private function writeAll(string $bytes): void
    {
        $total = strlen($bytes);
        $written = 0;
        while ($written < $total) {
            $count = fwrite($this->stream, substr($bytes, $written));
            if ($count === false || $count === 0) {
                throw new BiDiException('Failed to write to the Firefox BiDi socket.');
            }
            $written += $count;
        }
    }

    private function failOnDeadStream(string $action): void
    {
        $meta = stream_get_meta_data($this->stream);
        if ($meta['timed_out']) {
            throw new BiDiException("Timed out {$action} the Firefox BiDi socket.");
        }
        if (feof($this->stream)) {
            throw new BiDiException("Firefox BiDi socket closed while {$action} it.");
        }

        usleep(1_000);
    }

    private function newMask(): string
    {
        $mask = ($this->maskProvider)();
        if (strlen($mask) !== 4) {
            throw new BiDiException('Mask provider must return exactly 4 bytes.');
        }

        return $mask;
    }
}
