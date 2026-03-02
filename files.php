<?php
session_start();
require 'config.php';

$user_id = $_SESSION['user_id'] ?? null;
$error = '';

// 【0】新增預覽功能 (透過預覽金鑰，不需要登入)
if (isset($_GET['preview_key']) && isset($_GET['file_id'])) {
    $file_id = (int)$_GET['file_id'];
    $preview_key = $_GET['preview_key'];
    
    // 產生預期的金鑰作為安全檢查（使用日期作為變化因子）
    $expected_key = hash('sha256', $file_id . 'preview_salt_' . date('Ymd'));
    
    // 驗證金鑰是否正確
    if ($preview_key === $expected_key) {
        // 查詢資料庫獲取檔案資訊
        $stmt = $pdo->prepare("SELECT original_filename, stored_filename, file_size FROM files WHERE file_id = ?");
        $stmt->execute([$file_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($file) {
            $path = __DIR__ . '/uploads/' . $file['stored_filename'];
            if (file_exists($path)) {
                // 確定檔案的 MIME 類型
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime_type = finfo_file($finfo, $path);
                finfo_close($finfo);
                
                // 設定合適的標頭
                header('Content-Type: ' . $mime_type);
                header('Content-Length: ' . $file['file_size']);
                // 設為 inline 允許瀏覽器直接顯示
                header('Content-Disposition: inline; filename="' . basename($file['original_filename']) . '"');
                
                // 輸出檔案內容
                readfile($path);
                exit;
            }
        }
        // 檔案不存在
        header('HTTP/1.0 404 Not Found');
        echo "檔案不存在或已刪除";
        exit;
    }
    // 金鑰錯誤
    header('HTTP/1.0 403 Forbidden');
    echo "無效的預覽請求";
    exit;
}

// ✅【1】透過 Token 下載 (不需要登入)
if (isset($_GET['token'])) {
    $token = $_GET['token'];

    // 查詢檔案是否符合該 token
    $stmt = $pdo->prepare("
        SELECT f.original_filename, f.stored_filename
        FROM file_shares s
        JOIN files f ON s.file_id = f.file_id
        WHERE s.share_token = ?
          AND (s.expires_at IS NULL OR s.expires_at > NOW())
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $file = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($file) {
        $filepath = __DIR__ . "/uploads/" . $file['stored_filename'];
        if (file_exists($filepath)) {
            header("Content-Disposition: attachment; filename=\"" . basename($file['original_filename']) . "\"");
            readfile($filepath);
            exit;
        } else {
            $error = "檔案不存在";
        }
    } else {
        $error = "分享連結已過期或無效";
    }
}

// ✅【2】若使用者登入，提供檔案管理功能
if ($user_id) {
    // —— 刪除檔案 —— 
    if (isset($_GET['delete'])) {
      $file_id = (int)$_GET['delete'];
      // 取得檔案資訊，包括 stored_filename 與 folder_id
      $stmt = $pdo->prepare("SELECT stored_filename, folder_id FROM files WHERE file_id = ? AND user_id = ?");
      $stmt->execute([$file_id, $user_id]);
      $file = $stmt->fetch(PDO::FETCH_ASSOC);
  
      if ($file) {
          $path = __DIR__ . '/uploads/' . $file['stored_filename'];
          if (file_exists($path)) {
              if (!unlink($path)) {
                  $error = "無法刪除檔案";
              }
          }
          // 刪除資料庫記錄
          $pdo->prepare("DELETE FROM files WHERE file_id = ?")->execute([$file_id]);
          // 根據檔案所在的資料夾導回，若 folder_id 不存在則預設回根目錄 (0)
          $folder_id = $file['folder_id'] ?? 0;
          header("Location: index.php?folder=" . $folder_id);
          exit;
      }
      header("Location: index.php");
      exit;
  }
  

    // —— 下載檔案 (使用者登入) —— 
    if (isset($_GET['download'])) {
        $file_id = (int)$_GET['download'];
        $stmt = $pdo->prepare("SELECT original_filename, stored_filename FROM files WHERE file_id = ? AND user_id = ?");
        $stmt->execute([$file_id, $user_id]);
        $file = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($file) {
            $path = __DIR__ . '/uploads/' . $file['stored_filename'];
            if (file_exists($path)) {
                header('Content-Disposition: attachment; filename="' . $file['original_filename'] . '"');
                readfile($path);
                exit;
            }
        }
        $error = "無權限或檔案不存在";
    }

    // —— 讀取檔案列表 (使用者登入) —— 
    $stmt = $pdo->prepare("SELECT * FROM files WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    $files = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <link rel="icon" type="image/png" href="image/icon.png">

  <title>雲霄閣-我的檔案</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4 bg-light">
<div class="container">
  <h2 class="mb-4">我的檔案</h2>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <?php if ($user_id): ?>
  <table class="table table-striped">
    <thead>
      <tr>
        <th>檔名</th>
        <th>大小 (Bytes)</th>
        <th>上傳時間</th>
        <th>操作</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($files as $f): ?>
        <tr>
          <td><?= htmlspecialchars($f['original_filename']) ?></td>
          <td><?= number_format($f['file_size']) ?></td>
          <td><?= htmlspecialchars($f['created_at']) ?></td>
          <td>
            <a href="?download=<?= $f['file_id'] ?>" class="btn btn-sm btn-success">下載</a>
            <a href="?delete=<?= $f['file_id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('確定要刪除這個檔案嗎？');">刪除</a>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  <a href="index.php" class="btn btn-primary">返回首頁</a>
  <?php else: ?>
    <p class="text-muted">此分享連結僅允許下載，無法管理檔案。</p>
  <?php endif; ?>
</div>
</body>
</html>