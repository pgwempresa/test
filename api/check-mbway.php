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

$data = read_json_input();

if (isset($data['transaction_id']) && !isset($data['id'])) {
    $data['id'] = $data['transaction_id'];
}

if (empty($data['id'])) {
    json_response(['error' => 'transaction id is required'], 400);
}

$cached = kv_get_json('tx:' . $data['id']);

if (is_array($cached) && !empty($cached['status'])) {
    $cached['status'] = normalize_waymb_status($cached['status']);

    if (in_array($cached['status'], ['COMPLETED', 'DECLINED'], true)) {
        json_response($cached, 200);
    }
}

$result = waymb_request('/transactions/info', ['id' => $data['id']], 15);

if (!$result['ok']) {
    json_response(['error' => 'Gateway error: ' . $result['error']], 502);
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

persist_transaction_snapshot($payload);
json_response($payload, $result['status']);
