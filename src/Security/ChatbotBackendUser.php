<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Security;

use Symfony\Component\Security\Core\User\UserInterface;

class ChatbotBackendUser implements UserInterface
{
    public const IDENTIFIER = 'chatbot-backend';

    public const ROLE = 'ROLE_CHATBOT_BACKEND';

    public function getRoles(): array
    {
        return [self::ROLE];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return self::IDENTIFIER;
    }
}
