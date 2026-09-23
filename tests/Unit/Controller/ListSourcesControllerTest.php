<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\HonkersBundle\Controller\ListSourcesController;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\PlainDataSource;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListSourcesControllerTest extends TestCase
{
    public function testASourceIsAdvertisedForEveryChannelLocaleNotOnlyTheResolvedOne(): void
    {
        $controller = $this->createController([new PlainDataSource()]);

        $sources = $this->readSources($controller);

        self::assertSame(['cs_CZ', 'de_AT'], $sources[0]['locales']);
    }

    public function testASourceKeepsTheLocalesItDeclaresForItself(): void
    {
        $controller = $this->createController([new PlainDataSource('narrow', ['hu_HU'])]);

        $sources = $this->readSources($controller);

        self::assertSame(['hu_HU'], $sources[0]['locales']);
    }

    /**
     * @return list<array{name: string, description: string, locales: list<string>}>
     */
    private function readSources(ListSourcesController $controller): array
    {
        $response = $controller->__invoke();
        $decoded = json_decode((string) $response->getContent(), true);

        return $decoded['sources'];
    }

    /**
     * @param list<PlainDataSource> $sources
     */
    private function createController(array $sources): ListSourcesController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('described');

        $localeContext = $this->createStub(ChatbotLocaleContextInterface::class);
        $localeContext->method('getCurrentLocale')->willReturn('cs_CZ');
        $localeContext->method('getChannelLocales')->willReturn(['cs_CZ']);
        $localeContext->method('getAllChannelLocales')->willReturn(['cs_CZ', 'de_AT']);

        $controller = new ListSourcesController(
            new DataSourceRegistry($sources),
            $translator,
            $localeContext,
        );
        $controller->setContainer(new Container());

        return $controller;
    }
}
