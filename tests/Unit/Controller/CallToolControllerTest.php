<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Tests\Unit\Controller;

use FluffyDiscord\Honkers\DTO\ToolCallContext;
use FluffyDiscord\Honkers\DTO\ToolCallRequest;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use FluffyDiscord\HonkersBundle\Controller\CallToolController;
use FluffyDiscord\HonkersBundle\Locale\DefaultLocaleContext;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\NullableArguments;
use FluffyDiscord\HonkersBundle\Tests\Unit\Fixtures\ThrowingTool;
use FluffyDiscord\Honkers\Locale\LocaleMatcher;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CallToolControllerTest extends TestCase
{
    public function testAFailingToolReturnsTheErrorResultEvenWithoutALogger(): void
    {
        $controller = $this->createController(null);

        $response = $controller->__invoke('throwing_tool', $this->createPayload());

        $decoded = json_decode((string) $response->getContent(), true);
        self::assertTrue($decoded['isError']);
        self::assertSame('tool failed', $decoded['content'][0]['text']);
    }

    public function testAFailingToolIsLoggedWhenALoggerIsPresent(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('Chatbot tool execution failed.', self::anything());
        $controller = $this->createController($logger);

        $controller->__invoke('throwing_tool', $this->createPayload());
    }

    private function createPayload(): ToolCallRequest
    {
        return new ToolCallRequest([], new ToolCallContext(
            '00000000-0000-4000-8000-000000000000',
            'en_US',
        ));
    }

    private function createController(?LoggerInterface $logger): CallToolController
    {
        $denormalizer = $this->createStub(DenormalizerInterface::class);
        $denormalizer->method('denormalize')->willReturn(new NullableArguments());

        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList());

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturn('tool failed');

        $controller = new CallToolController(
            new ToolRegistry([new ThrowingTool()]),
            $denormalizer,
            $validator,
            $translator,
            new DefaultLocaleContext(new LocaleMatcher(), 'en_US'),
            $logger,
        );
        $controller->setContainer(new Container());

        return $controller;
    }
}
