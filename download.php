<?php
require_once 'auth_check.php';

if (!isset($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$filename = $_GET['file'];

$filenameMapping = loadFilenameMapping();
if (!isset($filenameMapping[$filename])) {
    header('Location: index.php?error=file_not_found');
    exit;
}

$encryptedFilename = $filenameMapping[$filename];
$encryptedFilepath = UPLOAD_DIR . $encryptedFilename;

// 安全检查：确保加密文件存在且在上传目录内
if (!file_exists($encryptedFilepath) || !is_file($encryptedFilepath)) {
    header('Location: index.php?error=file_not_found');
    exit;
}

// 检查文件路径是否在允许的目录内（防止目录遍历攻击）
$realPath = realpath($encryptedFilepath);
$uploadPath = realpath(UPLOAD_DIR);
if (strpos($realPath, $uploadPath) !== 0) {
    header('Location: index.php?error=access_denied');
    exit;
}

$decryptedContent = decryptFile($encryptedFilepath);
if ($decryptedContent === false) {
    header('Location: index.php?error=decrypt_failed');
    exit;
}

// 获取文件信息
$fileSize = strlen($decryptedContent);
$mimeType = 'application/octet-stream'; // 默认MIME类型，因为我们无法从加密文件直接获取

// 尝试根据文件扩展名确定MIME类型
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'pdf' => 'application/pdf',
    'txt' => 'text/plain',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'zip' => 'application/zip',
    'rar' => 'application/x-rar-compressed'
];

if (isset($mimeTypes[$extension])) {
    $mimeType = $mimeTypes[$extension];
}

// 设置下载头
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Content-Length: ' . $fileSize);
header('Cache-Control: no-cache, must-revalidate');
header('Pragma: no-cache');

echo $decryptedContent;
exit;
?>
