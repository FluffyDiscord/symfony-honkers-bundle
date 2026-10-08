<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Contract;

use FluffyDiscord\Honkers\Exception\PairingException;
use FluffyDiscord\HonkersBundle\Pairing\PairedCredentials;
use Symfony\Component\HttpFoundation\Request;

interface CredentialsWriterInterface
{
    public function findTrustedHost(Request $request): ?string;

    /**
     * @throws PairingException
     */
    public function save(PairedCredentials $credentials, Request $request): void;
}
