<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Firefox;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;
use Vusys\Tetryon\Core\Dialog\Dialog;
use Vusys\Tetryon\Core\Dialog\DialogExpectation;
use Vusys\Tetryon\Core\Dialog\DialogType;
use Vusys\Tetryon\Core\Dialog\UnhandledDialogException;
use Vusys\Tetryon\Core\Selector\ElementReference;
use Vusys\Tetryon\Core\Selector\Locator;
use Vusys\Tetryon\Core\Selector\NodeLocator;
use Vusys\Tetryon\Core\Selector\Visibility;
use Vusys\Tetryon\Core\Selector\VisibilityProbe;
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
final class FirefoxBiDiDriver implements NodeLocator, VisibilityProbe
{
    /**
     * Probe for {@see self::visibility()}: `clickable` when the element is rendered,
     * on-screen and top-most at its own centre; `rendered` when it has a real box
     * but is off-screen or covered; `hidden` when it is not rendered at all.
     * Deliberately does NOT scroll — resolution only ranks candidates; scrolling
     * is the actionability check's job, which is exactly why `rendered` is not
     * folded into `hidden`: an off-screen match is one scroll away from being
     * clicked, an unrendered one never will be. Used solely to break ties between
     * several matches, and only when there is more than one, so a single
     * legitimately off-screen target is never reached by this check.
     */
    private const string VISIBILITY_JS = <<<'JS'
        function () {
          const s = getComputedStyle(this);
          if (s.display === 'none' || s.visibility === 'hidden') return 'hidden';
          const r = this.getBoundingClientRect();
          if (!(r.width || r.height)) return 'hidden';
          const x = r.left + r.width / 2, y = r.top + r.height / 2;
          const vw = window.innerWidth || document.documentElement.clientWidth;
          const vh = window.innerHeight || document.documentElement.clientHeight;
          if (x < 0 || y < 0 || x > vw || y > vh) return 'rendered';
          const hit = document.elementFromPoint(x, y);
          if (!hit) return 'rendered';
          return (hit === this || this.contains(hit) || hit.contains(this)) ? 'clickable' : 'rendered';
        }
        JS;

    /**
     * The body of {@see locatePiercing()}: apply a `match(root)` matcher inside
     * the start node and every open shadow root beneath it, de-duped, and return
     * the elements as node remote values so the caller gets shared references
     * (#151, #162). `this` is the `within()` scope when set, else the document.
     */
    private const string PIERCE_WALK_JS = <<<'JS'
        const start = (this && this.querySelectorAll) ? this : document;
        const out = [];
        const seen = new Set();
        const visit = function (node) {
          (match(node) || []).forEach(function (el) {
            if (!seen.has(el)) { seen.add(el); out.push(el); }
          });
          node.querySelectorAll('*').forEach(function (el) {
            if (el.shadowRoot) visit(el.shadowRoot);
          });
        };
        visit(start);
        if (start.shadowRoot) visit(start.shadowRoot);
        return out;
        JS;

    private ?FirefoxProcess $process = null;

    private ?WebSocketClient $socket = null;

    private ?BiDiConnection $bidi = null;

    private ?string $context = null;

    /** @var list<ConsoleMessage> */
    private array $console = [];

    /** @var array<string, NetworkRecord> keyed by BiDi request id */
    private array $network = [];

    private ?DialogExpectation $expectation = null;

    private ?Dialog $lastDialog = null;

    /** @var list<string> complaints about dialogs nobody arranged an answer for */
    private array $dialogComplaints = [];

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

        $this->bidi->send('session.new', ['capabilities' => ['alwaysMatch' => [
            // Firefox's default is to dismiss a dialog behind the test's back,
            // which silently sends every journey down the "cancel" branch. Take
            // ownership instead: the dialogs the test arranged an answer for get
            // it, and the ones it didn't are dismissed *and reported*. Leaving
            // beforeunload to the browser keeps navigation from wedging on a
            // guard no test asked about.
            'unhandledPromptBehavior' => ['default' => 'ignore', 'beforeUnload' => 'accept'],
        ]]]);
        $this->bidi->subscribe(
            'log.entryAdded',
            'network.beforeRequestSent',
            'network.responseCompleted',
            'browsingContext.userPromptOpened',
        );
        $this->bidi->listen($this->onEvent(...));
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
        $this->reportDialogs();
    }

    public function reload(): void
    {
        $this->connection()->send('browsingContext.reload', [
            'context' => $this->context(),
            'wait' => 'complete',
        ]);
        $this->collectConsole();
        $this->reportDialogs();
    }

    public function traverseHistory(int $delta): void
    {
        $this->connection()->send('browsingContext.traverseHistory', [
            'context' => $this->context(),
            'delta' => $delta,
        ]);
        $this->awaitDocumentReady();
        $this->collectConsole();
        $this->reportDialogs();
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

        // browsingContext.locateNodes only sees the light DOM, so an app that
        // renders into shadow roots (web components) is invisible to it. When the
        // native locate finds nothing, retry with a matcher run inside every open
        // shadow root: CSS via querySelectorAll (#151), and the text/label
        // strategies via the JS twin the locator carries (#162). Accessibility
        // locators have no piercing matcher, so they stay on the native path.
        if ($references === []) {
            $matcher = $this->piercingMatcher($locator);
            if ($matcher !== null) {
                return $this->locatePiercing($matcher, $within);
            }
        }

        return $references;
    }

    /**
     * The `(root) => Element[]` matcher to run across shadow roots for a locator:
     * the locator's own `pierce` twin (text/label strategies), or a
     * querySelectorAll for a CSS locator. Null when the locator can't pierce.
     */
    private function piercingMatcher(Locator $locator): ?string
    {
        if ($locator->pierce !== null) {
            return $locator->pierce;
        }

        if ($locator->bidi['type'] === 'css' && is_string($locator->bidi['value'])) {
            return '(root)=>Array.from(root.querySelectorAll('.json_encode($locator->bidi['value'], JSON_THROW_ON_ERROR).'))';
        }

        return null;
    }

    /**
     * Run a `(root) => Element[]` matcher inside every open shadow root (and the
     * light DOM), returning shared references the input/script commands can drive.
     * The shadow-piercing fallback for {@see locateAll()} (#151, #162).
     *
     * @return list<ElementReference>
     */
    private function locatePiercing(string $matcher, ?ElementReference $within): array
    {
        $params = [
            'functionDeclaration' => 'function () { const match = ('.$matcher.'); '.self::PIERCE_WALK_JS.' }',
            'target' => ['context' => $this->context()],
            'awaitPromise' => false,
        ];
        if ($within instanceof ElementReference) {
            $params['this'] = ['sharedId' => $within->sharedId];
        }

        $result = $this->connection()->send('script.callFunction', $params);
        if (($result['type'] ?? null) === 'exception') {
            throw new BiDiException('Shadow-piercing locate threw: '.$this->exceptionText($result));
        }

        $remote = $result['result'] ?? null;
        if (! is_array($remote) || ($remote['type'] ?? null) !== 'array' || ! is_array($remote['value'] ?? null)) {
            return [];
        }

        $references = [];
        foreach ($remote['value'] as $item) {
            if (! is_array($item)) {
                continue;
            }
            if (($item['type'] ?? null) !== 'node') {
                continue;
            }
            if (! is_string($item['sharedId'] ?? null)) {
                continue;
            }
            $value = $item['value'] ?? null;
            $localName = is_array($value) && is_string($value['localName'] ?? null) ? $value['localName'] : null;
            $references[] = new ElementReference($item['sharedId'], $localName);
        }

        return $references;
    }

    public function visibility(ElementReference $element): Visibility
    {
        return Visibility::fromProbe($this->callFunctionOn($element, self::VISIBILITY_JS));
    }

    // ── Native dialogs ──────────────────────────────────────────────────────

    /**
     * Arrange how the next dialog is answered. Must be set *before* the action
     * that opens it: a dialog blocks the page, so the command that triggered it
     * does not return until the dialog is gone.
     */
    public function expectDialog(DialogExpectation $expectation): void
    {
        $this->expectation = $expectation;
    }

    /**
     * The last dialog that appeared, whether it was expected or not — so a test
     * can assert on the wording after the fact.
     */
    public function lastDialog(): ?Dialog
    {
        return $this->lastDialog;
    }

    /**
     * Raise (once) anything that went wrong with a dialog since the last check.
     * Called at the end of every command that can trigger one, so the failure
     * lands on the action that caused it rather than on an unrelated timeout
     * later in the test.
     */
    private function reportDialogs(): void
    {
        $complaint = array_shift($this->dialogComplaints);
        if ($complaint === null) {
            return;
        }

        $this->dialogComplaints = [];

        throw new UnhandledDialogException($complaint);
    }

    /**
     * Answer a dialog the moment it opens, from inside the wait of whichever
     * command opened it. Never throws — the complaint is recorded and raised by
     * {@see reportDialogs()} once that command has completed.
     *
     * @param  array<string, mixed>  $event
     */
    private function onEvent(array $event): void
    {
        if (($event['method'] ?? null) !== 'browsingContext.userPromptOpened') {
            return;
        }

        $params = $event['params'] ?? null;
        $dialog = new Dialog(
            DialogType::fromDriver(is_array($params) ? $params['type'] ?? null : null),
            is_array($params) && is_string($params['message'] ?? null) ? $params['message'] : '',
            is_array($params) && is_string($params['defaultValue'] ?? null) ? $params['defaultValue'] : '',
        );
        $this->lastDialog = $dialog;

        $expectation = $this->expectation;
        $this->expectation = null;

        if (! $expectation instanceof DialogExpectation) {
            $this->handleUserPrompt(accept: false);
            $this->dialogComplaints[] = UnhandledDialogException::unexpected($dialog)->getMessage();

            return;
        }

        $this->handleUserPrompt($expectation->accept, $expectation->text);

        $mismatch = $expectation->mismatch($dialog);
        if ($mismatch !== null) {
            $this->dialogComplaints[] = UnhandledDialogException::mismatched($mismatch)->getMessage();
        }
    }

    private function handleUserPrompt(bool $accept, ?string $text = null): void
    {
        $params = ['context' => $this->context(), 'accept' => $accept];
        if ($text !== null) {
            $params['userText'] = $text;
        }

        try {
            $this->connection()->send('browsingContext.handleUserPrompt', $params);
        } catch (Throwable $e) {
            // The dialog may already have closed (a `beforeunload` the browser
            // accepts for us, or a page that tore itself down). Nothing to
            // answer is not a failure; losing the session would be.
            $this->logger->debug('BiDi could not handle a user prompt: {message}', ['message' => $e->getMessage()]);
        }
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
        $this->reportDialogs();
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
        $this->reportDialogs();
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

        $this->reportDialogs();

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
        $this->reportDialogs();

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
            $this->expectation = null;
            $this->lastDialog = null;
            $this->dialogComplaints = [];
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
