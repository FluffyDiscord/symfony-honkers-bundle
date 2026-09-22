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
                ->scalarNode('backend_url')->defaultValue('')->end()
                ->scalarNode('ingest_secret')->defaultValue('')->end()
                ->arrayNode('widget')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultTrue()->end()
                        ->scalarNode('site_key')->defaultValue('')->end()
                        ->scalarNode('cdn_url')->defaultValue('')->end()
                    ->end()
                ->end()
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
            ->set('fluffydiscord_honkers.api_secret', $config['api_secret'])
            ->set('fluffydiscord_honkers.backend_url', $config['backend_url'])
            ->set('fluffydiscord_honkers.ingest_secret', $config['ingest_secret'])
            ->set('fluffydiscord_honkers.widget.enabled', $config['widget']['enabled'])
            ->set('fluffydiscord_honkers.widget.site_key', $config['widget']['site_key'])
            ->set('fluffydiscord_honkers.widget.cdn_url', $config['widget']['cdn_url']);

        $configurator->import(__DIR__ . '/../config/services.php');
    }
}
