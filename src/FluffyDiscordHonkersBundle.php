<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle;

use FluffyDiscord\Honkers\Contract\ChatbotDataSourceInterface;
use FluffyDiscord\Honkers\Contract\ChatbotToolInterface;
use FluffyDiscord\Honkers\Contract\ToolChoiceLoaderInterface;
use FluffyDiscord\HonkersBundle\DependencyInjection\Compiler\ChatbotDefinitionNamePass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

class FluffyDiscordHonkersBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);
        $container->addCompilerPass(new ChatbotDefinitionNamePass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->scalarNode('api_secret')->isRequired()->cannotBeEmpty()->end()
            ->end();
    }

    public function loadExtension(array $config, ContainerConfigurator $configurator, ContainerBuilder $container): void
    {
        $container->registerForAutoconfiguration(ChatbotToolInterface::class)
            ->addTag('fluffydiscord_chatbot.tool');
        $container->registerForAutoconfiguration(ChatbotDataSourceInterface::class)
            ->addTag('fluffydiscord_chatbot.source');
        $container->registerForAutoconfiguration(ToolChoiceLoaderInterface::class)
            ->addTag('fluffydiscord_chatbot.tool_choice_loader');

        $configurator->parameters()
            ->set('fluffydiscord_honkers.api_secret', $config['api_secret']);

        $configurator->import(__DIR__ . '/../config/services.php');
    }
}
