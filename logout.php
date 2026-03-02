<?php
session_start();
require 'config.php';

// 只更新 `last_active`，但不改變 `status`
if (isset($_SESSION['user_id'])) {
    $userId = $_SESSION['user_id'];
    $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
    $stmt->execute([$userId]);
}

// 清除 session 並登出
session_destroy();
header("Location: login.php");
exit;
?>
