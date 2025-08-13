<?php
require_once 'auth_check.php';

date_default_timezone_set('Asia/Shanghai');

$message = '';
$messageType = '';

if (!isset($_GET['file'])) {
    header('Location: index.php');
    exit;
}

$originalFilename = $_GET['file'];

$filenameMapping = loadFilenameMapping();
$encryptedFilename = null;

// Find the encrypted filename for the original filename
if (isset($filenameMapping[$originalFilename])) {
    $encryptedFilename = $filenameMapping[$originalFilename];
} else {
    $message = '文件映射不存在';
    $messageType = 'error';
}

if ($encryptedFilename) {
    $filepath = UPLOAD_DIR . $encryptedFilename;
    
    // 安全检查：确保文件存在且在上传目录内
    if (!file_exists($filepath) || !is_file($filepath)) {
        $message = '加密文件不存在';
        $messageType = 'error';
    } else {
        // 检查文件路径是否在允许的目录内（防止目录遍历攻击）
        $realPath = realpath($filepath);
        $uploadPath = realpath(UPLOAD_DIR);
        
        if (strpos($realPath, $uploadPath) !== 0) {
            $message = '访问被拒绝';
            $messageType = 'error';
        } else {
            if (unlink($filepath)) {
                // Remove from filename mapping
                unset($filenameMapping[$originalFilename]);
                saveFilenameMapping($filenameMapping);
                
                $message = '文件删除成功';
                $messageType = 'success';
            } else {
                $message = '文件删除失败';
                $messageType = 'error';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件删除 - 文件管理系统</title>
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
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .result-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            text-align: center;
            max-width: 400px;
            width: 100%;
        }
        
        .result-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .success .result-icon {
            color: #28a745;
        }
        
        .error .result-icon {
            color: #dc3545;
        }
        
        .result-message {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            color: #333;
        }
        
        .back-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 1rem;
            display: inline-block;
            transition: transform 0.2s;
        }
        
        .back-btn:hover {
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <div class="result-container <?php echo $messageType; ?>">
        <div class="result-icon">
            <?php echo $messageType === 'success' ? '✅' : '❌'; ?>
        </div>
        <div class="result-message">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <a href="index.php" class="back-btn">返回文件列表</a>
    </div>
    
    <script>
        // 3秒后自动跳转
        setTimeout(function() {
            window.location.href = 'index.php';
        }, 3000);
    </script>
</body>
</html>
