<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Firefox\Bidi;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Vusys\Tetryon\Firefox\Bidi\BiDiConnection;
use Vusys\Tetryon\Firefox\Bidi\BiDiTrace;
use Vusys\Tetryon\Firefox\Bidi\BiDiTraceEntry;
use Vusys\Tetryon\Firefox\Exception\BiDiCommandException;
use Vusys\Tetryon\Tests\Support\Firefox\FakeTransport;

#[CoversClass(BiDiConnection::class)]
final class BiDiConnectionTest extends TestCase
{
    public function test_send_returns_the_result_and_encodes_empty_params_as_an_object(): void
    {
        $transport = new FakeTransport;
        $transport->queue(['id' => 1, 'type' => 'success', 'result' => ['ready' => true]]);

        $result = new BiDiConnection($transport)->send('session.status');

        self::assertSame(['ready' => true], $result);
        self::assertStringContainsString('"method":"session.status"', $transport->sent[0]);
        self::assertStringContainsString('"id":1', $transport->sent[0]);
        // The empty params must serialise as {} not [] — Firefox rejects arrays.
        self::assertStringContainsString('"params":{}', $transport->sent[0]);
    }

    public function test_it_throws_a_command_exception_on_an_error_response(): void
    {
        $transport = new FakeTransport;
        $transport->queue([
            'id' => 1,
            'type' => 'error',
            'error' => 'invalid argument',
            'message' => 'bad params',
        ]);

        try {
            new BiDiConnection($transport)->send('script.evaluate');
            self::fail('Expected a BiDiCommandException.');
        } catch (BiDiCommandException $e) {
            self::assertSame('script.evaluate', $e->method);
            self::assertSame('invalid argument', $e->errorCode);
        }
    }

    public function test_a_listener_can_answer_an_event_before_the_outer_command_returns(): void
    {
        // The shape a native dialog forces (#118): the event arrives while the
        // command that opened it is still waiting, the listener must issue a
        // command of its own to unblock it, and the outer response then arrives
        // *after* the inner one.
        $transport = new FakeTransport;
        $transport->queue(['method' => 'browsingContext.userPromptOpened', 'params' => ['type' => 'confirm']]);
        $transport->queue(['id' => 2, 'type' => 'success', 'result' => ['handled' => true]]);
        $transport->queue(['id' => 1, 'type' => 'success', 'result' => ['clicked' => true]]);

        $connection = new BiDiConnection($transport);
        $inner = [];
        $connection->listen(function (array $event) use ($connection, &$inner): void {
            if (($event['method'] ?? null) === 'browsingContext.userPromptOpened') {
                $inner[] = $connection->send('browsingContext.handleUserPrompt');
            }
        });

        $result = $connection->send('input.performActions');

        self::assertSame(['clicked' => true], $result);
        self::assertSame([['handled' => true]], $inner);
    }

    public function test_a_response_arriving_before_an_inner_command_completes_is_not_lost(): void
    {
        // Same nesting, but Firefox answers the outer command first. The held
        // response must be delivered when the outer wait resumes rather than
        // dropped, which would hang the session.
        $transport = new FakeTransport;
        $transport->queue(['method' => 'browsingContext.userPromptOpened', 'params' => []]);
        $transport->queue(['id' => 1, 'type' => 'success', 'result' => ['clicked' => true]]);
        $transport->queue(['id' => 2, 'type' => 'success', 'result' => ['handled' => true]]);

        $connection = new BiDiConnection($transport);
        $connection->listen(function (array $event) use ($connection): void {
            $connection->send('browsingContext.handleUserPrompt');
        });

        self::assertSame(['clicked' => true], $connection->send('input.performActions'));
    }

    public function test_it_buffers_events_seen_while_awaiting_a_response(): void
    {
        $transport = new FakeTransport;
        $transport->queue(['method' => 'log.entryAdded', 'params' => ['text' => 'hi']]);
        $transport->queue(['id' => 1, 'type' => 'success', 'result' => []]);
        $connection = new BiDiConnection($transport);

        $connection->send('browsingContext.navigate');
        $events = $connection->takeEvents();

        self::assertCount(1, $events);
        self::assertSame('log.entryAdded', $events[0]['method']);
    }

    public function test_subscribe_sends_the_event_list(): void
    {
        $transport = new FakeTransport;
        $transport->queue(['id' => 1, 'type' => 'success', 'result' => []]);

        new BiDiConnection($transport)->subscribe('log.entryAdded');

        self::assertStringContainsString('"method":"session.subscribe"', $transport->sent[0]);
        self::assertStringContainsString('"events":["log.entryAdded"]', $transport->sent[0]);
    }

    public function test_it_records_the_command_and_result_in_the_trace(): void
    {
        $transport = new FakeTransport;
        $transport->queue(['id' => 1, 'type' => 'success', 'result' => ['x' => 1]]);
        $trace = new BiDiTrace;

        new BiDiConnection($transport, trace: $trace)->send('session.new');

        $kinds = array_map(
            static fn (BiDiTraceEntry $entry): string => $entry->kind,
            $trace->entries(),
        );
        self::assertSame([BiDiTraceEntry::KIND_COMMAND, BiDiTraceEntry::KIND_RESULT], $kinds);
    }

    public function test_pump_events_drains_pushed_events(): void
    {
        $transport = new FakeTransport;
        $transport->queue(['method' => 'log.entryAdded', 'params' => ['text' => 'a']]);
        $transport->queue(['method' => 'log.entryAdded', 'params' => ['text' => 'b']]);
        $connection = new BiDiConnection($transport);

        $connection->pumpEvents(0.0);

        self::assertCount(2, $connection->takeEvents());
    }
}
