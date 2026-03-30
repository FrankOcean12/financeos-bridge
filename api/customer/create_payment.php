<?php
require_once '../../config/paymongo.php';
require_once '../../config/db.php';
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed', 'code' => 405]);
    exit;
}

$db = getDB();
$auth = verify_session($db);
$customer_id = $auth['customer']['customer_id'];
$tenant_id = $auth['customer']['tenant_id'];

$input = json_decode(file_get_contents('php://input'), true);
$repayment_id = $input['repayment_id'] ?? null;
$payment_method_type = $input['payment_method_type'] ?? 'gcash';

if (!$repayment_id) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Repayment ID required', 'code' => 400]);
    exit;
}

$stmt = $db->prepare("SELECT r.amount_due, r.loan_id, l.loan_no FROM repayments r JOIN loans l ON r.loan_id = l.id WHERE r.id = ? AND l.borrower_id = ?");
$stmt->execute([$repayment_id, $customer_id]);
$repayment = $stmt->fetch();

if (!$repayment) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Repayment not found', 'code' => 404]);
    exit;
}

$amount_cents = $repayment['amount_due'] * 100;

$paymongo_data = [
    'data' => [
        'attributes' => [
            'amount' => $amount_cents,
            'payment_method_allowed' => [$payment_method_type],
            'payment_method_options' => [
                'card' => ['request_three_d_secure' => 'any']
            ],
            'currency' => 'PHP',
            'capture_type' => 'automatic',
            'description' => "Loan Payment - " . $repayment['loan_no'],
            'metadata' => [
                'repayment_id' => $repayment_id,
                'customer_id' => $customer_id,
                'tenant_id' => $tenant_id,
                'loan_no' => $repayment['loan_no']
            ]
        ]
    ]
];

$ch = curl_init('https://api.paymongo.com/v1/payment_intents');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($paymongo_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode(PAYMONGO_SECRET_KEY . ':')
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$intent = json_decode($response, true);

if ($http_code == 200 && isset($intent['data'])) {
    $intent_id = $intent['data']['id'];
    $client_key = $intent['data']['attributes']['client_key'];
    
    // Create the payment_intents table tracking if it somehow doesn't exist
    $db->exec("CREATE TABLE IF NOT EXISTS payment_intents (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tenant_id INT, customer_id INT, repayment_id INT, 
        paymongo_intent_id VARCHAR(100), paymongo_client_key VARCHAR(255),
        amount DECIMAL(12,2), payment_method VARCHAR(50), 
        status VARCHAR(50) DEFAULT 'awaiting_payment_method',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $db->prepare("INSERT INTO payment_intents (tenant_id, customer_id, repayment_id, paymongo_intent_id, paymongo_client_key, amount, payment_method) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$tenant_id, $customer_id, $repayment_id, $intent_id, $client_key, $repayment['amount_due'], $payment_method_type]);
    
    echo json_encode([
        'status' => 'success',
        'data' => [
            'payment_intent_id' => $intent_id,
            'client_key' => $client_key,
            'amount' => $repayment['amount_due'],
            'currency' => 'PHP',
            'status' => 'awaiting_payment_method'
        ]
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'PayMongo error', 'code' => 500, 'paymongo_response' => $intent]);
}
?>
