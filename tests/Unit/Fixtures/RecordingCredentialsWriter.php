<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use FluffyDiscord\Honkers\Exception\PairingException;
use FluffyDiscord\HonkersBundle\Contract\CredentialsWriterInterface;
use FluffyDiscord\HonkersBundle\Pairing\PairedCredentials;
use Symfony\Component\HttpFoundation\Request;

class RecordingCredentialsWriter implements CredentialsWriterInterface
{
    /**
     * @var list<PairedCredentials>
     */
    public array $savedCredentials = [];

    public function __construct(
        private readonly ?string $trustedHost = 'shop.example.com',
        private readonly ?string $saveErrorCode = null,
    ) {
    }

    public function findTrustedHost(Request $request): ?string
    {
        return $this->trustedHost;
    }

    public function save(PairedCredentials $credentials, Request $request): void
    {
        if ($this->saveErrorCode !== null) {
            throw new PairingException('The writer refused.', $this->saveErrorCode);
        }

        $this->savedCredentials[] = $credentials;
    }
}
