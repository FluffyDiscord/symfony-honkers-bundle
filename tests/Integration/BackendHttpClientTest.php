<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\Psr18Client;

class BackendHttpClientTest extends TestCase
{
    private const SERVER_START_ATTEMPTS = 50;

    private const SERVER_START_WAIT_MICROSECONDS = 100_000;

    /**
     * @var resource|null
     */
    private $serverProcess = null;

    public function testARedirectIsNotFollowed(): void
    {
        $serverUrl = $this->startRedirectServer();
        $kernel = new HonkersTestKernel('test', true);
        $kernel->boot();
        $container = $kernel->getContainer()->get('test.service_container');
        $httpClient = $container->get(Psr18Client::class);

        $request = $httpClient->createRequest('POST', $serverUrl . '/api/v1/tool-server-pairings/complete');
        $response = $httpClient->sendRequest($request);

        self::assertSame(307, $response->getStatusCode());
        self::assertSame('/followed', $response->getHeaderLine('Location'));
    }

    protected function tearDown(): void
    {
        if ($this->serverProcess !== null) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
        }

        $cacheDir = sys_get_temp_dir() . '/honkers_bundle_test';
        if (is_dir($cacheDir)) {
            exec('rm -rf ' . escapeshellarg($cacheDir));
        }
    }

    private function startRedirectServer(): string
    {
        $port = random_int(20000, 40000);
        $address = '127.0.0.1:' . $port;
        $command = [PHP_BINARY, '-S', $address, __DIR__ . '/Fixtures/redirect-router.php'];
        $descriptors = [1 => ['file', '/dev/null', 'w'], 2 => ['file', '/dev/null', 'w']];

        $serverProcess = proc_open($command, $descriptors, $pipes);
        if ($serverProcess === false) {
            throw new RuntimeException('The redirect server could not be started.');
        }

        $this->serverProcess = $serverProcess;
        $this->waitForServer($address);

        return 'http://' . $address;
    }

    private function waitForServer(string $address): void
    {
        for ($attempt = 0; $attempt < self::SERVER_START_ATTEMPTS; ++$attempt) {
            $socket = @fsockopen('tcp://' . $address);
            if ($socket !== false) {
                fclose($socket);

                return;
            }

            usleep(self::SERVER_START_WAIT_MICROSECONDS);
        }

        throw new RuntimeException('The redirect server did not start on ' . $address . '.');
    }
}
