<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Contract;

use FluffyDiscord\Honkers\DTO\SiteCredentials;

interface CredentialsProviderInterface
{
    public function isApiSecretValid(#[\SensitiveParameter] string $token): bool;

    public function findCurrentSite(): ?SiteCredentials;
}
