<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Controller;

use FluffyDiscord\Honkers\Exception\PairingException;
use FluffyDiscord\Honkers\Pairing\HostMatcher;
use FluffyDiscord\Honkers\Pairing\PairingClient;
use FluffyDiscord\Honkers\Pairing\PairingResult;
use FluffyDiscord\HonkersBundle\Contract\CredentialsWriterInterface;
use FluffyDiscord\HonkersBundle\Pairing\PairedCredentials;
use FluffyDiscord\HonkersBundle\Pairing\PairingErrorCode;
use FluffyDiscord\HonkersBundle\Reporting\BackendReportGuard;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Contracts\Translation\TranslatorInterface;

class PairController extends AbstractController
{
    private const CODE_PATTERN = '/^[A-Za-z0-9_-]{43}$/';

    private const SECRET_BYTES = 32;

    public function __construct(
        private readonly PairingClient               $pairingClient,
        private readonly BackendReportGuard          $backendReportGuard,
        private readonly TranslatorInterface         $translator,
        private readonly HostMatcher                 $hostMatcher,

        #[Autowire(param: 'fluffydiscord_honkers.backend_url')]
        private readonly string                      $backendUrl,
        private readonly ?CredentialsWriterInterface $credentialsWriter = null,
    ) {
    }

    public function __invoke(Request $request, #[MapQueryParameter] string $code = ''): Response
    {
        if ($this->credentialsWriter === null) {
            $message = $this->translator->trans('fluffydiscord_honkers.pairing.environment_credentials');

            return $this->renderPage($request, $message, null, Response::HTTP_CONFLICT);
        }

        try {
            $result = $this->pair($request, $code, $this->credentialsWriter);
        } catch (PairingException $exception) {
            return $this->renderError($request, $exception->getErrorCode());
        }

        return $this->redirect($result->returnUrl);
    }

    /**
     * @throws PairingException
     */
    private function pair(Request $request, string $code, CredentialsWriterInterface $credentialsWriter): PairingResult
    {
        $isValidCode = preg_match(self::CODE_PATTERN, $code) === 1;
        if (!$isValidCode) {
            throw new PairingException('The pairing code is malformed.', PairingErrorCode::InvalidCode->value);
        }

        $canReport = $this->backendReportGuard->canReport('pairing');
        if (!$canReport) {
            throw new PairingException('The backend URL is missing or not https.', PairingErrorCode::BackendUrlRefused->value);
        }

        $trustedHost = $credentialsWriter->findTrustedHost($request);
        if ($trustedHost === null) {
            throw new PairingException('This shop has no trusted hostname.', PairingErrorCode::HostnameMissing->value);
        }

        $apiSecret = $this->generateSecret();
        $ingestSecret = $this->generateSecret();
        $result = $this->pairingClient->complete($code, $apiSecret, $ingestSecret);

        $isSameShop = $this->hostMatcher->isSameHost($result->baseUrl, $trustedHost);
        if (!$isSameShop) {
            throw new PairingException('The pairing belongs to another shop.', PairingErrorCode::WrongShop->value);
        }

        $isBackendReturnUrl = $this->isBackendOrigin($result->returnUrl);
        if (!$isBackendReturnUrl) {
            throw new PairingException('The return URL is not on the backend.', PairingErrorCode::InvalidResponse->value);
        }

        $credentials = new PairedCredentials(
            $result->siteKey,
            $apiSecret,
            $ingestSecret,
            $result->channelCodes,
            $result->verifiedDomains,
        );
        $credentialsWriter->save($credentials, $request);

        return $result;
    }

    private function generateSecret(): string
    {
        return bin2hex(random_bytes(self::SECRET_BYTES));
    }

    private function isBackendOrigin(string $url): bool
    {
        $origin = $this->getOrigin($url);
        $backendOrigin = $this->getOrigin($this->backendUrl);

        if ($origin === null) {
            return false;
        }

        return $origin === $backendOrigin;
    }

    private function getOrigin(string $url): ?string
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (!is_string($scheme)) {
            return null;
        }

        $host = $this->hostMatcher->normalize($url);
        if ($host === null) {
            return null;
        }

        $port = parse_url($url, PHP_URL_PORT);
        $portSuffix = is_int($port) ? ':' . $port : '';

        return strtolower($scheme) . '://' . $host . $portSuffix;
    }

    private function renderError(Request $request, string $errorCode): Response
    {
        $knownErrorCode = PairingErrorCode::tryFrom($errorCode) ?? PairingErrorCode::Unknown;
        $message = $this->translator->trans('fluffydiscord_honkers.pairing.error.' . $knownErrorCode->value);

        return $this->renderPage($request, $message, $errorCode, Response::HTTP_BAD_REQUEST);
    }

    private function renderPage(Request $request, string $message, ?string $errorCode, int $statusCode): Response
    {
        $title = $this->translator->trans('fluffydiscord_honkers.pairing.title');
        $errorCodeLine = $errorCode === null ? '' : '<p><code>' . $this->escape($errorCode) . '</code></p>';

        $html = sprintf(
            '<!DOCTYPE html><html lang="%s"><head><meta charset="utf-8"><meta name="robots" content="noindex">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1"><title>%s</title></head>'
            . '<body style="font-family: system-ui, sans-serif; max-width: 36rem; margin: 4rem auto; padding: 0 1rem;">'
            . '<h1>%s</h1><p>%s</p>%s</body></html>',
            $this->escape($request->getLocale()),
            $this->escape($title),
            $this->escape($title),
            $this->escape($message),
            $errorCodeLine,
        );

        return new Response($html, $statusCode, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
