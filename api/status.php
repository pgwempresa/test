<?php

require_once 'utils.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$creds = get_waymb_creds();
$origin = get_request_origin();

json_response([
    'ok' => true,
    'php_runtime' => true,
    'origin' => $origin,
    'kv' => [
        'configured' => !empty($kvUrl) && !empty($kvToken),
        'url_present' => !empty($kvUrl),
        'token_present' => !empty($kvToken)
    ],
    'waymb' => [
        'configured' => !empty($creds['client_id']) && !empty($creds['client_secret']),
        'client_id_present' => !empty($creds['client_id']),
        'client_secret_present' => !empty($creds['client_secret']),
        'account_email' => $creds['account_email'] ?: null
    ],
    'utmify' => [
        'configured' => get_utmify_token() !== '',
        'token_present' => get_utmify_token() !== '',
        'is_test' => env_first(['UTMIFY_IS_TEST'], 'false') === 'true',
        'last' => kv_get_json('utmify:last')
    ],
    'routes' => [
        'create' => '/api/create-mbway.php',
        'check' => '/api/check-mbway.php',
        'webhook' => '/api/waymb-webhook.php',
        'checkout_page' => '/checkout'
    ]
]);
