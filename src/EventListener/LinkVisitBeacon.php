<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\EventListener;

use FluffyDiscord\Honkers\DTO\SiteCredentials;
use FluffyDiscord\Honkers\Telemetry\ClickId;
use FluffyDiscord\Honkers\Telemetry\TelemetryClient;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Contracts\Service\ResetInterface;

class LinkVisitBeacon implements ResetInterface
{
    private string $visitClickId = '';

    private string $pageUrl = '';

    private ?SiteCredentials $site = null;

    public function __construct(
        private readonly TelemetryClient              $telemetryClient,
        private readonly CredentialsProviderInterface $credentialsProvider,
        private readonly ClickId                      $clickId,
        private readonly BackendReportGuard           $backendReportGuard,
        private readonly LoggerInterface              $logger,
    ) {
    }

    #[AsEventListener(event: KernelEvents::REQUEST)]
    public function rememberVisit(RequestEvent $event): void
    {
        $isMainRequest = $event->isMainRequest();
        if (!$isMainRequest) {
            return;
        }

        $request = $event->getRequest();
        $isLandingRequest = $this->isLandingRequest($request);
        if (!$isLandingRequest) {
            return;
        }

        $query = $request->query->all();
        $clickId = $this->clickId->find($query);
        if ($clickId === null) {
            return;
        }

        $site = $this->findCurrentSite();
        if ($site === null) {
            return;
        }

        $hasIngestSecret = $site->hasIngestSecret();
        if (!$hasIngestSecret) {
            return;
        }

        $canReport = $this->backendReportGuard->canReport('chat link visit');
        if (!$canReport) {
            return;
        }

        $path = $request->getPathInfo();
        $this->visitClickId = $clickId;
        $this->pageUrl = $request->getUriForPath($path);
        $this->site = $site;
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function reportVisit(TerminateEvent $event): void
    {
        $clickId = $this->visitClickId;
        $pageUrl = $this->pageUrl;
        $site = $this->site;
        $this->reset();

        if ($site === null) {
            return;
        }

        $response = $event->getResponse();
        $isSuccessful = $response->isSuccessful();
        if (!$isSuccessful) {
            return;
        }

        try {
            $this->telemetryClient->reportLinkVisit($site, $clickId, $pageUrl);
        } catch (\Throwable $exception) {
            $this->logger->warning('Chatbot: reporting the chat link visit failed.', [
                'pageUrl' => $pageUrl,
                'exception' => $exception,
            ]);
        }
    }

    public function reset(): void
    {
        $this->visitClickId = '';
        $this->pageUrl = '';
        $this->site = null;
    }

    /**
     * @return list<string>
     */
    private function getPrefetchHeaderNames(): array
    {
        return ['Sec-Purpose', 'Purpose'];
    }

    private function isLandingRequest(Request $request): bool
    {
        $isGet = $request->isMethod(Request::METHOD_GET);
        if (!$isGet) {
            return false;
        }

        $isPrefetch = $this->isPrefetch($request);

        return !$isPrefetch;
    }

    private function isPrefetch(Request $request): bool
    {
        foreach ($this->getPrefetchHeaderNames() as $headerName) {
            $headerValue = (string) $request->headers->get($headerName);
            $normalizedValue = strtolower($headerValue);
            $mentionsPrefetch = str_contains($normalizedValue, 'prefetch');
            if ($mentionsPrefetch) {
                return true;
            }
        }

        return false;
    }

    private function findCurrentSite(): ?SiteCredentials
    {
        try {
            return $this->credentialsProvider->findCurrentSite();
        } catch (\Throwable $exception) {
            $this->logger->warning('Chatbot: resolving the site for the chat link visit failed.', ['exception' => $exception]);

            return null;
        }
    }
}
