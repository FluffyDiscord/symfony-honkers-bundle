<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Translation;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Translation\TranslatorBagInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChannelFallbackTranslator implements TranslatorInterface
{
    public function __construct(
        #[Autowire(service: 'translator')]
        private readonly TranslatorInterface&TranslatorBagInterface $translator,
        private readonly ChatbotLocaleContextInterface              $localeContext,
    ) {
    }

    public function trans(string $id, array $parameters = [], ?string $domain = null, ?string $locale = null): string
    {
        $requestedLocale = $locale ?? $this->translator->getLocale();
        $isTranslated = $this->isTranslatedInLanguage($id, $domain ?? 'messages', $requestedLocale);
        $translationLocale = $isTranslated ? $requestedLocale : $this->localeContext->getDefaultLocale();

        return $this->translator->trans($id, $parameters, $domain, $translationLocale);
    }

    public function getLocale(): string
    {
        return $this->translator->getLocale();
    }

    private function isTranslatedInLanguage(string $id, string $domain, string $locale): bool
    {
        $language = \Locale::getPrimaryLanguage($locale);
        $catalogue = $this->translator->getCatalogue($locale);

        while ($catalogue !== null) {
            $catalogueLanguage = \Locale::getPrimaryLanguage($catalogue->getLocale());

            if ($catalogueLanguage !== $language) {
                return false;
            }

            $isDefined = $catalogue->defines($id, $domain);

            if ($isDefined) {
                return true;
            }

            $catalogue = $catalogue->getFallbackCatalogue();
        }

        return false;
    }
}
