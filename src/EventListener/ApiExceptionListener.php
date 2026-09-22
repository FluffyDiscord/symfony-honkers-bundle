<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\EventListener;

use FluffyDiscord\Honkers\DTO\Violation;
use FluffyDiscord\Honkers\Enum\ApiErrorCode;
use FluffyDiscord\Honkers\Exception\ChatbotApiException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\Validator\Exception\ValidationFailedException;
use Symfony\Contracts\Translation\TranslatorInterface;

#[AsEventListener(event: 'kernel.exception', priority: 0)]
class ApiExceptionListener
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function __invoke(ExceptionEvent $event): void
    {
        $isChatbotRequest = str_starts_with($event->getRequest()->getPathInfo(), $this->getPathPrefix());
        if (!$isChatbotRequest) {
            return;
        }

        $throwable = $event->getThrowable();

        if ($throwable instanceof ChatbotApiException) {
            $this->respond($event, $throwable->getStatusCode(), $throwable->getErrorCode(), $throwable->getViolations());

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $statusCode = $throwable->getStatusCode();
            $violations = $this->extractValidationViolations($throwable);
            $errorCode = $this->mapStatusCode($statusCode);
            if ($statusCode === Response::HTTP_BAD_REQUEST && $violations !== []) {
                $errorCode = $this->mapQueryViolations($violations);
            }
            $this->respond($event, $statusCode, $errorCode, $violations);

            return;
        }

        $this->respond($event, Response::HTTP_INTERNAL_SERVER_ERROR, ApiErrorCode::InternalError, []);
    }

    private function getPathPrefix(): string
    {
        return '/chatbot/v1';
    }

    private function mapQueryViolations(array $violations): ApiErrorCode
    {
        foreach ($violations as $violation) {
            if ($violation->path === 'locale') {
                return ApiErrorCode::InvalidLocale;
            }
        }

        foreach ($violations as $violation) {
            if ($violation->path === 'cursor') {
                return ApiErrorCode::InvalidCursor;
            }
        }

        return ApiErrorCode::BadRequest;
    }

    private function extractValidationViolations(\Throwable $throwable): array
    {
        $previous = $throwable->getPrevious();
        while ($previous !== null) {
            if ($previous instanceof ValidationFailedException) {
                $violations = [];
                foreach ($previous->getViolations() as $violation) {
                    $violations[] = new Violation($violation->getPropertyPath(), (string) $violation->getMessage());
                }

                return $violations;
            }
            $previous = $previous->getPrevious();
        }

        return [];
    }

    private function mapStatusCode(int $statusCode): ApiErrorCode
    {
        return match ($statusCode) {
            Response::HTTP_BAD_REQUEST => ApiErrorCode::BadRequest,
            Response::HTTP_UNAUTHORIZED => ApiErrorCode::Unauthorized,
            Response::HTTP_NOT_FOUND => ApiErrorCode::NotFound,
            Response::HTTP_METHOD_NOT_ALLOWED => ApiErrorCode::MethodNotAllowed,
            Response::HTTP_UNPROCESSABLE_ENTITY => ApiErrorCode::ValidationFailed,
            default => ApiErrorCode::InternalError,
        };
    }

    private function respond(ExceptionEvent $event, int $statusCode, ApiErrorCode $errorCode, array $violations): void
    {
        $includedViolations = [];
        if ($statusCode === Response::HTTP_UNPROCESSABLE_ENTITY) {
            $includedViolations = array_map(
                fn (Violation $violation): array => $violation->jsonSerialize(),
                $violations,
            );
        }

        $event->setResponse(new JsonResponse(
            [
                'error' => [
                    'code' => $errorCode->value,
                    'message' => $this->translator->trans($errorCode->getTranslationKey()),
                    'violations' => $includedViolations,
                ],
            ],
            $statusCode,
        ));
        $event->stopPropagation();
    }
}
