<?php

require_once 'utils.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    readfile(__DIR__ . '/../index.html');
    exit;
}

$input = read_json_input();
$idempotencyKey = '';

if (!empty($input['idempotency_key'])) {
    $idempotencyKey = preg_replace('/[^a-zA-Z0-9:_-]/', '', (string) $input['idempotency_key']);
    $cached = kv_get_json('idem:' . $idempotencyKey);

    if (is_array($cached)) {
        $cached['idempotent_replay'] = true;
        json_response($cached, 200);
    }
}

$data = normalize_waymb_create_payload($input);
$result = waymb_request('/transactions/create', $data, 30);

if (!$result['ok']) {
    json_response([
        'error' => 'Falha na comunicação com o gateway de pagamentos.',
        'details' => $result['error'],
        'method' => $data['method']
    ], 502);
}

$payload = json_decode($result['body'], true);

if (!is_array($payload)) {
    json_response([
        'error' => 'Resposta inválida da WayMB.',
        'raw' => $result['body']
    ], 502);
}

if (isset($payload['status'])) {
    $payload['status'] = normalize_waymb_status($payload['status']);
}

$payload['amount'] = $payload['amount'] ?? $data['amount'];
$payload['method'] = $payload['method'] ?? $data['method'];
$payload['payer'] = $payload['payer'] ?? $data['payer'];
$payload['trackingParameters'] = $payload['trackingParameters'] ?? $data['trackingParameters'];
$payload['pagePath'] = $payload['pagePath'] ?? $data['pagePath'];
$payload['paymentDescription'] = $payload['paymentDescription'] ?? $data['paymentDescription'];

persist_transaction_snapshot($payload);
send_utmify_order($payload);

if ($idempotencyKey !== '') {
    kv_set_json('idem:' . $idempotencyKey, $payload);
}

json_response($payload, $result['status']);
