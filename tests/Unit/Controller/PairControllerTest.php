<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Controller;

use FluffyDiscord\Honkers\Pairing\HostMatcher;
use FluffyDiscord\Honkers\Pairing\PairingClient;
use FluffyDiscord\HonkersBundle\Controller\PairController;
use FluffyDiscord\HonkersBundle\Pairing\PairingErrorCode;
use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\RecordingCredentialsWriter;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response as PsrResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

class PairControllerTest extends TestCase
{
    private const VALID_CODE = 'AbCdEfGhIjKlMnOpQrStUvWxYz0123456789-_AbCdE';

    private const RETURN_URL = 'https://chatbot.example.com/dashboard/pairings/0199/finish';

    /**
     * @var list<RequestInterface>
     */
    private array $sentRequests = [];

    public function testAShopWithoutAWriterShowsTheManualCredentialsPage(): void
    {
        $response = $this->pair(null);

        self::assertSame(Response::HTTP_CONFLICT, $response->getStatusCode());
        self::assertStringContainsString('fluffydiscord_honkers.pairing.environment_credentials', (string) $response->getContent());
        self::assertSame([], $this->sentRequests);
    }

    public function testAMalformedCodeIsRefusedWithoutCallingTheBackend(): void
    {
        $writer = new RecordingCredentialsWriter();

        $response = $this->pair($writer, code: 'too-short');

        $this->assertErrorPage($response, PairingErrorCode::InvalidCode);
        self::assertSame([], $this->sentRequests);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testAPlainHttpBackendIsRefusedWithoutCallingIt(): void
    {
        $writer = new RecordingCredentialsWriter();

        $response = $this->pair($writer, backendUrl: 'http://chatbot.example.com');

        $this->assertErrorPage($response, PairingErrorCode::BackendUrlRefused);
        self::assertSame([], $this->sentRequests);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testAShopWithoutATrustedHostIsRefusedWithoutCallingTheBackend(): void
    {
        $writer = new RecordingCredentialsWriter(null);

        $response = $this->pair($writer);

        $this->assertErrorPage($response, PairingErrorCode::HostnameMissing);
        self::assertSame([], $this->sentRequests);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testASuccessfulPairingSavesTheSentSecretsAndRedirectsToTheReturnUrl(): void
    {
        $writer = new RecordingCredentialsWriter();

        $response = $this->pair($writer);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame(self::RETURN_URL, $response->getTargetUrl());

        self::assertCount(1, $writer->savedCredentials);
        $saved = $writer->savedCredentials[0];
        $sentBody = $this->getSentBody();
        self::assertSame('https://chatbot.example.com/api/v1/tool-server-pairings/complete', (string) $this->sentRequests[0]->getUri());
        self::assertSame(self::VALID_CODE, $sentBody['code']);
        self::assertSame($sentBody['apiSecret'], $saved->apiSecret);
        self::assertSame($sentBody['ingestSecret'], $saved->ingestSecret);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $saved->apiSecret);
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $saved->ingestSecret);
        self::assertNotSame($saved->apiSecret, $saved->ingestSecret);
        self::assertSame('site_key_1', $saved->siteKey);
        self::assertSame(['default'], $saved->channelCodes);
        self::assertSame(['shop.example.com'], $saved->verifiedDomains);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideMatchingHosts(): iterable
    {
        yield 'case' => ['Shop.Example.COM', 'https://shop.example.com'];
        yield 'trailing dot' => ['shop.example.com.', 'https://shop.example.com'];
        yield 'port' => ['shop.example.com', 'https://shop.example.com:8443'];
        yield 'idn' => ['obchod.příklad.cz', 'https://obchod.xn--pklad-zsa96e.cz'];
    }

    #[DataProvider('provideMatchingHosts')]
    public function testTheTrustedHostMatchesAfterNormalizing(string $trustedHost, string $baseUrl): void
    {
        $writer = new RecordingCredentialsWriter($trustedHost);
        $backendResponse = $this->createSuccessResponse($baseUrl);

        $response = $this->pair($writer, backendResponse: $backendResponse);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertCount(1, $writer->savedCredentials);
    }

    public function testABackendRejectionShowsItsCodeAndSavesNothing(): void
    {
        $writer = new RecordingCredentialsWriter();
        $backendResponse = new PsrResponse(404, [], '{"error":{"code":"pairing_not_found"}}');

        $response = $this->pair($writer, backendResponse: $backendResponse);

        $this->assertErrorPage($response, PairingErrorCode::NotFound);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testAnUnknownBackendCodeShowsTheGenericMessageAndTheRawCode(): void
    {
        $writer = new RecordingCredentialsWriter();
        $backendResponse = new PsrResponse(500, [], '{"error":{"code":"something_new"}}');

        $response = $this->pair($writer, backendResponse: $backendResponse);
        $content = (string) $response->getContent();

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('fluffydiscord_honkers.pairing.error.unknown', $content);
        self::assertStringContainsString('<code>something_new</code>', $content);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testAPairingForAnotherShopIsRefusedAndSavesNothing(): void
    {
        $writer = new RecordingCredentialsWriter();
        $backendResponse = $this->createSuccessResponse('https://attacker.example.net');

        $response = $this->pair($writer, backendResponse: $backendResponse);

        $this->assertErrorPage($response, PairingErrorCode::WrongShop);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testAWwwHostIsNotTheSameShop(): void
    {
        $writer = new RecordingCredentialsWriter();
        $backendResponse = $this->createSuccessResponse('https://www.shop.example.com');

        $response = $this->pair($writer, backendResponse: $backendResponse);

        $this->assertErrorPage($response, PairingErrorCode::WrongShop);
        self::assertSame([], $writer->savedCredentials);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function provideForeignReturnUrls(): iterable
    {
        yield 'other host' => ['https://attacker.example.net/dashboard'];
        yield 'other scheme' => ['http://chatbot.example.com/dashboard'];
        yield 'other port' => ['https://chatbot.example.com:8443/dashboard'];
        yield 'subdomain' => ['https://evil.chatbot.example.com/dashboard'];
        yield 'protocol-relative' => ['//attacker.example.net/dashboard'];
        yield 'relative' => ['/dashboard'];
        yield 'javascript' => ['javascript:alert(1)'];
    }

    #[DataProvider('provideForeignReturnUrls')]
    public function testAReturnUrlOffTheBackendOriginIsAnInvalidResponseAndSavesNothing(string $returnUrl): void
    {
        $writer = new RecordingCredentialsWriter();
        $backendResponse = $this->createSuccessResponse('https://shop.example.com', $returnUrl);

        $response = $this->pair($writer, backendResponse: $backendResponse);

        $this->assertErrorPage($response, PairingErrorCode::InvalidResponse);
        self::assertSame([], $writer->savedCredentials);
    }

    public function testAReturnUrlOnTheBackendOriginMatchesRegardlessOfHostCase(): void
    {
        $writer = new RecordingCredentialsWriter();
        $returnUrl = 'HTTPS://Chatbot.Example.com/dashboard';
        $backendResponse = $this->createSuccessResponse('https://shop.example.com', $returnUrl);

        $response = $this->pair($writer, backendResponse: $backendResponse);

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame($returnUrl, $response->getTargetUrl());
    }

    public function testAWriterRefusalShowsItsCode(): void
    {
        $writer = new RecordingCredentialsWriter(saveErrorCode: PairingErrorCode::Unsupported->value);

        $response = $this->pair($writer);

        $this->assertErrorPage($response, PairingErrorCode::Unsupported);
    }

    private function pair(
        ?RecordingCredentialsWriter $writer,
        string                      $code = self::VALID_CODE,
        string                      $backendUrl = 'https://chatbot.example.com',
        ?ResponseInterface          $backendResponse = null,
    ): Response {
        $backendResponse ??= $this->createSuccessResponse('https://shop.example.com');
        $factory = new Psr17Factory();
        $httpClient = $this->createRecordingHttpClient($backendResponse);
        $pairingClient = new PairingClient($httpClient, $factory, $factory, $backendUrl);
        $guard = new BackendReportGuard(new NullLogger(), $backendUrl, 'prod');
        $translator = $this->createIdentityTranslator();

        $controller = new PairController($pairingClient, $guard, $translator, new HostMatcher(), $backendUrl, $writer);
        $request = Request::create('/chatbot/pair');

        return $controller($request, $code);
    }

    private function assertErrorPage(Response $response, PairingErrorCode $errorCode): void
    {
        $content = (string) $response->getContent();

        self::assertSame(Response::HTTP_BAD_REQUEST, $response->getStatusCode());
        self::assertStringContainsString('fluffydiscord_honkers.pairing.error.' . $errorCode->value, $content);
        self::assertStringContainsString('<code>' . $errorCode->value . '</code>', $content);
    }

    private function createSuccessResponse(string $baseUrl, string $returnUrl = self::RETURN_URL): ResponseInterface
    {
        $payload = [
            'siteKey' => 'site_key_1',
            'baseUrl' => $baseUrl,
            'channelCodes' => ['default'],
            'returnUrl' => $returnUrl,
            'verifiedDomains' => ['shop.example.com'],
        ];

        return new PsrResponse(200, [], json_encode($payload, JSON_THROW_ON_ERROR));
    }

    /**
     * @return array<string, string>
     */
    private function getSentBody(): array
    {
        $body = (string) $this->sentRequests[0]->getBody();

        return json_decode($body, true, 512, JSON_THROW_ON_ERROR);
    }

    private function createRecordingHttpClient(ResponseInterface $response): ClientInterface
    {
        $httpClient = $this->createStub(ClientInterface::class);
        $httpClient->method('sendRequest')->willReturnCallback(
            function (RequestInterface $request) use ($response): ResponseInterface {
                $this->sentRequests[] = $request;

                return $response;
            },
        );

        return $httpClient;
    }

    private function createIdentityTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return $translator;
    }
}
