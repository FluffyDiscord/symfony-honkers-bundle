<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\DTO\ToolListHeaders;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use FluffyDiscord\Honkers\Registry\ToolChoiceLoaderRegistry;
use FluffyDiscord\HonkersBundle\Controller\ListToolsController;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Contracts\Translation\TranslatorInterface;

class ListToolsControllerTest extends TestCase
{
    public function testALocaleServedByAnotherChannelIsTranslatedIntoItself(): void
    {
        $translator = $this->createTranslatorRecordingTheLocale($recorded);

        $this->listTools($translator, 'de_AT');

        self::assertSame('de_AT', $recorded);
    }

    public function testALocaleNoChannelServesFallsBackToTheContextLocale(): void
    {
        $translator = $this->createTranslatorRecordingTheLocale($recorded);

        $this->listTools($translator, 'ja_JP');

        self::assertSame('cs_CZ', $recorded);
    }

    private function createTranslatorRecordingTheLocale(?string &$recorded): TranslatorInterface
    {
        $recorded = null;
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            static function (string $id, array $parameters, ?string $domain, ?string $locale) use (&$recorded): string {
                $recorded = $locale;

                return $id;
            },
        );

        return $translator;
    }

    private function listTools(TranslatorInterface $translator, string $requestedLocale): void
    {
        $localeContext = $this->createStub(ChatbotLocaleContextInterface::class);
        $localeContext->method('getCurrentLocale')->willReturn('cs_CZ');
        $localeContext->method('getChannelLocales')->willReturn(['cs_CZ']);
        $localeContext->method('getAllChannelLocales')->willReturn(['cs_CZ', 'de_AT']);

        $controller = new ListToolsController(
            new ToolRegistry([new AlphaTool()]),
            new ArgumentsSchemaGenerator(new ToolChoiceLoaderRegistry([])),
            $translator,
            $localeContext,
            new LocaleMatcher(),
        );
        $controller->setContainer(new Container());

        $controller->__invoke(new ToolListHeaders($requestedLocale));
    }
}
