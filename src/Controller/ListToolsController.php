<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\DTO\ToolListHeaders;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListToolsController extends AbstractController
{
    public function __construct(
        private readonly ToolRegistry $toolRegistry,
        private readonly ArgumentsSchemaGenerator $schemaGenerator,
        private readonly TranslatorInterface $translator,
        private readonly ChatbotLocaleContextInterface $localeContext,
    ) {
    }

    public function __invoke(ToolListHeaders $headers): JsonResponse
    {
        $locale = $this->resolveLocale($headers->locale);

        $tools = [];
        foreach ($this->toolRegistry->all() as $tool) {
            $definition = $tool->getDefinition()
                ->withInputSchema($this->schemaGenerator->generate($tool->getArgumentsClass()))
                ->translated($this->translator, $locale);
            $tools[] = $definition->jsonSerialize();
        }

        return $this->json(['tools' => $tools]);
    }

    private function resolveLocale(?string $requested): string
    {
        $contextLocale = $this->localeContext->getCurrentLocale();

        if ($requested === null) {
            return $contextLocale;
        }

        $servedLocale = $this->localeContext->resolveForChannel($requested);

        return $servedLocale ?? $contextLocale;
    }
}
