<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\EventListener;

use FluffyDiscord\Honkers\DTO\SiteCredentials;
use FluffyDiscord\Honkers\Exception\TelemetryException;
use FluffyDiscord\Honkers\Telemetry\ClickId;
use FluffyDiscord\Honkers\Telemetry\TelemetryClient;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use FluffyDiscord\HonkersBundle\EventListener\LinkVisitBeacon;
use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\FixedCredentialsProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class LinkVisitBeaconTest extends TestCase
{
    public function testTheVisitIsReportedOnceOnTerminateWithTheSiteCapturedAtRequestTime(): void
    {
        $requestStack = new RequestStack();
        $site = new SiteCredentials('site-a', 'ingest-secret');
        $credentialsProvider = $this->createRequestScopedCredentialsProvider($requestStack, $site);
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::once())
            ->method('reportLinkVisit')
            ->with(self::identicalTo($site), 'click-1', 'https://shop.example:8443/products/clipper');
        $beacon = $this->createBeacon($telemetryClient, $credentialsProvider);
        $request = Request::create('https://shop.example:8443/products/clipper?gooseclid=click-1&utm_source=chat#reviews');

        $requestStack->push($request);
        $beacon->rememberVisit($this->createRequestEvent($request));
        $requestStack->pop();

        $beacon->reportVisit($this->createTerminateEvent($request, new Response()));
        $beacon->reportVisit($this->createTerminateEvent($request, new Response()));
    }

    public function testARequestWithoutAClickIdIsNotReported(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $beacon = $this->createBeacon($telemetryClient);

        $this->handle($beacon, Request::create('https://shop.example/products/clipper?utm_source=chat'), new Response());
    }

    public function testAPrefetchIsNotReported(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $beacon = $this->createBeacon($telemetryClient);

        $secPurposeRequest = Request::create('https://shop.example/products/clipper?gooseclid=click-1', server: ['HTTP_SEC_PURPOSE' => 'prefetch;prerender']);
        $purposeRequest = Request::create('https://shop.example/products/clipper?gooseclid=click-1', server: ['HTTP_PURPOSE' => 'Prefetch']);

        $this->handle($beacon, $secPurposeRequest, new Response());
        $this->handle($beacon, $purposeRequest, new Response());
    }

    public function testANonGetRequestIsNotReported(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $beacon = $this->createBeacon($telemetryClient);

        $this->handle($beacon, Request::create('https://shop.example/products/clipper?gooseclid=click-1', Request::METHOD_POST), new Response());
    }

    public function testARedirectOrErrorResponseIsNotReported(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $beacon = $this->createBeacon($telemetryClient);
        $request = Request::create('https://shop.example/products/clipper?gooseclid=click-1');

        $this->handle($beacon, $request, new Response('', Response::HTTP_FOUND));
        $this->handle($beacon, $request, new Response('', Response::HTTP_NOT_FOUND));
    }

    public function testAnUnconfiguredBeaconReportsNothingAndLogsNothing(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('warning');
        $beacon = $this->createBeacon($telemetryClient, new FixedCredentialsProvider(null), $logger, '');
        $request = Request::create('https://shop.example/products/clipper?gooseclid=click-1');

        $this->handle($beacon, $request, new Response());
        $this->handle($beacon, $request, new Response());
    }

    public function testASiteWithoutAnIngestSecretIsNotReported(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $credentialsProvider = new FixedCredentialsProvider(new SiteCredentials('site-a', ''));
        $beacon = $this->createBeacon($telemetryClient, $credentialsProvider);

        $this->handle($beacon, Request::create('https://shop.example/products/clipper?gooseclid=click-1'), new Response());
    }

    public function testAnHttpBackendIsRefusedOutsideDev(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::never())->method('reportLinkVisit');
        $beacon = $this->createBeacon($telemetryClient, backendUrl: 'http://honkers.example', environment: 'prod');

        $this->handle($beacon, Request::create('https://shop.example/products/clipper?gooseclid=click-1'), new Response());
    }

    public function testAnHttpBackendIsAllowedInDev(): void
    {
        $telemetryClient = $this->createMock(TelemetryClient::class);
        $telemetryClient->expects(self::once())->method('reportLinkVisit');
        $beacon = $this->createBeacon($telemetryClient, backendUrl: 'http://honkers.example', environment: 'dev');

        $this->handle($beacon, Request::create('https://shop.example/products/clipper?gooseclid=click-1'), new Response());
    }

    public function testATelemetryFailureIsLoggedAndSwallowed(): void
    {
        $telemetryClient = $this->createStub(TelemetryClient::class);
        $telemetryClient->method('reportLinkVisit')
            ->willThrowException(new TelemetryException('The telemetry backend rejected the request with HTTP 500.', 500));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with('Chatbot: reporting the chat link visit failed.', self::anything());
        $beacon = $this->createBeacon($telemetryClient, logger: $logger);

        $this->handle($beacon, Request::create('https://shop.example/products/clipper?gooseclid=click-1'), new Response());
    }

    private function handle(LinkVisitBeacon $beacon, Request $request, Response $response): void
    {
        $beacon->rememberVisit($this->createRequestEvent($request));
        $beacon->reportVisit($this->createTerminateEvent($request, $response));
    }

    private function createBeacon(
        TelemetryClient $telemetryClient,
        ?CredentialsProviderInterface $credentialsProvider = null,
        ?LoggerInterface $logger = null,
        string $backendUrl = 'https://honkers.example',
        string $environment = 'prod',
    ): LinkVisitBeacon {
        $logger ??= new NullLogger();
        $backendReportGuard = new BackendReportGuard($logger, $backendUrl, $environment);

        return new LinkVisitBeacon(
            $telemetryClient,
            $credentialsProvider ?? new FixedCredentialsProvider(),
            new ClickId(),
            $backendReportGuard,
            $logger,
        );
    }

    private function createRequestScopedCredentialsProvider(RequestStack $requestStack, SiteCredentials $site): CredentialsProviderInterface
    {
        return new class($requestStack, $site) implements CredentialsProviderInterface {
            public function __construct(
                private readonly RequestStack    $requestStack,
                private readonly SiteCredentials $site,
            ) {
            }

            public function isApiSecretValid(#[\SensitiveParameter] string $token): bool
            {
                return false;
            }

            public function findCurrentSite(): ?SiteCredentials
            {
                $request = $this->requestStack->getCurrentRequest();
                if ($request === null) {
                    return null;
                }

                return $this->site;
            }
        };
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new RequestEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST);
    }

    private function createTerminateEvent(Request $request, Response $response): TerminateEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);

        return new TerminateEvent($kernel, $request, $response);
    }
}
