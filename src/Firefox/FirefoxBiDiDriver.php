<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Throwable;
use Vusys\Tetryon\Firefox\Bidi\BiDiConnection;
use Vusys\Tetryon\Firefox\Bidi\BiDiTrace;
use Vusys\Tetryon\Firefox\Bidi\RemoteValue;
use Vusys\Tetryon\Firefox\Bidi\WebSocketClient;
use Vusys\Tetryon\Firefox\Exception\BiDiException;
use Vusys\Tetryon\Firefox\Exception\FirefoxException;

/**
 * The v1 Firefox driver — proudly Firefox-specific. Launches the browser,
 * establishes a BiDi session, and exposes the handful of primitives the higher
 * layers build on: navigate, evaluate JS, screenshot, and collect console
 * output. Diagnostics (command trace, browser stderr) are first-class.
 */
final class FirefoxBiDiDriver
{
    private ?FirefoxProcess $process = null;

    private ?WebSocketClient $socket = null;

    private ?BiDiConnection $bidi = null;

    private ?string $context = null;

    /** @var list<ConsoleMessage> */
    private array $console = [];

    public function __construct(
        private readonly LaunchOptions $options = new LaunchOptions,
        private readonly LoggerInterface $logger = new NullLogger,
        private readonly BiDiTrace $trace = new BiDiTrace,
    ) {}

    public function start(): void
    {
        if ($this->process instanceof FirefoxProcess) {
            throw new FirefoxException('Driver already started.');
        }

        $this->process = FirefoxProcess::launch($this->options, $this->logger);
        $this->socket = WebSocketClient::connect(
            $this->process->bidiUrl.'/session',
            $this->options->connectTimeout,
        );
        $this->bidi = new BiDiConnection($this->socket, $this->logger, $this->trace);

        $this->bidi->send('session.new', ['capabilities' => ['alwaysMatch' => new stdClass]]);
        $this->bidi->subscribe('log.entryAdded');
        $this->context = $this->resolveFirstContext();
    }

    public function navigate(string $url): void
    {
        $this->connection()->send('browsingContext.navigate', [
            'context' => $this->context(),
            'url' => $url,
            'wait' => 'complete',
        ]);
        $this->collectConsole();
    }

    public function evaluateScript(string $expression): mixed
    {
        $result = $this->connection()->send('script.evaluate', [
            'expression' => $expression,
            'target' => ['context' => $this->context()],
            'awaitPromise' => true,
        ]);

        if (($result['type'] ?? null) === 'exception') {
            throw new BiDiException('Script evaluation threw: '.$this->exceptionText($result));
        }

        return RemoteValue::toPhp($result['result'] ?? null);
    }

    public function currentUrl(): string
    {
        $url = $this->evaluateScript('window.location.href');

        return is_string($url) ? $url : '';
    }

    public function title(): string
    {
        $title = $this->evaluateScript('document.title');

        return is_string($title) ? $title : '';
    }

    /**
     * @return string raw PNG bytes
     */
    public function screenshot(): string
    {
        $result = $this->connection()->send('browsingContext.captureScreenshot', [
            'context' => $this->context(),
        ]);

        $data = $result['data'] ?? null;
        if (! is_string($data)) {
            throw new BiDiException('Screenshot response contained no base64 data.');
        }

        $png = base64_decode($data, true);
        if ($png === false) {
            throw new BiDiException('Screenshot data was not valid base64.');
        }

        return $png;
    }

    /**
     * @return list<ConsoleMessage>
     */
    public function consoleMessages(): array
    {
        $this->collectConsole();

        return $this->console;
    }

    public function trace(): BiDiTrace
    {
        return $this->trace;
    }

    public function browserStderr(): string
    {
        return $this->process?->stderr() ?? '';
    }

    public function stop(): void
    {
        try {
            $this->socket?->close();
        } catch (Throwable) {
            // Teardown is best-effort; a dead socket must not mask the real failure.
        } finally {
            $this->process?->stop();
            $this->process = null;
            $this->socket = null;
            $this->bidi = null;
            $this->context = null;
        }
    }

    private function collectConsole(): void
    {
        $connection = $this->connection();
        $connection->pumpEvents(0.05);

        foreach ($connection->takeEvents() as $event) {
            if (($event['method'] ?? null) !== 'log.entryAdded') {
                continue;
            }
            $this->console[] = ConsoleMessage::fromLogEntry($event['params'] ?? null);
        }
    }

    private function resolveFirstContext(): string
    {
        $tree = $this->connection()->send('browsingContext.getTree');
        $contexts = $tree['contexts'] ?? null;

        if (is_array($contexts)
            && isset($contexts[0])
            && is_array($contexts[0])
            && is_string($contexts[0]['context'] ?? null)
        ) {
            return $contexts[0]['context'];
        }

        throw new BiDiException('Firefox returned no browsing context.');
    }

    /**
     * @param  array<array-key, mixed>  $result
     */
    private function exceptionText(array $result): string
    {
        $details = $result['exceptionDetails'] ?? null;
        if (is_array($details) && is_string($details['text'] ?? null)) {
            return $details['text'];
        }

        return 'unknown script exception';
    }

    private function connection(): BiDiConnection
    {
        if (! $this->bidi instanceof BiDiConnection) {
            throw new FirefoxException('Driver not started — call start() first.');
        }

        return $this->bidi;
    }

    private function context(): string
    {
        if ($this->context === null) {
            throw new FirefoxException('No browsing context — call start() first.');
        }

        return $this->context;
    }
}
