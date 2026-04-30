<?php

function env_first(array $keys, $default = '') {
    foreach ($keys as $key) {
        $value = getenv($key);

        if ($value !== false && $value !== '') {
            return $value;
        }
    }

    return $default;
}

$kvUrl = env_first([
    'KV_REST_API_URL',
    'UPSTASH_REDIS_REST_URL',
    'UPSTASH_KV_REST_API_URL'
]);

$kvToken = env_first([
    'KV_REST_API_TOKEN',
    'UPSTASH_REDIS_REST_TOKEN',
    'UPSTASH_KV_REST_API_TOKEN'
]);

function get_waymb_creds() {
    global $kvUrl, $kvToken;

    $creds = [
        'client_id' => env_first(['WAYMB_CLIENT_ID'], 'hiago_b9244e48'),
        'client_secret' => env_first(['WAYMB_CLIENT_SECRET'], ''),
        'account_email' => env_first(['WAYMB_ACCOUNT_EMAIL'], '')
    ];

    if ($kvUrl && $kvToken) {
        $stored = kv_get_json('waymb_credentials');

        if (is_array($stored)) {
            if (!empty($stored['client_id'])) {
                $creds['client_id'] = $stored['client_id'];
            }

            if (!empty($stored['client_secret'])) {
                $creds['client_secret'] = $stored['client_secret'];
            }

            if (!empty($stored['account_email'])) {
                $creds['account_email'] = $stored['account_email'];
            }
        }
    }

    if ($creds['account_email'] === '' && $creds['client_id'] !== '') {
        $creds['account_email'] = $creds['client_id'] . '@waymb.com';
    }

    return $creds;
}

function read_json_input() {
    $input = file_get_contents('php://input');
    $data = json_decode($input ?: '', true);

    return is_array($data) ? $data : [];
}

function json_response($payload, $status = 200) {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

function get_request_origin() {
    $proto = 'https';

    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $proto = explode(',', $_SERVER['HTTP_X_FORWARDED_PROTO'])[0];
    } elseif (!empty($_SERVER['REQUEST_SCHEME'])) {
        $proto = $_SERVER['REQUEST_SCHEME'];
    } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        $proto = 'https';
    }

    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? ($_SERVER['HTTP_HOST'] ?? '');

    return $host ? ($proto . '://' . $host) : '';
}

function normalize_waymb_status($status) {
    $normalized = strtoupper(trim((string) $status));

    if ($normalized === 'PAID' || $normalized === 'APPROVED') {
        return 'COMPLETED';
    }

    if ($normalized === '') {
        return 'PENDING';
    }

    return $normalized;
}

function normalize_waymb_create_payload(array $data) {
    $creds = get_waymb_creds();
    $origin = get_request_origin();

    $payer = isset($data['payer']) && is_array($data['payer']) ? $data['payer'] : [];
    $amount = isset($data['amount']) ? (float) $data['amount'] : 12.97;
    $description = isset($data['paymentDescription']) ? (string) $data['paymentDescription'] : 'Transaction Payment';

    $data['client_id'] = $creds['client_id'];
    $data['client_secret'] = $creds['client_secret'];
    $data['account_email'] = $data['account_email'] ?? ($creds['account_email'] ?: 'hiago_b9244e48@waymb.com');
    $data['amount'] = $amount;
    $data['method'] = strtolower((string) ($data['method'] ?? 'mbway'));
    $data['currency'] = $data['currency'] ?? 'EUR';
    $data['paymentDescription'] = function_exists('mb_substr')
        ? mb_substr($description, 0, 50)
        : substr($description, 0, 50);

    $payer['email'] = $payer['email'] ?? ('user' . time() . '@example.com');
    $payer['name'] = $payer['name'] ?? 'Cliente';
    $payer['document'] = preg_replace('/\D+/', '', (string) ($payer['document'] ?? '999999999'));
    $payer['phone'] = preg_replace('/\D+/', '', (string) ($payer['phone'] ?? '912345678'));
    $data['payer'] = $payer;
    $data['trackingParameters'] = normalize_tracking_parameters($data['trackingParameters'] ?? []);
    $data['pagePath'] = isset($data['pagePath']) ? (string) $data['pagePath'] : '';

    if (empty($data['callbackUrl']) && $origin) {
        $data['callbackUrl'] = $origin . '/api/waymb-webhook.php';
    }

    if (empty($data['success_url']) && $origin) {
        $data['success_url'] = $origin . '/up1/';
    }

    if (empty($data['failed_url']) && $origin) {
        $data['failed_url'] = $origin . '/back-redirect/';
    }

    return $data;
}

function waymb_request($path, array $payload, $timeout = 20) {
    $ch = curl_init('https://api.waymb.com' . $path);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: pt-main/1.0'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'ok' => $response !== false && $curlError === '',
        'status' => $httpCode ?: 502,
        'body' => $response,
        'error' => $curlError
    ];
}

function kv_get_json($key) {
    global $kvUrl, $kvToken;

    if (!$kvUrl || !$kvToken) {
        return null;
    }

    $ch = curl_init(rtrim($kvUrl, '/') . '/get/' . rawurlencode($key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $kvToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    curl_close($ch);

    if (!$response) {
        return null;
    }

    $decoded = json_decode($response, true);

    if (!isset($decoded['result']) || $decoded['result'] === null) {
        return null;
    }

    $result = json_decode($decoded['result'], true);

    return is_array($result) ? $result : null;
}

function kv_set_json($key, array $value) {
    global $kvUrl, $kvToken;

    if (!$kvUrl || !$kvToken) {
        return false;
    }

    $ch = curl_init(rtrim($kvUrl, '/') . '/set/' . rawurlencode($key));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $kvToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);

    $response = curl_exec($ch);
    curl_close($ch);

    return $response !== false;
}

function normalize_tracking_parameters($tracking) {
    $keys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'src', 'sck'];
    $result = [];

    if (!is_array($tracking)) {
        $tracking = [];
    }

    foreach ($keys as $key) {
        $value = $tracking[$key] ?? null;
        $result[$key] = $value === null || $value === '' ? null : (string) $value;
    }

    return $result;
}

function get_utmify_token() {
    $token = env_first(['UTMIFY_API_TOKEN', 'UTMIFY_TOKEN'], '');

    if ($token !== '') {
        return $token;
    }

    $stored = kv_get_json('utmify_credentials');

    return is_array($stored) && !empty($stored['api_token']) ? (string) $stored['api_token'] : '';
}

function get_transaction_id(array $payload) {
    return $payload['id'] ?? $payload['transactionID'] ?? $payload['transactionId'] ?? $payload['transaction_id'] ?? null;
}

function get_utmify_status(array $payload) {
    $status = normalize_waymb_status($payload['status'] ?? 'PENDING');

    if ($status === 'COMPLETED') {
        return 'paid';
    }

    if (in_array($status, ['DECLINED', 'CANCELED', 'CANCELLED', 'FAILED'], true)) {
        return 'refused';
    }

    return 'waiting_payment';
}

function get_utmify_product(array $payload) {
    $path = (string) ($payload['pagePath'] ?? '');
    $description = (string) ($payload['paymentDescription'] ?? 'Pagamento MB WAY');

    $map = [
        '/confirmar-saque' => ['front', 'Ticket inicial'],
        '/back-redirect' => ['back_redirect', 'Back redirect'],
        '/up1' => ['up1', 'Upsell 1'],
        '/upsell-1' => ['up1', 'Upsell 1'],
        '/up2' => ['up2', 'Upsell 2'],
        '/upsell-2' => ['up2', 'Upsell 2'],
        '/up3' => ['up3', 'Upsell 3'],
        '/upsell-3' => ['up3', 'Upsell 3'],
        '/up4' => ['up4', 'Upsell 4'],
        '/upsell-4' => ['up4', 'Upsell 4'],
        '/up5' => ['upsell-5', 'Upsell 5'],
        '/upsell-5' => ['upsell-5', 'Upsell 5']
    ];

    foreach ($map as $needle => $product) {
        if ($path !== '' && strpos($path, $needle) === 0) {
            return $product;
        }
    }

    $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $description));
    $slug = trim($slug ?: 'mbway', '-');

    return [$slug, $description ?: 'Pagamento MB WAY'];
}

function build_utmify_order_payload(array $payload) {
    $txId = get_transaction_id($payload);

    if (!$txId) {
        return null;
    }

    $payer = isset($payload['payer']) && is_array($payload['payer']) ? $payload['payer'] : [];
    $amount = isset($payload['amount']) ? (float) $payload['amount'] : 0.0;
    $priceInCents = max(0, (int) round($amount * 100));
    [$productId, $productName] = get_utmify_product($payload);
    $status = get_utmify_status($payload);
    $now = gmdate('c', time() - 300);
    $createdAt = (string) ($payload['createdAt'] ?? $payload['created_at'] ?? $now);

    if (strtotime($createdAt) === false || strtotime($createdAt) > time() - 60) {
        $createdAt = $now;
    }

    return [
        'isTest' => env_first(['UTMIFY_IS_TEST'], 'false') === 'true',
        'status' => $status,
        'orderId' => (string) $txId,
        'customer' => [
            'name' => (string) ($payer['name'] ?? 'Cliente'),
            'email' => (string) ($payer['email'] ?? ''),
            'phone' => (string) ($payer['phone'] ?? ''),
            'country' => 'PT',
            'document' => preg_replace('/\D+/', '', (string) ($payer['document'] ?? ''))
        ],
        'platform' => 'WayMB',
        'products' => [[
            'id' => $productId,
            'name' => $productName,
            'planId' => $productId,
            'planName' => $productName,
            'quantity' => 1,
            'priceInCents' => $priceInCents
        ]],
        'createdAt' => $createdAt,
        'commission' => [
            'gatewayFeeInCents' => 0,
            'totalPriceInCents' => $priceInCents,
            'userCommissionInCents' => $priceInCents,
            'currency' => 'EUR'
        ],
        'refundedAt' => null,
        'approvedDate' => $status === 'paid' ? (string) ($payload['approvedDate'] ?? $payload['paidAt'] ?? $payload['paid_at'] ?? $now) : null,
        'paymentMethod' => 'unknown',
        'trackingParameters' => normalize_tracking_parameters($payload['trackingParameters'] ?? [])
    ];
}

function send_utmify_order(array $payload) {
    $order = build_utmify_order_payload($payload);

    if (!$order) {
        return [
            'attempted' => false,
            'accepted' => false,
            'reason' => 'missing_order_data'
        ];
    }

    $token = get_utmify_token();

    if ($token === '') {
        return [
            'attempted' => false,
            'accepted' => false,
            'reason' => 'missing_token',
            'orderId' => $order['orderId'],
            'statusName' => $order['status']
        ];
    }

    $dedupeKey = 'utmify:' . $order['orderId'] . ':' . $order['status'];

    $previous = kv_get_json($dedupeKey);

    if (is_array($previous) && !empty($previous['ok'])) {
        return [
            'attempted' => false,
            'accepted' => true,
            'deduped' => true,
            'orderId' => $order['orderId'],
            'statusName' => $order['status'],
            'httpStatus' => $previous['status'] ?? null
        ];
    }

    $ch = curl_init('https://api.utmify.com.br/api-credentials/orders');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($order, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'x-api-token: ' . $token
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 12);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    $ok = $response !== false && $curlError === '' && $httpCode >= 200 && $httpCode < 300;

    $summary = [
        'ok' => $ok,
        'status' => $httpCode ?: 0,
        'sent_at' => gmdate('c'),
        'response' => is_string($response) ? substr($response, 0, 500) : '',
        'error' => $curlError
    ];

    kv_set_json($dedupeKey, $summary);
    kv_set_json('utmify:last', [
        'orderId' => $order['orderId'],
        'statusName' => $order['status'],
        'product' => $order['products'][0]['id'] ?? null,
        'amountInCents' => $order['commission']['totalPriceInCents'] ?? null,
        'ok' => $ok,
        'httpStatus' => $httpCode ?: 0,
        'sent_at' => $summary['sent_at'],
        'response' => $summary['response'],
        'error' => $curlError
    ]);

    return [
        'attempted' => true,
        'accepted' => $ok,
        'deduped' => false,
        'orderId' => $order['orderId'],
        'statusName' => $order['status'],
        'httpStatus' => $httpCode ?: 0,
        'response' => $summary['response'],
        'error' => $curlError
    ];
}

function persist_transaction_snapshot(array $payload) {
    $txId = get_transaction_id($payload);

    if (!$txId) {
        return;
    }

    if (isset($payload['status'])) {
        $payload['status'] = normalize_waymb_status($payload['status']);
    }

    kv_set_json('tx:' . $txId, $payload);
}
