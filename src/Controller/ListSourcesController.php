<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\HonkersBundle\Translation\ChannelFallbackTranslator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListSourcesController extends AbstractController
{
    public function __construct(
        private readonly DataSourceRegistry            $dataSourceRegistry,

        #[Autowire(service: ChannelFallbackTranslator::class)]
        private readonly TranslatorInterface           $translator,
        private readonly ChatbotLocaleContextInterface $localeContext,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $locale = $this->localeContext->getCurrentLocale();
        $channelLocales = $this->localeContext->getAllChannelLocales();

        $sources = [];
        foreach ($this->dataSourceRegistry->all() as $source) {
            $definition = $source->getDefinition();
            $definition = $definition
                ->withLocales($definition->locales ?? $channelLocales)
                ->translated($this->translator, $locale);
            $sources[] = $definition->jsonSerialize();
        }

        return $this->json(['sources' => $sources]);
    }
}
