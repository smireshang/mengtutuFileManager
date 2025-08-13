<?php
require_once 'auth_check.php';

if (!isset($_GET['file'])) {
    http_response_code(404);
    exit('File not found');
}

$filename = $_GET['file'];
$filepath = UPLOAD_DIR . $filename;

// 安全检查：确保文件存在且在上传目录内
if (!file_exists($filepath) || !is_file($filepath)) {
    http_response_code(404);
    exit('File not found');
}

// 检查文件路径是否在允许的目录内（防止目录遍历攻击）
$realPath = realpath($filepath);
$uploadPath = realpath(UPLOAD_DIR);
if (strpos($realPath, $uploadPath) !== 0) {
    http_response_code(403);
    exit('Access denied');
}

// 获取文件信息
$mimeType = mime_content_type($filepath);
$fileSize = filesize($filepath);

// 设置适当的HTTP头
header('Content-Type: ' . $mimeType);
header('Content-Length: ' . $fileSize);
header('Cache-Control: public, max-age=3600'); // 缓存1小时

// 对于图片文件，设置内联显示；对于其他文件，可以设置为下载
$extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
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

// 输出文件内容
readfile($filepath);
exit;
?>
