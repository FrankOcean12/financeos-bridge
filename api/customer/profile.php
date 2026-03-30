<?php
require_once '../../config/db.php';
require_once 'auth.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Method not allowed', 'code' => 405]);
    exit;
}

$db = getDB();
$auth = verify_session($db);
$customer_id = $auth['customer']['customer_id'];

$stmt = $db->prepare("
    SELECT c.id, c.customer_no, c.first_name, c.last_name, c.email, c.phone, c.address, c.status,
           t.org_name, t.accent_color, t.logo_url, t.contact_email, t.contact_phone
    FROM customers c
    JOIN tenants t ON c.tenant_id = t.id
    WHERE c.id = ?
");
$stmt->execute([$customer_id]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    echo json_encode(['status' => 'error', 'message' => 'Profile not found', 'code' => 404]);
    exit;
}

echo json_encode([
    'status' => 'success',
    'data' => $profile
]);
?>
