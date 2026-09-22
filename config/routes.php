<?php

declare(strict_types=1);

use FluffyDiscord\HonkersBundle\Controller\CallToolController;
use FluffyDiscord\HonkersBundle\Controller\ListSourcesController;
use FluffyDiscord\HonkersBundle\Controller\ListToolsController;
use FluffyDiscord\HonkersBundle\Controller\ReadSourceController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;

return function (RoutingConfigurator $routes): void {
    $routes->add('fluffydiscord_chatbot_list_tools', '/chatbot/v1/tools')
        ->controller(ListToolsController::class)
        ->methods([Request::METHOD_GET]);

    $routes->add('fluffydiscord_chatbot_call_tool', '/chatbot/v1/tools/{name}')
        ->controller(CallToolController::class)
        ->methods([Request::METHOD_POST])
        ->requirements(['name' => '[a-z0-9_]+']);

    $routes->add('fluffydiscord_chatbot_list_sources', '/chatbot/v1/sources')
        ->controller(ListSourcesController::class)
        ->methods([Request::METHOD_GET]);

    $routes->add('fluffydiscord_chatbot_read_source', '/chatbot/v1/sources/{name}')
        ->controller(ReadSourceController::class)
        ->methods([Request::METHOD_GET])
        ->requirements(['name' => '[a-z0-9_]+']);
};
