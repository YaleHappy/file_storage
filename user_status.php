<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['error' => '請先登入']);
    exit;
}

try {
    $currentUserId = $_SESSION['user_id'];

    // 取出所有使用者 (包含自己)
    $stmt = $pdo->prepare("
        SELECT user_id, username, status, last_active 
        FROM users
    ");
    $stmt->execute();
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $statusStmt = $pdo->prepare("SELECT status FROM users WHERE user_id = ?");
    $statusStmt->execute([$currentUserId]);
    $dbStatus = $statusStmt->fetchColumn();
    
    $_SESSION['status'] = $dbStatus;
    $currentStatus = $dbStatus;

    // 狀態對應的 icon
    $statusIcons = [
        'online'  => '🟢',
        'idle'    => '🟡',
        'away'    => '🔴',
        'hidden'  => '⚫',
        'offline' => '⚪'
    ];

    echo json_encode([
        'users' => $users,
        'current_status' => $currentStatus,
        'current_status_icon' => $statusIcons[$currentStatus] ?? '⚪'
    ]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
?>
