<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\DependencyInjection\Compiler;

use FluffyDiscord\Honkers\Registry\DataSourceRegistry;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class ChatbotDefinitionNamePass implements CompilerPassInterface
{
    private const NAME_PATTERN = '/^[a-z0-9_]{1,64}$/';

    public function process(ContainerBuilder $container): void
    {
        $toolNames = $this->indexTaggedServices($container, 'fluffydiscord_chatbot.tool');
        $this->injectDefinitionNames($container, ToolRegistry::class, $toolNames);

        $sourceNames = $this->indexTaggedServices($container, 'fluffydiscord_chatbot.source');
        $this->injectDefinitionNames($container, DataSourceRegistry::class, $sourceNames);
    }

    /**
     * @return list<string>
     */
    private function indexTaggedServices(ContainerBuilder $container, string $tag): array
    {
        $serviceIdsByName = [];
        foreach (array_keys($container->findTaggedServiceIds($tag)) as $serviceId) {
            $definition = $container->getDefinition($serviceId);
            $class = $container->getParameterBag()->resolveValue($definition->getClass()) ?? $serviceId;
            $name = $this->resolveDefinitionName((string) $class, $tag);

            if (isset($serviceIdsByName[$name])) {
                throw new LogicException(sprintf(
                    'Duplicate chatbot definition name "%s" for tag "%s": services "%s" and "%s".',
                    $name,
                    $tag,
                    $serviceIdsByName[$name],
                    $serviceId,
                ));
            }
            $serviceIdsByName[$name] = $serviceId;

            $definition->clearTag($tag);
            $definition->addTag($tag, ['definition_name' => $name]);
        }

        return array_keys($serviceIdsByName);
    }

    /**
     * @param list<string> $names
     */
    private function injectDefinitionNames(ContainerBuilder $container, string $registryId, array $names): void
    {
        $hasRegistry = $container->hasDefinition($registryId);
        if (!$hasRegistry) {
            return;
        }

        $container->getDefinition($registryId)->setArgument('$names', $names);
    }

    private function resolveDefinitionName(string $class, string $tag): string
    {
        try {
            $instance = (new \ReflectionClass($class))->newInstanceWithoutConstructor();
            $name = $instance->getDefinition()->name;
        } catch (\Throwable $exception) {
            throw new LogicException(sprintf(
                'Cannot read the definition name of "%s" (tag "%s"): getDefinition() must not depend on constructor arguments. %s',
                $class,
                $tag,
                $exception->getMessage(),
            ), 0, $exception);
        }

        $isValidName = preg_match(self::NAME_PATTERN, $name) === 1;
        if (!$isValidName) {
            throw new LogicException(sprintf(
                'Chatbot definition name "%s" of "%s" must match %s.',
                $name,
                $class,
                self::NAME_PATTERN,
            ));
        }

        return $name;
    }
}
