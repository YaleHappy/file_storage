<?php
session_start();
require 'config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => '請先登入']);
    exit;
}

$userId = $_SESSION['user_id'];
$allowedStatuses = ['online', 'idle', 'away', 'hidden'];
$newStatus = isset($_POST['status']) && in_array($_POST['status'], $allowedStatuses) ? $_POST['status'] : 'online';

try {
    $stmt = $pdo->prepare("UPDATE users SET last_active = NOW(), status = ? WHERE user_id = ?");
    $stmt->execute([$newStatus, $userId]);

    $_SESSION['status'] = $newStatus;

    // 額外取得更新後的用戶資訊
    $userStmt = $pdo->prepare("SELECT username, status FROM users WHERE user_id = ?");
    $userStmt->execute([$userId]);
    $userInfo = $userStmt->fetch(PDO::FETCH_ASSOC);

    // 回傳更多用戶資訊
    echo json_encode([
        'success' => true, 
        'status' => $newStatus,
        'username' => $userInfo['username'],
        'message' => '狀態更新成功'
    ]);
    exit;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    exit;
}
?>