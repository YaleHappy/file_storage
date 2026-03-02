<?php
session_start();
require 'config.php';
header('Content-Type: application/json');

// 定義更精確的閒置時間閾值（秒）
$statusThresholds = [
    'online' => 70,    
    'idle'   => 70,    
    'away'  =>70,      
];

// 遍歷每個狀態的閾值，更新用戶狀態
foreach ($statusThresholds as $fromStatus => $threshold) {
    $stmt = $pdo->prepare("
        UPDATE users 
        SET status = CASE 
            WHEN status = ? AND TIMESTAMPDIFF(SECOND, last_active, NOW()) > ? THEN ?
            ELSE status 
        END
        WHERE status = ?
    ");
    
    // 根據不同的起始狀態和閾值，設定目標狀態
    $toStatus = match($fromStatus) {
        'online' => 'idle',
        'idle'   => 'away',
        'away'   => 'offline',
        default  => 'offline'
    };
    
    $stmt->execute([$fromStatus, $threshold, $toStatus, $fromStatus]);
}

// 計算更新的用戶數
$stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE status = 'offline'");
$stmt->execute();
$offlineUsers = $stmt->fetchColumn();

echo json_encode([
    'success' => true, 
    'offline_users' => $offlineUsers,
    'status_thresholds' => $statusThresholds
]);
?>