<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Http;

use FluffyDiscord\Honkers\DTO\ToolListHeaders;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Controller\ValueResolverInterface;
use Symfony\Component\HttpKernel\ControllerMetadata\ArgumentMetadata;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class ToolListHeadersValueResolver implements ValueResolverInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        $isSupported = $argument->getType() === ToolListHeaders::class;
        if (!$isSupported) {
            return [];
        }

        $headers = new ToolListHeaders($request->headers->get('Accept-Language'));
        $violations = $this->validator->validate($headers);
        $violationCount = count($violations);
        if ($violationCount > 0) {
            throw new BadRequestHttpException('Invalid Accept-Language header.');
        }

        return [$headers];
    }
}
