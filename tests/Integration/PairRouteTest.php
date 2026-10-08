<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class PairRouteTest extends TestCase
{
    private const VALID_CODE = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_AbCdE';

    public function testTheCodeIsReadFromTheQueryString(): void
    {
        $response = $this->requestPairing('/chatbot/pair?code=' . self::VALID_CODE);

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('<code>backend_url_refused</code>', (string) $response->getContent());
    }

    public function testAMissingCodeIsAnInvalidCode(): void
    {
        $response = $this->requestPairing('/chatbot/pair');

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('<code>invalid_code</code>', (string) $response->getContent());
    }

    protected function tearDown(): void
    {
        $cacheDir = sys_get_temp_dir() . '/honkers_bundle_test';
        if (is_dir($cacheDir)) {
            exec('rm -rf ' . escapeshellarg($cacheDir));
        }
    }

    private function requestPairing(string $uri): Response
    {
        $kernel = new HonkersTestKernel('test', true, withCredentialsWriter: true);
        $request = Request::create($uri);

        return $kernel->handle($request);
    }
}
