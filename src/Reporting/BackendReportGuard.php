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

        return $missingKeys;
    }

    public function canReport(string $feature): bool
    {
        $missingKeys = $this->getMissingBackendConfigurationKeys();
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
