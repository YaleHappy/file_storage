<?php
session_start();
require 'config.php';

// 若未登入，則顯示錯誤訊息
if (!isset($_SESSION['user_id'])) {
    echo "<p style='color:red;'>請先登入</p>";
    exit;
}

// 檢查是否有傳入 file_id
if (!isset($_GET['file_id'])) {
    echo "<p style='color:red;'>無效的檔案編號</p>";
    exit;
}
$fileId = (int)$_GET['file_id'];

// 從資料庫抓取檔案資訊
$stmt = $pdo->prepare("
    SELECT file_id, user_id, original_filename, file_size, stored_filename 
    FROM files
    WHERE file_id = ? AND user_id = ?
");
$stmt->execute([$fileId, $_SESSION['user_id']]);
$fileRow = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$fileRow) {
    echo "<p style='color:red;'>找不到此檔案，或你沒有權限預覽。</p>";
    exit;
}

// 設定檔案實體存放路徑
$filePath = __DIR__ . "/uploads/" . $fileRow['stored_filename'];
if (!file_exists($filePath)) {
    echo "<p style='color:red;'>檔案遺失，無法預覽</p>";
    exit;
}

// 取得原始檔名並判斷副檔名
$originalName = $fileRow['original_filename'];
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$fileTitle = htmlspecialchars($originalName);

// 檢查是否為 Office 文件（使用 Google Docs Viewer 預覽）
$officeFormats = ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'pdf', 'odt', 'ods', 'odp'];

// 生成預覽金鑰
$previewKey = hash('sha256', $fileId . 'preview_salt_' . date('Ymd'));

// 建立檔案的完整 URL（用於 Google Docs Viewer）
// 使用新增的預覽金鑰機制
$fileUrl = 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . '/files.php?preview_key=' . $previewKey . '&file_id=' . $fileId;

// URL 編碼，確保 Google Docs Viewer 可以正確讀取
$encodedFileUrl = urlencode($fileUrl);
?>
<!DOCTYPE html>
<html lang="zh-TW">
<head>
  <meta charset="UTF-8">
  <title>檔案預覽 - <?= $fileTitle ?></title>
  <style>
    body {
      margin: 0;
      padding: 0;
      font-family: sans-serif;
      background: #f5f5f5;
    }
    .preview-container {
      padding: 1rem;
    }
    h3 {
      margin-bottom: 1rem;
    }
    .preview-frame {
      width: 100%;
      height: 600px;
      border: 1px solid #ddd;
    }
    .fallback-message {
      padding: 20px;
      background-color: #f8f9fa;
      border: 1px solid #ddd;
      border-radius: 5px;
      margin-top: 20px;
    }
    .download-button {
      display: inline-block;
      margin-top: 10px;
      padding: 8px 16px;
      background-color: #007bff;
      color: white;
      text-decoration: none;
      border-radius: 4px;
    }
    .download-button:hover {
      background-color: #0056b3;
    }
  </style>
</head>
<body>
<div class="preview-container">
  <h3>檔案預覽：<?= $fileTitle ?></h3>
  <?php
  // 根據副檔名判斷如何預覽
  if (in_array($ext, $officeFormats)) {
      // 使用 Google Docs Viewer 預覽 Office 文件
      echo '<iframe src="https://docs.google.com/viewer?url=' . $encodedFileUrl . '&embedded=true" class="preview-frame" frameborder="0"></iframe>';
      echo '<div class="fallback-message">
              <p>如果預覽無法載入，可能是以下原因：</p>
              <ol>
                <li>檔案格式不受支援</li>
                <li>檔案大小超過 Google Docs Viewer 的限制</li>
                <li>伺服器網路問題</li>
              </ol>
              <p>您可以直接下載檔案後在本機開啟：</p>
              <a href="files.php?download=' . $fileId . '" class="download-button">下載檔案</a>
            </div>';
  } else {
      switch ($ext) {
          case 'jpg': case 'jpeg': case 'png': case 'gif':
              // 使用 <img> 顯示圖片
              echo '<img src="files.php?preview_key=' . $previewKey . '&file_id=' . $fileId . '" alt="image" style="max-width:100%;height:auto;">';
              break;
          case 'pdf':
              // 使用 <embed> 內嵌 PDF（Google Docs Viewer 也能預覽 PDF，但瀏覽器內建支援通常更好）
              echo '<embed src="files.php?preview_key=' . $previewKey . '&file_id=' . $fileId . '" type="application/pdf" width="100%" height="600px" />';
              break;
          case 'mp4': case 'webm': case 'ogg':
              // 使用 <video> 顯示影片
              echo '<video width="100%" height="auto" controls>
                      <source src="files.php?preview_key=' . $previewKey . '&file_id=' . $fileId . '" type="video/' . $ext . '">
                      無法播放此影片，可能瀏覽器不支援。
                    </video>';
              break;
          case 'mp3': case 'wav':
              // 使用 <audio> 播放音訊
              echo '<audio controls style="width:100%;">
                      <source src="files.php?preview_key=' . $previewKey . '&file_id=' . $fileId . '" type="audio/' . $ext . '">
                      你的瀏覽器不支援音訊播放。
                    </audio>';
              break;
          case 'txt': case 'log': case 'html': case 'css': case 'js': case 'json': case 'xml':
              // 文字檔案讀取並以 <pre> 顯示（注意大檔案可能會耗費資源）
              $content = file_get_contents($filePath);
              echo '<pre style="white-space: pre-wrap; background:#fff; padding:15px; border:1px solid #ddd; max-height:600px; overflow:auto;">' . htmlspecialchars($content) . '</pre>';
              break;
          default:
              echo "<div class='fallback-message'>
                      <p>不支援此檔案類型的線上預覽，請下載後開啟。</p>
                      <a href='files.php?download=" . $fileId . "' class='download-button'>下載檔案</a>
                    </div>";
              break;
      }
  }
  ?>
</div>
</body>
</html>