<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Validates chatbot tool/source definition names at compile time: the registries key services by
 * `getDefinition()->name`, so a duplicate would silently shadow another and a malformed name would
 * only surface at runtime. Fail the build instead.
 */
class ChatbotDefinitionNamePass implements CompilerPassInterface
{
    private const NAME_PATTERN = '/^[a-z0-9_]{1,64}$/';

    public function process(ContainerBuilder $container): void
    {
        $this->validateTaggedServices($container, 'fluffydiscord_chatbot.tool');
        $this->validateTaggedServices($container, 'fluffydiscord_chatbot.source');
    }

    private function validateTaggedServices(ContainerBuilder $container, string $tag): void
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
        }
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
