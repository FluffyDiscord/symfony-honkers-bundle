<?php

declare(strict_types=1);

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\Cursor\CursorCodec;
use FluffyDiscord\Honkers\Ingest\CatalogIngestClient;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use FluffyDiscord\Honkers\Pairing\HostMatcher;
use FluffyDiscord\Honkers\Pairing\PairingClient;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\Honkers\Registry\ToolChoiceLoaderRegistry;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use FluffyDiscord\Honkers\Telemetry\ClickId;
use FluffyDiscord\Honkers\Telemetry\TelemetryClient;
use FluffyDiscord\Honkers\Text\HtmlToText;
use FluffyDiscord\Honkers\Validator\ToolChoiceValidator;
use FluffyDiscord\Honkers\Widget\WidgetSnippet;
use FluffyDiscord\HonkersBundle\Contract\CredentialsProviderInterface;
use FluffyDiscord\HonkersBundle\Credentials\EnvCredentialsProvider;
use FluffyDiscord\HonkersBundle\Locale\DefaultLocaleContext;
use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
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
        ->args([['timeout' => 2.0, 'max_duration' => 5.0, 'max_redirects' => 0]]);

    $services->set(Psr18Client::class)
        ->arg('$client', service('fluffydiscord_honkers.ingest_http_client'));

    $services->set(CatalogIngestClient::class)
        ->public()
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('fluffydiscord_honkers.backend_url'));

    $services->set(TelemetryClient::class)
        ->public()
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('fluffydiscord_honkers.backend_url'));

    $services->set(PairingClient::class)
        ->arg('$httpClient', service(Psr18Client::class))
        ->arg('$requestFactory', service(Psr18Client::class))
        ->arg('$streamFactory', service(Psr18Client::class))
        ->arg('$backendUrl', param('fluffydiscord_honkers.backend_url'));

    $services->set(HostMatcher::class);

    $services->set(ClickId::class);

    $services->set(BackendReportGuard::class)
        ->public();

    $services->set(WidgetSnippet::class)
        ->public();

    $services->alias(ChatbotLocaleContextInterface::class, DefaultLocaleContext::class);
    $services->alias(CredentialsProviderInterface::class, EnvCredentialsProvider::class);
};
