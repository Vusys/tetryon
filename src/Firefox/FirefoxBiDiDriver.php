<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use stdClass;
use Throwable;
use Vusys\Tetryon\Core\Selector\ElementReference;
use Vusys\Tetryon\Core\Selector\Locator;
use Vusys\Tetryon\Core\Selector\NodeLocator;
use Vusys\Tetryon\Firefox\Bidi\BiDiConnection;
use Vusys\Tetryon\Firefox\Bidi\BiDiTrace;
use Vusys\Tetryon\Firefox\Bidi\InputActions;
use Vusys\Tetryon\Firefox\Bidi\Keys;
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
final class FirefoxBiDiDriver implements NodeLocator
{
    private ?FirefoxProcess $process = null;

    private ?WebSocketClient $socket = null;

    private ?BiDiConnection $bidi = null;

    private ?string $context = null;

    /** @var list<ConsoleMessage> */
    private array $console = [];

    /** @var array<string, NetworkRecord> keyed by BiDi request id */
    private array $network = [];

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
        $this->bidi->subscribe('log.entryAdded', 'network.beforeRequestSent', 'network.responseCompleted');
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

    public function reload(): void
    {
        $this->connection()->send('browsingContext.reload', [
            'context' => $this->context(),
            'wait' => 'complete',
        ]);
        $this->collectConsole();
    }

    public function traverseHistory(int $delta): void
    {
        $this->connection()->send('browsingContext.traverseHistory', [
            'context' => $this->context(),
            'delta' => $delta,
        ]);
        $this->awaitDocumentReady();
        $this->collectConsole();
    }

    /**
     * @return list<ElementReference>
     */
    public function locateAll(Locator $locator, ?ElementReference $within = null): array
    {
        $params = [
            'context' => $this->context(),
            'locator' => $locator->bidi,
        ];
        if ($within instanceof ElementReference) {
            $params['startNodes'] = [['sharedId' => $within->sharedId]];
        }

        $result = $this->connection()->send('browsingContext.locateNodes', $params);

        $nodes = $result['nodes'] ?? null;
        if (! is_array($nodes)) {
            return [];
        }

        $references = [];
        foreach ($nodes as $node) {
            if (! is_array($node)) {
                continue;
            }
            if (! is_string($node['sharedId'] ?? null)) {
                continue;
            }
            $value = $node['value'] ?? null;
            $localName = is_array($value) && is_string($value['localName'] ?? null) ? $value['localName'] : null;
            $references[] = new ElementReference($node['sharedId'], $localName);
        }

        return $references;
    }

    public function locate(string $css): ElementReference
    {
        return $this->locateAll(Locator::css('css', $css))[0]
            ?? throw new BiDiException("No element matched the CSS selector \"{$css}\".");
    }

    public function clickElement(ElementReference $element): void
    {
        $this->performActions(InputActions::clickElement($element->sharedId));
    }

    public function doubleClickElement(ElementReference $element): void
    {
        $this->performActions(InputActions::doubleClickElement($element->sharedId));
    }

    public function rightClickElement(ElementReference $element): void
    {
        $this->performActions(InputActions::contextClickElement($element->sharedId));
    }

    public function hoverElement(ElementReference $element): void
    {
        $this->performActions(InputActions::hoverElement($element->sharedId));
    }

    public function typeInto(ElementReference $element, string $text): void
    {
        $this->clickElement($element); // focus the field first
        $this->performActions(InputActions::typeText($text));
    }

    /**
     * Press on the source element's centre, drag to the target element's centre
     * over $steps intermediate moves, and release.
     */
    public function dragElement(ElementReference $source, ElementReference $target, int $steps = 10): void
    {
        [$sx, $sy] = $this->elementCentre($source);
        [$tx, $ty] = $this->elementCentre($target);
        $this->performActions(InputActions::pointerDrag($this->dragPath($sx, $sy, $tx, $ty, $steps)));
    }

    /**
     * Drag the source element to absolute viewport coordinates.
     */
    public function dragElementTo(ElementReference $source, int $x, int $y, int $steps = 10): void
    {
        [$sx, $sy] = $this->elementCentre($source);
        $this->performActions(InputActions::pointerDrag($this->dragPath($sx, $sy, $x, $y, $steps)));
    }

    /**
     * Drag the source element by a pixel offset from its centre.
     */
    public function dragElementBy(ElementReference $source, int $dx, int $dy, int $steps = 10): void
    {
        [$sx, $sy] = $this->elementCentre($source);
        $this->performActions(InputActions::pointerDrag($this->dragPath($sx, $sy, $sx + $dx, $sy + $dy, $steps)));
    }

    public function pressKeys(string ...$keys): void
    {
        $values = array_map(Keys::resolve(...), $keys);
        $this->performActions(InputActions::pressKeys(array_values($values)));
    }

    public function setFiles(ElementReference $element, string ...$paths): void
    {
        $this->connection()->send('input.setFiles', [
            'context' => $this->context(),
            'element' => ['sharedId' => $element->sharedId],
            'files' => array_values($paths),
        ]);
        $this->collectConsole();
    }

    public function click(string $css): void
    {
        $this->clickElement($this->locate($css));
    }

    public function type(string $css, string $text): void
    {
        $this->typeInto($this->locate($css), $text);
    }

    /**
     * @param  array{type: string, id: string, actions: list<array<string, mixed>>}  $source
     */
    private function performActions(array $source): void
    {
        $this->connection()->send('input.performActions', [
            'context' => $this->context(),
            'actions' => [$source],
        ]);
        $this->collectConsole();
    }

    /**
     * Set a cookie via BiDi storage, partitioned by the source origin so it can
     * be seeded before the first navigation and carried by that request. Handles
     * HttpOnly and the Secure/SameSite/expiry attributes natively.
     *
     * @param  array{path?: string, secure?: bool, httpOnly?: bool, sameSite?: string, expiry?: int}  $options
     */
    public function setCookie(string $name, string $value, string $domain, string $sourceOrigin, array $options = []): void
    {
        $cookie = [
            'name' => $name,
            'value' => ['type' => 'string', 'value' => $value],
            'domain' => $domain,
            'path' => $options['path'] ?? '/',
        ];
        if (isset($options['secure'])) {
            $cookie['secure'] = $options['secure'];
        }
        if (isset($options['httpOnly'])) {
            $cookie['httpOnly'] = $options['httpOnly'];
        }
        if (isset($options['sameSite'])) {
            $cookie['sameSite'] = strtolower($options['sameSite']);
        }
        if (isset($options['expiry'])) {
            $cookie['expiry'] = $options['expiry'];
        }

        $this->connection()->send('storage.setCookie', [
            'cookie' => $cookie,
            'partition' => $this->cookiePartition($sourceOrigin),
        ]);
    }

    public function getCookie(string $name, string $sourceOrigin): ?string
    {
        $result = $this->connection()->send('storage.getCookies', [
            'filter' => ['name' => $name],
            'partition' => $this->cookiePartition($sourceOrigin),
        ]);

        $cookies = $result['cookies'] ?? null;
        if (! is_array($cookies)) {
            return null;
        }

        foreach ($cookies as $cookie) {
            if (! is_array($cookie)) {
                continue;
            }
            $value = $cookie['value'] ?? null;
            if (is_array($value) && is_string($value['value'] ?? null)) {
                return ($value['type'] ?? null) === 'base64'
                    ? (base64_decode($value['value'], true) ?: '')
                    : $value['value'];
            }
        }

        return null;
    }

    public function deleteCookie(string $name, string $sourceOrigin): void
    {
        $this->connection()->send('storage.deleteCookies', [
            'filter' => ['name' => $name],
            'partition' => $this->cookiePartition($sourceOrigin),
        ]);
    }

    public function clearCookies(string $sourceOrigin): void
    {
        $this->connection()->send('storage.deleteCookies', [
            'partition' => $this->cookiePartition($sourceOrigin),
        ]);
    }

    /**
     * @return array{type: string, sourceOrigin: string}
     */
    private function cookiePartition(string $sourceOrigin): array
    {
        return ['type' => 'storageKey', 'sourceOrigin' => $sourceOrigin];
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

    /**
     * Call a JS function with the element bound as `this`, returning its value.
     *
     * @param  string  ...$arguments  string arguments forwarded to the function
     */
    public function callFunctionOn(ElementReference $element, string $functionDeclaration, string ...$arguments): mixed
    {
        $localValues = array_map(
            static fn (string $argument): array => ['type' => 'string', 'value' => $argument],
            $arguments,
        );

        $result = $this->connection()->send('script.callFunction', [
            'functionDeclaration' => $functionDeclaration,
            'this' => ['sharedId' => $element->sharedId],
            'arguments' => array_values($localValues),
            'target' => ['context' => $this->context()],
            'awaitPromise' => true,
        ]);

        if (($result['type'] ?? null) === 'exception') {
            throw new BiDiException('Element function threw: '.$this->exceptionText($result));
        }

        $this->collectConsole();

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

    /**
     * The network exchanges observed so far (drains any pending events first).
     *
     * @return list<NetworkRecord>
     */
    public function networkLog(): array
    {
        $this->collectConsole();

        return array_values($this->network);
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
            $this->network = [];
        }
    }

    /**
     * @return array{0: int, 1: int} the element's viewport-centre x, y
     */
    private function elementCentre(ElementReference $element): array
    {
        $json = $this->callFunctionOn(
            $element,
            'function(){ const r = this.getBoundingClientRect();'
            .' return JSON.stringify({ x: Math.round(r.left + r.width / 2), y: Math.round(r.top + r.height / 2) }); }',
        );

        $point = is_string($json) ? json_decode($json, true) : null;
        $x = is_array($point) && is_int($point['x'] ?? null) ? $point['x'] : 0;
        $y = is_array($point) && is_int($point['y'] ?? null) ? $point['y'] : 0;

        return [$x, $y];
    }

    /**
     * A straight path of $steps interpolated points from a start to an end point,
     * inclusive of both — the intermediate moves a pointer-drag needs.
     *
     * @return list<array{x: int, y: int}>
     */
    private function dragPath(int $sx, int $sy, int $ex, int $ey, int $steps): array
    {
        $steps = max(1, $steps);
        $path = [['x' => $sx, 'y' => $sy]];

        for ($i = 1; $i <= $steps; $i++) {
            $path[] = [
                'x' => (int) round($sx + ($ex - $sx) * $i / $steps),
                'y' => (int) round($sy + ($ey - $sy) * $i / $steps),
            ];
        }

        return $path;
    }

    private function awaitDocumentReady(): void
    {
        // browsingContext.traverseHistory does not wait for the load, so settle
        // briefly on readyState. A full actionable-wait arrives with auto-wait.
        $deadline = microtime(true) + 3.0;
        while (microtime(true) < $deadline) {
            if ($this->evaluateScript('document.readyState') === 'complete') {
                return;
            }
            usleep(20_000);
        }
    }

    private function collectConsole(): void
    {
        $connection = $this->connection();
        $connection->pumpEvents(0.05);

        foreach ($connection->takeEvents() as $event) {
            match ($event['method'] ?? null) {
                'log.entryAdded' => $this->console[] = ConsoleMessage::fromLogEntry($event['params'] ?? null),
                'network.beforeRequestSent', 'network.responseCompleted' => $this->recordNetwork($event),
                default => null,
            };
        }
    }

    /**
     * @param  array<array-key, mixed>  $event
     */
    private function recordNetwork(array $event): void
    {
        $params = $event['params'] ?? null;
        if (! is_array($params)) {
            return;
        }

        $request = $params['request'] ?? null;
        if (! is_array($request)) {
            return;
        }

        $id = is_string($request['request'] ?? null) ? $request['request'] : null;
        $url = is_string($request['url'] ?? null) ? $request['url'] : null;
        if ($id === null || $url === null) {
            return;
        }

        $response = $params['response'] ?? null;
        $status = is_array($response) && is_int($response['status'] ?? null) ? $response['status'] : null;

        $existing = $this->network[$id] ?? null;
        $this->network[$id] = $existing instanceof NetworkRecord
            ? $existing->withStatus($status)
            : new NetworkRecord(is_string($request['method'] ?? null) ? $request['method'] : '', $url, $status);
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
