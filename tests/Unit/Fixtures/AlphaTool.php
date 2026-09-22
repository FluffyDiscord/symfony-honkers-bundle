<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures;

use FluffyDiscord\Honkers\Contract\ChatbotToolInterface;
use FluffyDiscord\Honkers\DTO\ToolCallContext;
use FluffyDiscord\Honkers\DTO\ToolDefinition;
use FluffyDiscord\Honkers\DTO\ToolResult;

class AlphaTool implements ChatbotToolInterface
{
    public function getDefinition(): ToolDefinition
    {
        return new ToolDefinition('alpha_tool', 'fixture.alpha_tool');
    }

    public function getArgumentsClass(): string
    {
        return NullableArguments::class;
    }

    public function execute(object $arguments, ToolCallContext $context): ToolResult
    {
        return new ToolResult([]);
    }
}
