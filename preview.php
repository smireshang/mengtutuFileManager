<?php
require_once 'auth_check.php';

if (!isset($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$originalFilename = $_GET['file'];

$mappingFile = UPLOAD_DIR . '.filename_mapping.json';
$filenameMapping = [];
if (file_exists($mappingFile)) {
    $mappingContent = file_get_contents($mappingFile);
    $filenameMapping = json_decode($mappingContent, true) ?: [];
}

$encryptedFilename = isset($filenameMapping[$originalFilename]) ? $filenameMapping[$originalFilename] : null;

if (!$encryptedFilename) {
    header('Location: index.php?error=file_not_found');
    exit;
}

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

$encryptedFileSize = filesize($encryptedFilepath);
$extension = strtolower(pathinfo($originalFilename, PATHINFO_EXTENSION));

// 支持预览的文件类型
$imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
$textTypes = ['txt'];

$isImage = in_array($extension, $imageTypes);
$isText = in_array($extension, $textTypes);

if (!$isImage && !$isText) {
    header('Location: index.php?error=preview_not_supported');
    exit;
}

$actualFileSize = $encryptedFileSize;
$textContent = '';
if ($isText) {
    $decryptedContent = decryptFile($encryptedFilepath);
    if ($decryptedContent !== false) {
        $actualFileSize = strlen($decryptedContent);
        $textContent = $decryptedContent;
    }
}

// 格式化文件大小
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件预览 - <?php echo htmlspecialchars($originalFilename); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f7fa;
            min-height: 100vh;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .header h1 {
            color: #333;
            font-size: 1.5rem;
        }
        
        .header-actions {
            display: flex;
            gap: 1rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
            cursor: pointer;
            transition: background 0.3s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5a6fd8;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .preview-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .preview-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
        }
        
        .preview-header h2 {
            font-size: 1.3rem;
            margin-bottom: 0.5rem;
        }
        
        .file-meta {
            display: flex;
            gap: 2rem;
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .preview-content {
            padding: 2rem;
        }
        
        .image-preview {
            text-align: center;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 70vh;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        }
        
        .text-preview {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 1.5rem;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 70vh;
            overflow-y: auto;
        }
        
        .preview-actions {
            padding: 1.5rem;
            border-top: 1px solid #eee;
            display: flex;
            gap: 1rem;
            justify-content: center;
        }
        
        .error-message {
            text-align: center;
            padding: 3rem;
            color: #dc3545;
        }
        
        .error-message .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .encryption-badge {
            background: #28a745;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        @media (max-width: 768px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .header-actions {
                justify-content: center;
            }
            
            .file-meta {
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .preview-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>文件预览</h1>
        <div class="header-actions">
            <a href="download.php?file=<?php echo urlencode($originalFilename); ?>" class="btn btn-primary">下载文件</a>
            <a href="index.php" class="btn btn-secondary">返回列表</a>
        </div>
    </div>
    
    <div class="container">
        <div class="preview-section">
            <div class="preview-header">
                <h2>
                    <?php echo htmlspecialchars($originalFilename); ?>
                    <span class="encryption-badge">🔒 已加密</span>
                </h2>
                <div class="file-meta">
                    <span>文件大小: <?php echo formatBytes($actualFileSize); ?></span>
                    <span>文件类型: <?php echo strtoupper($extension); ?></span>
                    <span>修改时间: <?php echo date('Y-m-d H:i:s', filemtime($encryptedFilepath)); ?></span>
                </div>
            </div>
            
            <div class="preview-content">
                <?php if ($isImage): ?>
                    <div class="image-preview">
                        <!-- Using serve_file.php with original filename to properly decrypt and serve images -->
                        <img src="serve_file.php?file=<?php echo urlencode($originalFilename); ?>" alt="<?php echo htmlspecialchars($originalFilename); ?>">
                    </div>
                <?php elseif ($isText): ?>
                    <div class="text-preview">
                        <?php
                        if ($textContent !== '') {
                            // 限制显示的文本长度，防止过大文件影响性能
                            if (strlen($textContent) > 50000) {
                                $textContent = substr($textContent, 0, 50000) . "\n\n... (文件内容过长，仅显示前50000个字符)";
                            }
                            echo htmlspecialchars($textContent);
                        } else {
                            echo "无法解密文件内容";
                        }
                        ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="preview-actions">
                <a href="download.php?file=<?php echo urlencode($originalFilename); ?>" class="btn btn-primary">下载文件</a>
                <button class="btn btn-secondary" onclick="renameFile('<?php echo htmlspecialchars($originalFilename); ?>')">重命名</button>
                <button class="btn" style="background: #dc3545; color: white;" onclick="deleteFile('<?php echo htmlspecialchars($originalFilename); ?>')">删除文件</button>
            </div>
        </div>
    </div>
    
    <script>
        // 重命名文件
        function renameFile(filename) {
            const newName = prompt('请输入新的文件名：', filename);
            if (newName && newName !== filename) {
                window.location.href = `rename.php?old=${encodeURIComponent(filename)}&new=${encodeURIComponent(newName)}`;
            }
        }
        
        // 删除文件
        function deleteFile(filename) {
            if (confirm(`确定要删除文件 "${filename}" 吗？此操作不可撤销。`)) {
                window.location.href = `delete.php?file=${encodeURIComponent(filename)}`;
            }
        }
    </script>
</body>
</html>
