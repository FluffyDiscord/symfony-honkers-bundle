<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Pairing;

class PairedCredentials
{
    /**
     * @param list<string> $channelCodes
     * @param list<string> $verifiedDomains
     */
    public function __construct(
        public readonly string $siteKey,

        #[\SensitiveParameter]
        public readonly string $apiSecret,

        #[\SensitiveParameter]
        public readonly string $ingestSecret,
        public readonly array  $channelCodes,
        public readonly array  $verifiedDomains,
    ) {
    }
}
