<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Credentials;

use FluffyDiscord\Honkers\DTO\SiteCredentials;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class EnvCredentialsProvider implements CredentialsProviderInterface
{
    public function __construct(
        #[Autowire('%env(string:default::CHATBOT_API_SECRET)%')]
        #[\SensitiveParameter]
        private readonly string $apiSecret,

        #[Autowire('%env(string:default::CHATBOT_INGEST_SECRET)%')]
        #[\SensitiveParameter]
        private readonly string $ingestSecret,

        #[Autowire('%env(string:default::CHATBOT_SITE_KEY)%')]
        private readonly string $siteKey,
    ) {
    }

    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool
    {
        if ($this->apiSecret === '') {
            return false;
        }

        return hash_equals($this->apiSecret, $token);
    }

    public function findCurrentSite(): ?SiteCredentials
    {
        if ($this->siteKey === '') {
            return null;
        }

        return $this->findSite($this->siteKey);
    }

    public function findSite(string $siteKey): SiteCredentials
    {
        return new SiteCredentials($siteKey, $this->ingestSecret);
    }
}
