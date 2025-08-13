<?php
require_once 'auth_check.php';

if (!isset($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$filename = $_GET['file'];
$filepath = UPLOAD_DIR . $filename;

// 安全检查：确保文件存在且在上传目录内
if (!file_exists($filepath) || !is_file($filepath)) {
    header('Location: index.php?error=file_not_found');
    exit;
}

// 检查文件路径是否在允许的目录内（防止目录遍历攻击）
$realPath = realpath($filepath);
$uploadPath = realpath(UPLOAD_DIR);
if (strpos($realPath, $uploadPath) !== 0) {
    header('Location: index.php?error=access_denied');
    exit;
}

// 获取文件信息
$fileSize = filesize($filepath);
$mimeType = mime_content_type($filepath);

// 设置下载头
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

// 输出文件内容
readfile($filepath);
exit;
?>
