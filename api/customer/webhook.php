<?php
require_once '../../config/paymongo.php';
require_once '../../config/db.php';

$payload = file_get_contents('php://input');
$signature_header = $_SERVER['HTTP_PAYMONGO_SIGNATURE'] ?? '';

list($t_part, $te_part, $li_part) = explode(',', $signature_header . ',,');
$timestamp = str_replace('t=', '', $t_part);
$te_signature = str_replace('te=', '', $te_part);

$signature_payload = $timestamp . '.' . $payload;

// Only verify if webhook secret exists, otherwise bypass for testing
if (defined('PAYMONGO_WEBHOOK_SECRET')) {
    $expected_signature = hash_hmac('sha256', $signature_payload, PAYMONGO_WEBHOOK_SECRET);
    if (!hash_equals($expected_signature, $te_signature)) {
        http_response_code(400);
        exit("Invalid signature");
    }
}

$event = json_decode($payload, true);

if ($event['data']['attributes']['type'] === 'payment.paid') {
    $payment = $event['data']['attributes']['data'];
    $repayment_id = $payment['attributes']['metadata']['repayment_id'] ?? null;
    $amount = $payment['attributes']['amount'] / 100;
    
    if ($repayment_id) {
        $db = getDB();
        // Since tenant dashboard looks at repayment.amount_paid and status
        // We update exactly those columns, which automatically causes the Web Dashboard
        // to show this successfully paid inside reports.php and dashboard.php
        $stmt = $db->prepare("UPDATE repayments SET amount_paid = amount_paid + ?, status = 'paid', paid_date = CURRENT_DATE WHERE id = ?");
        $stmt->execute([$amount, $repayment_id]);
    }
}

http_response_code(200);
echo "OK";
?>
