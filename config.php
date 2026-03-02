<?php
// config.php（XAMPP 本地版本）

$db_host = "127.0.0.1";      // 或 localhost
$db_port = "3306";           // XAMPP 預設 MySQL port
$db_name = "file_storage_db";
$db_user = "root";
$db_pass = "";               // XAMPP 預設 root 沒密碼（如果你有設請填）

// 設定 PHP 應用程式的時區為台北時間
date_default_timezone_set('Asia/Taipei');

try {
    $dsn = "mysql:host=$db_host;port=$db_port;dbname=$db_name;charset=utf8mb4";
    $pdo = new PDO($dsn, $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 設定 MySQL 連線時區
    $pdo->exec("SET time_zone = '+08:00';");

} catch (PDOException $e) {
    die("資料庫連線失敗: " . $e->getMessage());
}

// Session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 更新使用者最後活動時間
if (isset($_SESSION['user_id'])) {
    $stmt = $pdo->prepare("UPDATE users SET last_active = NOW() WHERE user_id = ?");
    $stmt->execute([$_SESSION['user_id']]);
}
?>