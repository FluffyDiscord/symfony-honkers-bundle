<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Reporting;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class BackendReportGuard
{
    public function __construct(
        private readonly LoggerInterface $logger,

        #[Autowire(param: 'fluffydiscord_honkers.backend_url')]
        private readonly string $backendUrl,

        #[Autowire(param: 'fluffydiscord_honkers.ingest_secret')]
        private readonly string $ingestSecret,

        #[Autowire(param: 'kernel.environment')]
        private readonly string $environment,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getMissingBackendConfigurationKeys(): array
    {
        $missingKeys = [];
        if ($this->backendUrl === '') {
            $missingKeys[] = 'backend_url';
        }
        if ($this->ingestSecret === '') {
            $missingKeys[] = 'ingest_secret';
        }

        return $missingKeys;
    }

    /**
     * @return list<string>
     */
    public function getMissingConfigurationKeys(string $siteKey): array
    {
        $missingKeys = $this->getMissingBackendConfigurationKeys();
        if ($siteKey === '') {
            $missingKeys[] = 'widget.site_key';
        }

        return $missingKeys;
    }

    public function canReport(string $siteKey, string $feature): bool
    {
        $missingKeys = $this->getMissingConfigurationKeys($siteKey);
        if ($missingKeys !== []) {
            return false;
        }

        $isAllowedBackendUrl = $this->isAllowedBackendUrl();
        if (!$isAllowedBackendUrl) {
            $this->logger->warning('Chatbot: refusing to report to a non-https backend URL.', [
                'feature' => $feature,
                'backendUrl' => $this->backendUrl,
                'environment' => $this->environment,
            ]);

            return false;
        }

        return true;
    }

    private function isAllowedBackendUrl(): bool
    {
        $scheme = parse_url($this->backendUrl, PHP_URL_SCHEME);
        $isSecure = $scheme === 'https';
        if ($isSecure) {
            return true;
        }

        return $this->environment === 'dev';
    }
}
