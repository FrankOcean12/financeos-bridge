<?php
require_once '../../config/db.php';
header('Content-Type: application/json');

try {
    $db = getDB();
    
    // Check tables
    $stmt = $db->query("SHOW TABLES LIKE 'customers'");
    $exists = $stmt->fetch();
    
    // Check if there is even one user
    $stmt = $db->query("SELECT id, email, login_code FROM customers LIMIT 1");
    $sample = $stmt->fetch();
    
    echo json_encode([
        'status' => 'success',
        'db_connection' => 'OK',
        'customers_table' => $exists ? 'EXISTS' : 'NOT FOUND',
        'sample_user' => $sample ? $sample['email'] : 'NO USERS'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
