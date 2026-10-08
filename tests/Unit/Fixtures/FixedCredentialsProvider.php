<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use FluffyDiscord\Honkers\DTO\SiteCredentials;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;

class FixedCredentialsProvider implements CredentialsProviderInterface
{
    public function __construct(
        private readonly ?SiteCredentials $site = new SiteCredentials('site-a', 'ingest-secret'),
    ) {
    }

    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool
    {
        return $token === 'fixed-secret';
    }

    public function findCurrentSite(): ?SiteCredentials
    {
        return $this->site;
    }
}
