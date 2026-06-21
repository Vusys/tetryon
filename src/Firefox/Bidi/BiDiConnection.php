<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox\Bidi;

use JsonException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Vusys\Tetryon\Firefox\Exception\BiDiCommandException;
use Vusys\Tetryon\Firefox\Exception\BiDiException;

/**
 * Speaks WebDriver BiDi over a {@see WebSocketClient}: assigns command ids,
 * correlates responses, buffers pushed events, and records everything to a
 * {@see BiDiTrace} and a PSR-3 logger. Commands are issued serially.
 */
final class BiDiConnection
{
    private int $nextId = 1;

    /** @var list<array<string, mixed>> */
    private array $events = [];

    public function __construct(
        private readonly MessageTransport $socket,
        private readonly LoggerInterface $logger = new NullLogger,
        private readonly BiDiTrace $trace = new BiDiTrace,
    ) {}

    public function trace(): BiDiTrace
    {
        return $this->trace;
    }

    /**
     * Send a command and block until its matching response arrives, buffering
     * any events seen in the meantime.
     *
     * @param  array<string, mixed>  $params
     * @return array<array-key, mixed> the command result object
     */
    public function send(string $method, array $params = []): array
    {
        $id = $this->nextId++;
        $this->logger->debug('BiDi -> {method} #{id}', ['method' => $method, 'id' => $id, 'params' => $params]);
        $this->trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_COMMAND, $method, $id, $this->summarise($params)));

        $this->socket->sendText($this->encode($id, $method, $params));

        while (true) {
            $message = $this->readDecoded();
            $messageId = $message['id'] ?? null;

            if ($messageId === $id) {
                return $this->resultOrThrow($method, $id, $message);
            }

            if (is_int($messageId)) {
                $this->logger->warning('BiDi unexpected response for #{id}', ['id' => $messageId]);

                continue;
            }

            $this->bufferEvent($message);
        }
    }

    public function subscribe(string ...$events): void
    {
        $this->send('session.subscribe', ['events' => array_values($events)]);
    }

    /**
     * Drain events Firefox has pushed, without blocking past the timeout.
     */
    public function pumpEvents(float $timeout): void
    {
        while ($this->socket->poll($timeout)) {
            $message = $this->readDecoded();
            if (is_int($message['id'] ?? null)) {
                continue;
            }
            $this->bufferEvent($message);
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function takeEvents(): array
    {
        $events = $this->events;
        $this->events = [];

        return $events;
    }

    public function close(): void
    {
        $this->socket->close();
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function encode(int $id, string $method, array $params): string
    {
        try {
            return json_encode(
                ['id' => $id, 'method' => $method, 'params' => (object) $params],
                JSON_THROW_ON_ERROR,
            );
        } catch (JsonException $e) {
            throw new BiDiException("Could not encode BiDi command \"{$method}\": {$e->getMessage()}", 0, $e);
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private function readDecoded(): array
    {
        $raw = $this->socket->receiveMessage();

        try {
            $decoded = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new BiDiException("Could not decode BiDi message: {$e->getMessage()}", 0, $e);
        }

        if (! is_array($decoded)) {
            throw new BiDiException('A BiDi message was not a JSON object.');
        }

        return $decoded;
    }

    /**
     * @param  array<array-key, mixed>  $message
     * @return array<array-key, mixed>
     */
    private function resultOrThrow(string $method, int $id, array $message): array
    {
        if (($message['type'] ?? null) === 'error') {
            $code = is_string($message['error'] ?? null) ? $message['error'] : 'unknown error';
            $detail = is_string($message['message'] ?? null) ? $message['message'] : '';
            $this->logger->error('BiDi x {method} #{id}: {code}', ['method' => $method, 'id' => $id, 'code' => $code]);
            $this->trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_ERROR, $method, $id, trim("{$code} {$detail}")));

            throw new BiDiCommandException($method, $code, $detail);
        }

        $result = $message['result'] ?? null;
        if (! is_array($result)) {
            throw new BiDiException("The BiDi result for \"{$method}\" was not an object.");
        }

        $this->logger->debug('BiDi <- {method} #{id}', ['method' => $method, 'id' => $id]);
        $this->trace->record(new BiDiTraceEntry(BiDiTraceEntry::KIND_RESULT, $method, $id, $this->summarise($result)));

        return $result;
    }

    /**
     * @param  array<array-key, mixed>  $message
     */
    private function bufferEvent(array $message): void
    {
        $method = is_string($message['method'] ?? null) ? $message['method'] : '(event)';
        $this->logger->debug('BiDi ~ {method}', ['method' => $method]);
        $this->trace->record(new BiDiTraceEntry(
            BiDiTraceEntry::KIND_EVENT,
            $method,
            null,
            $this->summarise($message['params'] ?? null),
        ));

        $this->events[] = $message;
    }

    private function summarise(mixed $data): string
    {
        $json = json_encode($data);
        if ($json === false) {
            return '';
        }

        return strlen($json) > 200 ? substr($json, 0, 197).'...' : $json;
    }
}
