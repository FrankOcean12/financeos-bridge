<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed', 'code' => 405]);
    exit;
}

$db = getDB();
$input = json_decode(file_get_contents('php://input'), true);
$email = $input['email'] ?? '';
$login_code = $input['login_code'] ?? '';
$device_info = $input['device_info'] ?? 'Unknown Android Device';

if (empty($email) || empty($login_code)) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Email and login code are required', 'code' => 400]);
    exit;
}

// Search for the customer across any tenant to simplify login as requested
$stmt = $db->prepare("SELECT * FROM customers WHERE email = ? LIMIT 1");
$stmt->execute([$email]);
$customer = $stmt->fetch();

if (!$customer) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid credentials', 'code' => 401]);
    exit;
}

$tenant_id = $customer['tenant_id'];

if ($customer['status'] === 'pending') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Account under review', 'code' => 403]);
    exit;
} else if (in_array($customer['status'], ['suspended', 'rejected'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Account not approved', 'code' => 403]);
    exit;
}

// Fast password/login code verify
// Use the numeric version for comparison
$clean_code = str_replace('-', '', $login_code);
if (!password_verify($clean_code, $customer['login_code'])) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Invalid login code', 'code' => 401]);
    exit;
}

$token = bin2hex(random_bytes(32));
$ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Insert session
$stmt = $db->prepare("INSERT INTO customer_sessions (session_token, customer_id, tenant_id, device_info, ip_address, expires_at) VALUES (?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))");
$stmt->execute([$token, $customer['id'], $tenant_id, $device_info, $ip]);

// Log login
$stmt = $db->prepare("UPDATE customers SET last_login = NOW() WHERE id = ?");
$stmt->execute([$customer['id']]);

// Get Branding
$stmt = $db->prepare("SELECT org_name, accent_color, logo_url FROM tenants WHERE id = ?");
$stmt->execute([$tenant_id]);
$tenant = $stmt->fetch();

// End output buffer and send clean JSON
ob_end_clean();
echo json_encode([
    'status' => 'success',
    'data' => [
        'token' => $token,
        'customer_no' => $customer['customer_no'],
        'first_name' => $customer['first_name'],
        'last_name' => $customer['last_name'],
        'email' => $customer['email'],
        'phone' => $customer['phone'],
        'status' => $customer['status'],
        'tenant_id' => $tenant_id,
        'tenant_org_name' => $tenant['org_name'] ?? 'FinanceOS',
        'tenant_accent_color' => $tenant['accent_color'] ?? '#FF0066',
        'tenant_logo_url' => $tenant['logo_url'] ?? '',
        'plan' => $customer['plan'] ?? 'basic'
    ]
]);
