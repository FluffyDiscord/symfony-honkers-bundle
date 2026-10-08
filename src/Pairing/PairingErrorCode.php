<?php

declare(strict_types=1);

namespace FluffyDiscord\HonkersBundle\Pairing;

enum PairingErrorCode: string
{
    case InvalidCode = 'invalid_code';
    case BackendUrlRefused = 'backend_url_refused';
    case HostnameMissing = 'pairing_hostname_missing';
    case WrongShop = 'pairing_wrong_shop';
    case Unsupported = 'pairing_unsupported';
    case NotFound = 'pairing_not_found';
    case RateLimited = 'rate_limited';
    case ValidationFailed = 'validation_failed';
    case InvalidRequest = 'invalid_request';
    case TransportFailed = 'transport_failed';
    case InvalidResponse = 'invalid_response';
    case Unknown = 'unknown';
}
