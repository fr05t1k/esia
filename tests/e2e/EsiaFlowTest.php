<?php

namespace tests\e2e;

use Codeception\Test\Unit;
use Esia\Config;
use Esia\OpenId;
use RuntimeException;
use Symfony\Component\HttpClient\Psr18Client;

/**
 * End-to-end smoke test of the whole ESIA flow against a local mock server.
 *
 * A PHP built-in web server serves {@see tests/_support/mock_esia_router.php}
 * on a random loopback port; the library then runs the real HTTP round-trip
 * through a genuine PSR-18 client (Symfony HttpClient). This gives offline,
 * deterministic coverage of the transport + response parsing without needing
 * access to the geo-restricted ESIA test stand.
 */
class EsiaFlowTest extends Unit
{
    /** @var resource|null */
    private $serverProcess;

    /** @var array<int, resource> */
    private array $pipes = [];

    private string $portalUrl = '';

    private OpenId $openId;

    protected function _before(): void
    {
        $host = '127.0.0.1';
        $port = $this->reserveFreePort($host);
        $this->portalUrl = "http://$host:$port/";

        $router = codecept_root_dir('tests/_support/mock_esia_router.php');
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open(
            [PHP_BINARY, '-S', "$host:$port", $router],
            $descriptors,
            $this->pipes
        );

        if (!is_resource($process)) {
            throw new RuntimeException('Unable to start the mock ESIA server');
        }

        $this->serverProcess = $process;
        $this->waitUntilListening($host, $port);

        $client = new Psr18Client();
        $config = new Config([
            'clientId' => 'INSP03211',
            'redirectUrl' => 'http://my-site.com/response.php',
            'portalUrl' => $this->portalUrl,
            'scope' => ['fullname', 'openid'],
            'privateKeyPath' => codecept_data_dir('server.key'),
            'privateKeyPassword' => 'test',
            'certPath' => codecept_data_dir('server.crt'),
            'tmpPath' => codecept_log_dir(),
        ]);

        // Same instance implements the PSR-18 client and PSR-17 factories.
        $this->openId = new OpenId($config, $client, $client, $client);
    }

    protected function _after(): void
    {
        foreach ($this->pipes as $pipe) {
            if (is_resource($pipe)) {
                fclose($pipe);
            }
        }
        $this->pipes = [];

        if (is_resource($this->serverProcess)) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
            $this->serverProcess = null;
        }
    }

    public function testBuildUrlPointsAtMockPortal(): void
    {
        $url = $this->openId->buildUrl();

        self::assertStringStartsWith($this->portalUrl . 'aas/oauth2/v2/ac', $url);
        self::assertStringContainsString('client_id=INSP03211', $url);
        self::assertStringContainsString('client_secret=', $url);
    }

    public function testFullAuthorizationFlow(): void
    {
        $token = $this->openId->getToken('authorization-code');
        self::assertNotEmpty($token);
        self::assertSame('1000299944', $this->openId->getConfig()->getOid());

        $person = $this->openId->getPersonInfo();
        self::assertSame('Иван', $person['firstName']);
        self::assertSame('Иванов', $person['lastName']);

        $contacts = $this->openId->getContactInfo();
        self::assertCount(2, $contacts);
        self::assertSame('ivan@example.com', $contacts[0]['value']);

        $roles = $this->openId->getRoles();
        self::assertCount(1, $roles);
        self::assertTrue($roles[0]['chief']);
        self::assertSame('Ромашка', $roles[0]['shortName']);

        $organizations = $this->openId->getOrganizations();
        self::assertCount(1, $organizations);
        self::assertSame(111, $organizations[0]['oid']);
        self::assertSame('LEGAL', $organizations[0]['type']);
    }

    private function reserveFreePort(string $host): int
    {
        $socket = @stream_socket_server("tcp://$host:0", $errno, $errstr);
        if ($socket === false) {
            throw new RuntimeException("Cannot reserve a free port: $errstr ($errno)");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        $port = (int) substr((string) $name, (int) strrpos((string) $name, ':') + 1);
        if ($port <= 0) {
            throw new RuntimeException('Cannot determine a free port');
        }

        return $port;
    }

    private function waitUntilListening(string $host, int $port): void
    {
        $deadline = microtime(true) + 5.0;
        while (microtime(true) < $deadline) {
            $connection = @fsockopen($host, $port, $errno, $errstr, 0.2);
            if (is_resource($connection)) {
                fclose($connection);

                return;
            }
            usleep(50_000);
        }

        throw new RuntimeException("Mock ESIA server did not start on $host:$port");
    }
}
