<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(["success" => false, "msg" => "請先登入"]);
    exit;
}

try {
    $userId = $_SESSION['user_id'];
    
    // 獲取未讀訊息和檔案數量
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'message' AND is_read = 0 THEN 1 ELSE 0 END) as unread_messages,
            SUM(CASE WHEN type = 'file' AND is_read = 0 THEN 1 ELSE 0 END) as unread_files,
            COUNT(*) FILTER (WHERE is_read = 0) as total_unread
        FROM notifications 
        WHERE recipient_id = ?
    ");
    
    // 如果您的 MySQL 版本不支持 FILTER 語法，可以使用以下替代:
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN type = 'message' AND is_read = 0 THEN 1 ELSE 0 END) as unread_messages,
            SUM(CASE WHEN type = 'file' AND is_read = 0 THEN 1 ELSE 0 END) as unread_files,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as total_unread
        FROM notifications 
        WHERE recipient_id = ?
    ");
    
    $stmt->execute([$userId]);
    $counts = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 獲取最近10條通知
    $notifStmt = $pdo->prepare("
        SELECT n.id, n.sender_id, n.type, n.message, n.created_at, u.username as sender_name
        FROM notifications n
        LEFT JOIN users u ON n.sender_id = u.user_id
        WHERE n.recipient_id = ?
        ORDER BY n.created_at DESC
        LIMIT 10
    ");
    $notifStmt->execute([$userId]);
    $notifications = $notifStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        "success" => true, 
        "unread_messages" => (int)$counts['unread_messages'],
        "unread_files" => (int)$counts['unread_files'],
        "total_unread" => (int)$counts['total_unread'],
        "notifications" => $notifications
    ]);
} catch (Exception $e) {
    error_log("check_notifications.php SQL 錯誤: " . $e->getMessage());
    echo json_encode([
        "success" => false,
        "msg" => "數據庫錯誤：" . $e->getMessage()
    ]);
}
exit;
?>