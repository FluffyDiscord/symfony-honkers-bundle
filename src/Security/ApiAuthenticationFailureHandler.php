<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Security;

use FluffyDiscord\Honkers\Enum\ApiErrorCode;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\EntryPoint\AuthenticationEntryPointInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ApiAuthenticationFailureHandler implements AuthenticationFailureHandlerInterface, AuthenticationEntryPointInterface
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        return $this->createUnauthorizedResponse();
    }

    public function start(Request $request, ?AuthenticationException $authException = null): Response
    {
        return $this->createUnauthorizedResponse();
    }

    private function createUnauthorizedResponse(): JsonResponse
    {
        $errorCode = ApiErrorCode::Unauthorized;

        return new JsonResponse(
            [
                'error' => [
                    'code' => $errorCode->value,
                    'message' => $this->translator->trans($errorCode->getTranslationKey()),
                    'violations' => [],
                ],
            ],
            Response::HTTP_UNAUTHORIZED,
        );
    }
}
