<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\DTO\ToolListHeaders;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
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
        private readonly LocaleMatcher $localeMatcher,
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

    /**
     * @invariant Matches across every channel, not the one the request resolved. A tool is the
     *            same code on all of them, so a backend driving several channels through one host
     *            must get its descriptions in the locale it asked for, not the host channel's.
     */
    private function resolveLocale(?string $requested): string
    {
        $contextLocale = $this->localeContext->getCurrentLocale();

        if ($requested === null) {
            return $contextLocale;
        }

        $servedLocales = $this->localeContext->getAllChannelLocales();
        $servedLocale = $this->localeMatcher->resolveServedLocale($requested, $servedLocales);

        return $servedLocale ?? $contextLocale;
    }
}
