<?php
require_once 'auth_check.php';

$message = '';
$messageType = '';

if (!isset($_GET['old']) || !isset($_GET['new'])) {
    header('Location: index.php');
    exit;
}

$oldName = $_GET['old'];
$newName = $_GET['new'];

// 加载文件名映射
$filenameMapping = loadFilenameMapping();

// 验证原文件在映射中存在
if (!isset($filenameMapping[$oldName])) {
    $message = '原文件不存在';
    $messageType = 'error';
}
// 验证新文件名
elseif (empty($newName) || $newName === $oldName) {
    $message = '新文件名无效';
    $messageType = 'error';
}
// 检查新文件名是否已存在于映射中
elseif (isset($filenameMapping[$newName])) {
    $message = '文件名已存在';
    $messageType = 'error';
}
// 验证文件名安全性
elseif (!isValidFilename($newName)) {
    $message = '文件名包含非法字符';
    $messageType = 'error';
}
// 执行重命名
else {
    // 获取加密文件名
    $encryptedFilename = $filenameMapping[$oldName];
    $encryptedFilepath = UPLOAD_DIR . $encryptedFilename;
    
    // 验证加密文件确实存在
    if (!file_exists($encryptedFilepath) || !is_file($encryptedFilepath)) {
        $message = '加密文件不存在';
        $messageType = 'error';
    } else {
        // 更新文件名映射：移除旧映射，添加新映射
        unset($filenameMapping[$oldName]);
        $filenameMapping[$newName] = $encryptedFilename;
        
        // 保存更新后的映射
        if (saveFilenameMapping($filenameMapping)) {
            $message = '文件重命名成功';
            $messageType = 'success';
        } else {
            $message = '文件重命名失败：无法更新文件映射';
            $messageType = 'error';
        }
    }
}

// 验证文件名是否安全
function isValidFilename($filename) {
    // 不允许的字符和模式
    $invalidChars = ['/', '\\', ':', '*', '?', '"', '<', '>', '|'];
    $invalidPatterns = ['.', '..', 'CON', 'PRN', 'AUX', 'NUL'];
    
    // 检查非法字符
    foreach ($invalidChars as $char) {
        if (strpos($filename, $char) !== false) {
            return false;
        }
    }
    
    // 检查非法模式
    $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
    if (in_array(strtoupper($nameWithoutExt), $invalidPatterns)) {
        return false;
    }
    
    // 检查长度
    if (strlen($filename) > 255) {
        return false;
    }
    
    return true;
}

?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件重命名 - 文件管理系统</title>
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
