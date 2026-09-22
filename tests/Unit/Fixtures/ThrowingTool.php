<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use FluffyDiscord\Honkers\Contract\ChatbotToolInterface;
use FluffyDiscord\Honkers\DTO\ToolCallContext;
use FluffyDiscord\Honkers\DTO\ToolDefinition;
use FluffyDiscord\Honkers\DTO\ToolResult;

class ThrowingTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('throwing_tool', 'fixture.throwing_tool');
    }

    public function getArgumentsClass(): string
    {
        return NullableArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        throw new \RuntimeException('boom');
    }
}
