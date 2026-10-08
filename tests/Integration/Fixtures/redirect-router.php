<?php

declare(strict_types=1);

$isRedirectTarget = $_SERVER['REQUEST_URI'] === '/followed';

if ($isRedirectTarget) {
    echo 'followed';

    return;
}

http_response_code(307);
header('Location: /followed');
