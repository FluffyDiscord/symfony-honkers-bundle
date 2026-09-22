<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\DTO\SourceQuery;
use FluffyDiscord\Honkers\Exception\SourceNotFoundException;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryString;

class ReadSourceController extends AbstractController
{
    public function __construct(
        private readonly DataSourceRegistry $dataSourceRegistry,
        private readonly ChatbotLocaleContextInterface $localeContext,
        private readonly LocaleMatcher $localeMatcher,
    ) {
    }

    public function __invoke(
        string $name,
        #[MapQueryString(validationFailedStatusCode: Response::HTTP_BAD_REQUEST)] SourceQuery $query,
    ): JsonResponse {
        $source = $this->dataSourceRegistry->get($name);
        if ($source === null) {
            throw new SourceNotFoundException($name);
        }

        $this->localeContext->applyChannel($query->channel);

        $servedLocales = $source->getDefinition()->locales ?? $this->localeContext->getChannelLocales();
        $servedLocale = $this->localeMatcher->resolveServedLocaleOrFail($query->locale, $servedLocales);

        $page = $source->getDocuments($query->withLocale($servedLocale));

        return $this->json($page->jsonSerialize());
    }
}
