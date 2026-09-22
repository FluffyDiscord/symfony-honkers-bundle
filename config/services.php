<?php

declare(strict_types=1);

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\Cursor\CursorCodec;
use FluffyDiscord\Honkers\Ingest\CatalogIngestClient;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\Honkers\Registry\ToolChoiceLoaderRegistry;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use FluffyDiscord\Honkers\Text\HtmlToText;
use FluffyDiscord\Honkers\Validator\ToolChoiceValidator;
use FluffyDiscord\Honkers\Widget\WidgetSnippet;
use FluffyDiscord\HonkersBundle\Locale\DefaultLocaleContext;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpClient\Psr18Client;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function Symfony\Component\DependencyInjection\Loader\Configurator\param;
use function Symfony\Component\DependencyInjection\Loader\Configurator\service;
use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

return function (ContainerConfigurator $configurator): void {
    $services = $configurator->services();

    $services->defaults()
        ->autowire()
        ->autoconfigure();

    $services->load('FluffyDiscord\\HonkersBundle\\', __DIR__ . '/../src/')
        ->exclude([
            __DIR__ . '/../src/DependencyInjection',
            __DIR__ . '/../src/Security/ChatbotBackendUser.php',
            __DIR__ . '/../src/FluffyDiscordHonkersBundle.php',
        ]);

    $services->set(LocaleMatcher::class);
    $services->set(ArgumentsSchemaGenerator::class);
    $services->set(CursorCodec::class);
    $services->set(HtmlToText::class);

    $services->set(ToolChoiceLoaderRegistry::class)
        ->arg('$loaders', tagged_iterator('fluffydiscord_chatbot.tool_choice_loader'));

    $services->set(ToolRegistry::class)
        ->arg('$tools', tagged_iterator('fluffydiscord_chatbot.tool'));

    $services->set(DataSourceRegistry::class)
        ->arg('$sources', tagged_iterator('fluffydiscord_chatbot.source'));

    $services->set(ToolChoiceValidator::class)
        ->tag('validator.constraint_validator');

    $services->set('fluffydiscord_honkers.ingest_http_client', HttpClientInterface::class)
        ->factory([HttpClient::class, 'create'])
        ->args([['timeout' => 2.0, 'max_duration' => 5.0]]);

    $services->set(Psr18Client::class)
        ->arg('$client', service('fluffydiscord_honkers.ingest_http_client'));

    $services->set(CatalogIngestClient::class)
        ->public()
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('fluffydiscord_honkers.backend_url'))
        ->arg('$ingestSecret', param('fluffydiscord_honkers.ingest_secret'));

    $services->set(WidgetSnippet::class)
        ->public();

    $services->alias(ChatbotLocaleContextInterface::class, DefaultLocaleContext::class);
};
