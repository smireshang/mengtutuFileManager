<?php
require_once 'auth_check.php';
require_once 'config.php'; // 引入config.php以使用其中的加密函数

$message = '';
$messageType = '';
$debugInfo = [];

// 检查uploads目录
if (!is_dir(UPLOAD_DIR)) {
    if (!mkdir(UPLOAD_DIR, 0755, true)) {
        $debugInfo[] = '无法创建uploads目录';
    } else {
        $debugInfo[] = 'uploads目录已创建';
    }
} else {
    $debugInfo[] = 'uploads目录存在';
}

// 检查目录权限
if (!is_writable(UPLOAD_DIR)) {
    $debugInfo[] = 'uploads目录不可写，请检查权限';
} else {
    $debugInfo[] = 'uploads目录可写';
}

// 检查PHP配置
$debugInfo[] = 'file_uploads: ' . (ini_get('file_uploads') ? '启用' : '禁用');
$debugInfo[] = 'upload_max_filesize: ' . ini_get('upload_max_filesize');
$debugInfo[] = 'post_max_size: ' . ini_get('post_max_size');
$debugInfo[] = 'max_file_uploads: ' . ini_get('max_file_uploads');

// 处理文件上传
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $debugInfo[] = 'POST请求已接收';
    
    if (isset($_FILES['file'])) {
        $debugInfo[] = '文件数据已接收';
        $file = $_FILES['file'];
        
        $debugInfo[] = '文件名: ' . $file['name'];
        $debugInfo[] = '文件大小: ' . $file['size'] . ' bytes';
        $debugInfo[] = '文件类型: ' . $file['type'];
        $debugInfo[] = '临时文件: ' . $file['tmp_name'];
        $debugInfo[] = '错误代码: ' . $file['error'];
        
        // 检查上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            $message = '文件上传失败：' . getUploadErrorMessage($file['error']);
            $messageType = 'error';
        }
        // 检查文件大小
        elseif ($file['size'] > MAX_FILE_SIZE) {
            $message = '文件大小超过限制（最大 ' . formatBytes(MAX_FILE_SIZE) . '）';
            $messageType = 'error';
        }
        // 检查文件是否为空
        elseif ($file['size'] == 0) {
            $message = '文件为空，请选择有效文件';
            $messageType = 'error';
        }
        // 检查文件类型（基本安全检查）
        elseif (!isAllowedFileType($file['name'])) {
            $message = '不允许的文件类型';
            $messageType = 'error';
        }
        else {
            // 生成安全的文件名
            $filename = generateSafeFilename($file['name']);
            
            $encryptedFilename = generateEncryptedFilename($filename);
            $encryptedFilepath = UPLOAD_DIR . $encryptedFilename;
            
            $debugInfo[] = '原始文件名: ' . $filename;
            $debugInfo[] = '加密文件名: ' . $encryptedFilename;
            $debugInfo[] = '目标文件路径: ' . $encryptedFilepath;
            
            // 检查临时文件是否存在
            if (!file_exists($file['tmp_name'])) {
                $message = '临时文件不存在';
                $messageType = 'error';
            }
            elseif (encryptFile($file['tmp_name'], $encryptedFilepath)) {
                addFilenameMapping($filename, $encryptedFilename);
                
                $message = '文件上传并加密成功：' . htmlspecialchars($filename);
                $messageType = 'success';
                $debugInfo[] = '文件加密保存成功';
            } else {
                $message = '文件加密保存失败，请检查目录权限';
                $messageType = 'error';
                $debugInfo[] = '文件加密失败';
            }
        }
    } else {
        $debugInfo[] = '未接收到文件数据';
        $message = '未选择文件或文件上传失败';
        $messageType = 'error';
    }
} else {
    $debugInfo[] = '等待文件上传...';
}

// 获取上传错误信息
function getUploadErrorMessage($errorCode) {
    switch ($errorCode) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return '文件太大';
        case UPLOAD_ERR_PARTIAL:
            return '文件只上传了一部分';
        case UPLOAD_ERR_NO_FILE:
            return '没有选择文件';
        case UPLOAD_ERR_NO_TMP_DIR:
            return '临时目录不存在';
        case UPLOAD_ERR_CANT_WRITE:
            return '文件写入失败';
        default:
            return '未知错误 (代码: ' . $errorCode . ')';
    }
}

// 检查允许的文件类型
function isAllowedFileType($filename) {
    $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip', 'rar'];
    $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($extension, $allowedExtensions);
}

// 生成安全的文件名
function generateSafeFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);
    
    // 只替换真正不安全的字符，保留中文字符
    // 移除或替换文件系统不支持的字符：\ / : * ? " < > |
    $basename = preg_replace('/[\\\\\/:\*\?"<>\|]/', '_', $basename);
    
    // 移除控制字符和一些特殊字符，但保留中文等Unicode字符
    $basename = preg_replace('/[\x00-\x1f\x7f]/', '', $basename);
    
    // 限制长度（按字节计算，中文字符占用更多字节）
    if (strlen($basename) > 100) {
        // 对于UTF-8字符串，使用mb_substr来正确截取
        $basename = mb_substr($basename, 0, 50, 'UTF-8');
    }
    
    // 如果处理后的文件名为空，使用时间戳
    if (empty(trim($basename))) {
        $basename = 'file_' . date('YmdHis');
    }
    
    // 构建完整文件名
    $filename = $basename . '.' . $extension;
    
    // 检查文件是否已存在（基于映射，而不是物理文件）
    $mapping = loadFilenameMapping();
    $counter = 1;
    $originalFilename = $filename;
    
    while (isset($mapping[$filename])) {
        $filename = $basename . '_' . $counter . '.' . $extension;
        $counter++;
    }
    
    return $filename;
}

// 格式化字节数
function formatBytes($bytes) {
    if ($bytes === 0) return '0 B';
    $k = 1024;
    $sizes = ['B', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件上传 - 文件管理系统</title>
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
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .user-info span {
            color: #666;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        
        .container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .nav {
            background: white;
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .nav a {
            display: inline-block;
            padding: 0.5rem 1rem;
            margin-right: 1rem;
            background: #667eea;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            transition: background 0.3s;
        }
        
        .nav a:hover {
            background: #5a6fd8;
        }
        
        .nav a.active {
            background: #764ba2;
        }
        
        .upload-section {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .upload-section h2 {
            color: #333;
            margin-bottom: 1.5rem;
            font-size: 1.3rem;
        }
        
        .message {
            padding: 1rem;
            border-radius: 5px;
            margin-bottom: 1.5rem;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .upload-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .file-input-wrapper {
            position: relative;
            display: inline-block;
            cursor: pointer;
            width: 100%;
        }
        
        .file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-input-display {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 150px;
            border: 2px dashed #ccc;
            border-radius: 10px;
            background: #fafafa;
            transition: all 0.3s;
            text-align: center;
            padding: 2rem;
        }
        
        .file-input-display:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }
        
        .file-input-display.dragover {
            border-color: #667eea;
            background: #e8f2ff;
        }
        
        .upload-icon {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 1rem;
        }
        
        .upload-text {
            color: #666;
            font-size: 1.1rem;
        }
        
        .upload-hint {
            color: #999;
            font-size: 0.9rem;
            margin-top: 0.5rem;
        }
        
        .selected-file {
            background: #e8f5e8;
            border-color: #28a745;
            color: #155724;
        }
        
        .upload-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 2rem;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .upload-btn:hover {
            transform: translateY(-2px);
        }
        
        .upload-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }
        
        .file-info {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
        }
        
        .file-info h3 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        
        .file-info p {
            color: #666;
            margin: 0.25rem 0;
        }
        
        .debug-info {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            font-family: monospace;
            font-size: 0.9rem;
        }
        
        .debug-info h3 {
            color: #495057;
            margin-bottom: 0.5rem;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        .debug-info ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        
        .debug-info li {
            margin: 0.25rem 0;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>文件管理系统</h1>
        <div class="user-info">
            <span>欢迎，<?php echo htmlspecialchars($_SESSION['username']); ?></span>
            <a href="logout.php" class="logout-btn">退出登录</a>
        </div>
    </div>
    
    <div class="container">
        <div class="nav">
            <a href="index.php">文件列表</a>
            <a href="upload.php" class="active">文件上传</a>
        </div>
        
        <div class="upload-section">
            <h2>上传文件</h2>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data" class="upload-form" id="uploadForm">
                <div class="file-input-wrapper">
                    <input type="file" name="file" id="fileInput" class="file-input" required>
                    <div class="file-input-display" id="fileDisplay">
                        <div>
                            <div class="upload-icon">📁</div>
                            <div class="upload-text">点击选择文件或拖拽文件到此处</div>
                            <div class="upload-hint">
                                支持格式：JPG, PNG, GIF, PDF, DOC, DOCX, TXT, ZIP, RAR<br>
                                最大文件大小：<?php echo formatBytes(MAX_FILE_SIZE); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="fileInfo" class="file-info" style="display: none;">
                    <h3>选中的文件：</h3>
                    <p id="fileName"></p>
                    <p id="fileSize"></p>
                    <p id="fileType"></p>
                </div>
                
                <button type="submit" class="upload-btn" id="uploadBtn" disabled>上传文件</button>
            </form>
        </div>
    </div>
    
    <script>
        const fileInput = document.getElementById('fileInput');
        const fileDisplay = document.getElementById('fileDisplay');
        const fileInfo = document.getElementById('fileInfo');
        const uploadBtn = document.getElementById('uploadBtn');
        
        // 文件选择处理
        fileInput.addEventListener('change', handleFileSelect);
        
        // 拖拽处理
        fileDisplay.addEventListener('dragover', handleDragOver);
        fileDisplay.addEventListener('dragleave', handleDragLeave);
        fileDisplay.addEventListener('drop', handleDrop);
        
        function handleFileSelect(e) {
            const file = e.target.files[0];
            if (file) {
                displayFileInfo(file);
            }
        }
        
        function handleDragOver(e) {
            e.preventDefault();
            fileDisplay.classList.add('dragover');
        }
        
        function handleDragLeave(e) {
            e.preventDefault();
            fileDisplay.classList.remove('dragover');
        }
        
        function handleDrop(e) {
            e.preventDefault();
            fileDisplay.classList.remove('dragover');
            
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                displayFileInfo(files[0]);
            }
        }
        
        function displayFileInfo(file) {
            fileDisplay.classList.add('selected-file');
            fileDisplay.innerHTML = `
                <div>
                    <div class="upload-icon">✅</div>
                    <div class="upload-text">已选择文件</div>
                </div>
            `;
            
            document.getElementById('fileName').textContent = '文件名：' + file.name;
            document.getElementById('fileSize').textContent = '文件大小：' + formatBytes(file.size);
            document.getElementById('fileType').textContent = '文件类型：' + file.type;
            
            fileInfo.style.display = 'block';
            uploadBtn.disabled = false;
        }
        
        function formatBytes(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }
    </script>
</body>
</html>
