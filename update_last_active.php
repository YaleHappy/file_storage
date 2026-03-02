<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '請先登入']);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // 更新 last_active，始終更新
    $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>