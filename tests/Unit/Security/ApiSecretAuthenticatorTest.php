<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Security;

use FluffyDiscord\HonkersBundle\Security\ApiSecretAuthenticator;
use FluffyDiscord\HonkersBundle\Security\ChatbotBackendUser;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;

class ApiSecretAuthenticatorTest extends TestCase
{
    public function testValidSecretReturnsBackendUserBadge(): void
    {
        $authenticator = new ApiSecretAuthenticator('top-secret');

        $badge = $authenticator->getUserBadgeFrom('top-secret');

        self::assertSame(ChatbotBackendUser::IDENTIFIER, $badge->getUserIdentifier());
        $user = ($badge->getUserLoader())(ChatbotBackendUser::IDENTIFIER);
        self::assertInstanceOf(ChatbotBackendUser::class, $user);
        self::assertSame([ChatbotBackendUser::ROLE], $user->getRoles());
    }

    public function testWrongSecretThrowsBadCredentials(): void
    {
        $authenticator = new ApiSecretAuthenticator('top-secret');

        $this->expectException(BadCredentialsException::class);

        $authenticator->getUserBadgeFrom('wrong-secret');
    }

    public function testEmptyConfiguredSecretAlwaysThrows(): void
    {
        $authenticator = new ApiSecretAuthenticator('');

        $this->expectException(BadCredentialsException::class);

        $authenticator->getUserBadgeFrom('');
    }
}
