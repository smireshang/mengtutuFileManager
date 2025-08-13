<?php
require_once 'auth_check.php';

if (!isset($_GET['file'])) {
    http_response_code(404);
    exit('File not found');
}

$filename = $_GET['file'];
$encryptedFilename = generateEncryptedFilename($filename);
$encryptedFilepath = UPLOAD_DIR . $encryptedFilename;

// 安全检查：确保加密文件存在且在上传目录内
if (!file_exists($encryptedFilepath) || !is_file($encryptedFilepath)) {
    http_response_code(404);
    exit('File not found');
}

// 检查文件路径是否在允许的目录内（防止目录遍历攻击）
$realPath = realpath($encryptedFilepath);
$uploadPath = realpath(UPLOAD_DIR);
if (strpos($realPath, $uploadPath) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

$decryptedContent = decryptFile($encryptedFilepath);
if ($decryptedContent === false) {
    http_response_code(500);
    exit('Failed to decrypt file');
}

// 根据原始文件扩展名确定MIME类型
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
$mimeTypes = [
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'png' => 'image/png',
    'gif' => 'image/gif',
    'webp' => 'image/webp',
    'svg' => 'image/svg+xml',
    'txt' => 'text/plain',
    'pdf' => 'application/pdf'
];

$mimeType = isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream';

// 设置适当的HTTP头
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . strlen($decryptedContent));
header('Cache-Control: public, max-age=3600'); // 缓存1小时

// 对于图片文件，设置内联显示；对于其他文件，可以设置为下载
$imageTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];

if (in_array($extension, $imageTypes)) {
    header('Content-Disposition: inline; filename="' . $filename . '"');
} else {
    // 对于非图片文件，如果是预览模式，仍然内联显示
    if (isset($_GET['preview']) && $_GET['preview'] == '1') {
        header('Content-Disposition: inline; filename="' . $filename . '"');
    } else {
        header('Content-Disposition: attachment; filename="' . $filename . '"');
    }
}

echo $decryptedContent;
exit;
?>
