<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Security;

use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class ApiSecretAuthenticator implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly CredentialsProviderInterface $credentialsProvider,
    ) {
    }

    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        $isValid = $this->credentialsProvider->isApiSecretValid($accessToken);
        if (!$isValid) {
            throw new BadCredentialsException('Invalid chatbot API secret.');
        }

        return new UserBadge(
            ChatbotBackendUser::IDENTIFIER,
            fn (): ChatbotBackendUser => new ChatbotBackendUser(),
        );
    }
}
