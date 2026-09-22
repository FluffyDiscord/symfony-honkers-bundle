<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\DependencyInjection;

use FluffyDiscord\HonkersBundle\DependencyInjection\Compiler\ChatbotDefinitionNamePass;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaDuplicateTool;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\AlphaTool;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\BadNameTool;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class ChatbotDefinitionNamePassTest extends TestCase
{
    public function testAcceptsValidUniqueNames(): void
    {
        $container = new ContainerBuilder();
        $container->register(AlphaTool::class, AlphaTool::class)->addTag('fluffydiscord_chatbot.tool');

        (new ChatbotDefinitionNamePass())->process($container);

        $this->expectNotToPerformAssertions();
    }

    public function testDuplicateNamesFailCompilation(): void
    {
        $container = new ContainerBuilder();
        $container->register(AlphaTool::class, AlphaTool::class)->addTag('fluffydiscord_chatbot.tool');
        $container->register(AlphaDuplicateTool::class, AlphaDuplicateTool::class)->addTag('fluffydiscord_chatbot.tool');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/Duplicate chatbot definition name "alpha_tool"/');

        (new ChatbotDefinitionNamePass())->process($container);
    }

    public function testInvalidNameFailsCompilation(): void
    {
        $container = new ContainerBuilder();
        $container->register(BadNameTool::class, BadNameTool::class)->addTag('fluffydiscord_chatbot.tool');

        $this->expectException(LogicException::class);

        (new ChatbotDefinitionNamePass())->process($container);
    }
}
