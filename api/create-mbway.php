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

$data = normalize_waymb_create_payload(read_json_input());
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

persist_transaction_snapshot($payload);
json_response($payload, $result['status']);
