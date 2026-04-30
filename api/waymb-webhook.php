<?php

require_once 'utils.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$data = read_json_input();

if (!empty($data)) {
    if (isset($data['status'])) {
        $data['status'] = normalize_waymb_status($data['status']);
    }

    persist_transaction_snapshot($data);
}

json_response([
    'received' => true,
    'id' => $data['id'] ?? ($data['transactionId'] ?? null),
    'status' => $data['status'] ?? null
]);
