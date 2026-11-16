<?php

declare(strict_types=1);

namespace Vusys\Tetryon\Tests\Unit\Core\Server;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Vusys\Tetryon\Core\Server\ManagedServer;
use Vusys\Tetryon\Core\Server\ServerSettings;

#[CoversClass(ManagedServer::class)]
#[CoversClass(ServerSettings::class)]
final class ManagedServerTest extends TestCase
{
    private const string FIXTURE = __DIR__.'/../../../Fixtures/server-env';

    protected function tearDown(): void
    {
        ManagedServer::stopRunning();
        putenv('TEST_TOKEN');
    }

    public function test_it_starts_the_server_once_and_passes_the_expanded_env(): void
    {
        $settings = $this->settings(['TETRYON_PROBE' => 'db_{token}_on_{port}']);

        $url = ManagedServer::ensure($settings);

        self::assertSame('http://127.0.0.1:'.$settings->port(), $url);
        self::assertSame('db_0_on_'.$settings->port(), $this->get($url));
        self::assertSame($url, ManagedServer::ensure($settings), 'a second call reuses the running server');
    }

    public function test_the_port_follows_the_paratest_token(): void
    {
        putenv('TEST_TOKEN=3');
        $settings = $this->settings(['TETRYON_PROBE' => 'worker {token}']);

        $url = ManagedServer::ensure($settings);

        self::assertSame('http://127.0.0.1:'.($settings->basePort + 3), $url);
        self::assertSame('worker 3', $this->get($url));
    }

    public function test_stopping_shuts_the_server_down(): void
    {
        $url = ManagedServer::ensure($this->settings());
        ManagedServer::stopRunning();

        self::assertFalse(@file_get_contents($url, false, stream_context_create(['http' => ['timeout' => 1]])));
    }

    public function test_a_server_that_never_answers_fails_with_its_output(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/did not answer .* within 500ms\.\nServer output:\nnot a server/');

        ManagedServer::ensure(new ServerSettings(
            command: 'sh -c "echo not a server >&2; sleep 5"',
            basePort: $this->freePort(),
            readyTimeoutMs: 500,
        ));
    }

    /**
     * @param  array<string, string>  $env
     */
    private function settings(array $env = []): ServerSettings
    {
        return new ServerSettings(
            command: PHP_BINARY.' -S {host}:{port} -t '.self::FIXTURE,
            // a free port for this worker's token
            basePort: $this->freePort() - ServerSettings::token(),
            readyTimeoutMs: 5000,
            env: $env,
        );
    }

    private function get(string $url): string
    {
        return (string) file_get_contents($url);
    }

    private function freePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        self::assertIsResource($socket);
        $name = (string) stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, (int) strrpos($name, ':') + 1);
    }
}
