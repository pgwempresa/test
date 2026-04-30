<?php

$kvUrl = getenv('KV_REST_API_URL');
$kvToken = getenv('KV_REST_API_TOKEN');

function get_waymb_creds() {
    global $kvUrl, $kvToken;

    $creds = [
        'client_id' => getenv('WAYMB_CLIENT_ID') ?: '',
        'client_secret' => getenv('WAYMB_CLIENT_SECRET') ?: ''
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
        }
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
    $data['account_email'] = $data['account_email'] ?? (getenv('WAYMB_ACCOUNT_EMAIL') ?: 'hiago_b9244e48@waymb.com');
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

    if (empty($data['callbackUrl']) && $origin) {
        $data['callbackUrl'] = $origin . '/api/waymb-webhook.php';
    }

    if (empty($data['success_url']) && $origin) {
        $data['success_url'] = $origin . '/upsell-1/';
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

function persist_transaction_snapshot(array $payload) {
    $txId = $payload['id'] ?? $payload['transactionID'] ?? $payload['transactionId'] ?? null;

    if (!$txId) {
        return;
    }

    if (isset($payload['status'])) {
        $payload['status'] = normalize_waymb_status($payload['status']);
    }

    kv_set_json('tx:' . $txId, $payload);
}
