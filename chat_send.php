<?php
session_start();
require 'config.php';
require 'add_notification.php'; // 引入通知功能

header('Content-Type: application/json');
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'msg' => '請先登入']);
    exit;
}

if (!isset($_POST['recipient_id']) || !isset($_POST['message'])) {
    echo json_encode(['success' => false, 'msg' => '缺少參數']);
    exit;
}

$recipient_id = (int) $_POST['recipient_id'];
$message = trim($_POST['message']);
if ($message === '') {
    echo json_encode(['success' => false, 'msg' => '訊息不能為空']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO chat_messages (sender_id, recipient_id, message) VALUES (?, ?, ?)");
if ($stmt->execute([$_SESSION['user_id'], $recipient_id, $message])) {
    // 成功發送訊息後，新增通知
    $notificationMessage = $_SESSION['username'] . " 傳送了一則訊息給你";
    addNotification($_SESSION['user_id'], $recipient_id, 'message', $notificationMessage);
    
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'msg' => '訊息儲存失敗']);
}
?>