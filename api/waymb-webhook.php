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

    $txId = get_transaction_id($data);
    $existing = $txId ? kv_get_json('tx:' . $txId) : null;

    if (is_array($existing)) {
        if (empty($data['payer']) && !empty($existing['payer'])) {
            $data['payer'] = $existing['payer'];
        }

        if (empty($data['trackingParameters']) && !empty($existing['trackingParameters'])) {
            $data['trackingParameters'] = $existing['trackingParameters'];
        }

        if (empty($data['pagePath']) && !empty($existing['pagePath'])) {
            $data['pagePath'] = $existing['pagePath'];
        }

        if (empty($data['amount']) && !empty($existing['amount'])) {
            $data['amount'] = $existing['amount'];
        }

        if (empty($data['method']) && !empty($existing['method'])) {
            $data['method'] = $existing['method'];
        }
    }

    persist_transaction_snapshot($data);
    send_utmify_order($data);
}

json_response([
    'received' => true,
    'id' => $data['id'] ?? ($data['transactionId'] ?? null),
    'status' => $data['status'] ?? null
]);
