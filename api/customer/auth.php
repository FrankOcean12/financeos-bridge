<?php
require_once '../../config/db.php';
$db = getDB();

function verify_session($db) {
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Unauthorized", "code" => 401]);
        exit;
    }
    
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    list($type, $token) = explode(" ", $auth_header, 2);
    
    if (strcasecmp($type, "Bearer") != 0 || empty($token)) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Invalid authorization format", "code" => 401]);
        exit;
    }

    $stmt = $db->prepare("SELECT customer_id, tenant_id FROM customer_sessions WHERE session_token = ? AND is_active = 1 AND expires_at > NOW()");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
    
    if (!$session) {
        http_response_code(401);
        echo json_encode(["status" => "error", "message" => "Session expired or invalid", "code" => 401]);
        exit;
    }
    
    return ['customer' => $session, 'token' => $token];
}

function send_json($data, $status_code = 200) {
    http_response_code($status_code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
