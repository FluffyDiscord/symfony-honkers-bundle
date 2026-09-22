<?php

declare(strict_types=1);

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\Honkers\Registry\ToolChoiceLoaderRegistry;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\Honkers\Schema\ArgumentsSchemaGenerator;
use FluffyDiscord\Honkers\Validator\ToolChoiceValidator;
use FluffyDiscord\HonkersBundle\Locale\DefaultLocaleContext;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_locator;

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

    $services->set(ToolChoiceLoaderRegistry::class)
        ->arg('$loaders', tagged_locator('fluffydiscord_chatbot.tool_choice_loader'));

    $services->set(ToolRegistry::class)
        ->arg('$tools', tagged_locator('fluffydiscord_chatbot.tool', 'definition_name'));

    $services->set(DataSourceRegistry::class)
        ->arg('$sources', tagged_locator('fluffydiscord_chatbot.source', 'definition_name'));

    $services->set(ToolChoiceValidator::class)
        ->tag('validator.constraint_validator');

    $services->alias(ChatbotLocaleContextInterface::class, DefaultLocaleContext::class);
};
