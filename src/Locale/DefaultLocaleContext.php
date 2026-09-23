<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Locale;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class DefaultLocaleContext implements ChatbotLocaleContextInterface
{
    public function __construct(
        private readonly LocaleMatcher $localeMatcher,

        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    public function applyChannel(?string $channelCode): void
    {
    }

    public function getCurrentLocale(): string
    {
        return $this->defaultLocale;
    }

    /**
     * @return list<string>
     */
    public function getChannelLocales(): array
    {
        return [$this->defaultLocale];
    }

    /**
     * @return list<string>
     */
    public function getAllChannelLocales(): array
    {
        return [$this->defaultLocale];
    }

    public function resolveForChannel(string $requestedLocale): ?string
    {
        return $this->localeMatcher->resolveServedLocale($requestedLocale, [$this->defaultLocale]);
    }
}
