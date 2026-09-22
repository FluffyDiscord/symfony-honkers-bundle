<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Controller;

use FluffyDiscord\Honkers\Contract\ChatbotLocaleContextInterface;
use FluffyDiscord\Honkers\DTO\ContentItem;
use FluffyDiscord\Honkers\DTO\ToolCallContext;
use FluffyDiscord\Honkers\DTO\ToolCallRequest;
use FluffyDiscord\Honkers\DTO\ToolResult;
use FluffyDiscord\Honkers\DTO\Violation;
use FluffyDiscord\Honkers\Exception\ArgumentsValidationException;
use FluffyDiscord\Honkers\Exception\ToolNotFoundException;
use FluffyDiscord\Honkers\Registry\ToolRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Serializer\Exception\ExceptionInterface as SerializerExceptionInterface;
use Symfony\Component\Serializer\Exception\ExtraAttributesException;
use Symfony\Component\Serializer\Exception\PartialDenormalizationException;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CallToolController extends AbstractController
{
    public function __construct(
        private readonly ToolRegistry                  $toolRegistry,
        private readonly DenormalizerInterface         $denormalizer,
        private readonly ValidatorInterface            $validator,
        private readonly TranslatorInterface           $translator,
        private readonly ChatbotLocaleContextInterface $localeContext,
        private readonly ?LoggerInterface              $logger = null,
    ) {
    }

    public function __invoke(string $name, #[MapRequestPayload] ToolCallRequest $payload): JsonResponse
    {
        $tool = $this->toolRegistry->get($name);
        if ($tool === null) {
            throw new ToolNotFoundException($name);
        }

        $this->localeContext->applyChannel($payload->context->channelCode);
        $context = $this->resolveContextLocale($payload->context);

        $arguments = $this->denormalizeArguments($payload->arguments, $tool->getArgumentsClass());

        $violations = [];
        foreach ($this->validator->validate($arguments) as $violation) {
            $violations[] = new Violation($violation->getPropertyPath(), (string) $violation->getMessage());
        }
        if ($violations !== []) {
            throw new ArgumentsValidationException($violations);
        }

        try {
            $result = $tool->execute($arguments, $context);
        } catch (\Throwable $exception) {
            $this->logger?->error('Chatbot tool execution failed.', [
                'tool' => $name,
                'exception' => $exception,
            ]);
            $result = new ToolResult(
                [new ContentItem($this->translator->trans(
                    'fluffydiscord_honkers.tool.failure',
                    [],
                    'messages',
                    $context->locale,
                ))],
                [],
                true,
            );
        }

        return $this->json($result->jsonSerialize());
    }

    private function resolveContextLocale(ToolCallContext $context): ToolCallContext
    {
        $servedLocale = $this->localeContext->resolveForChannel($context->locale);

        return $context->withLocale($servedLocale ?? $this->localeContext->getCurrentLocale());
    }

    private function denormalizeArguments(array $arguments, string $argumentsClass): object
    {
        try {
            return $this->denormalizer->denormalize($arguments, $argumentsClass, null, [
                AbstractNormalizer::COLLECT_DENORMALIZATION_ERRORS => true,
                AbstractNormalizer::ALLOW_EXTRA_ATTRIBUTES => false,
            ]);
        } catch (PartialDenormalizationException $exception) {
            $violations = [];
            foreach ($exception->getErrors() as $error) {
                $violations[] = new Violation((string) $error->getPath(), $error->getMessage());
            }

            throw new ArgumentsValidationException($violations);
        } catch (ExtraAttributesException $exception) {
            $violations = [];
            foreach ($exception->getExtraAttributes() as $extraAttribute) {
                $violations[] = new Violation((string) $extraAttribute, 'This argument is not expected.');
            }

            throw new ArgumentsValidationException($violations);
        } catch (SerializerExceptionInterface $exception) {
            throw new ArgumentsValidationException([new Violation('', $exception->getMessage())]);
        }
    }
}
