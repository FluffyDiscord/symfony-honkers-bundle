<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Contract;

interface SiteKeyContextInterface
{
    public function getSiteKey(): string;
}
