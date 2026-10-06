<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Site;

use FluffyDiscord\HonkersBundle\Contract\SiteKeyContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class ConfiguredSiteKeyContext implements SiteKeyContextInterface
{
    public function __construct(
        #[Autowire(param: 'fluffydiscord_honkers.widget.site_key')]
        private readonly string $siteKey,
    ) {
    }

    public function getSiteKey(): string
    {
        return $this->siteKey;
    }
}
