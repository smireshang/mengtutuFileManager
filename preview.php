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
$fileModified = filemtime($encryptedFilepath);

// 支持预览的文件类型
$imageTypes = ['jpg', 'jpeg', 'png', 'gif'];
$textTypes = ['txt'];

$isImage = in_array($extension, $imageTypes);
$isText = in_array($extension, $textTypes);

if (!$isImage && !$isText) {
    header('Location: index.php?error=preview_not_supported');
    exit;
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && $isText) {
    header('Content-Type: text/plain; charset=utf-8');
    $decryptedContent = decryptFile($encryptedFilepath);
    if ($decryptedContent !== false) {
        // 限制AJAX返回的文本长度
        if (strlen($decryptedContent) > 50000) {
            $decryptedContent = substr($decryptedContent, 0, 50000) . "\n\n... (文件内容过长，仅显示前50000个字符)";
        }
        echo $decryptedContent;
    } else {
        http_response_code(500);
        echo "无法解密文件内容";
    }
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
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            height: 70vh;
            min-height: 400px;
            max-height: 80vh;
            padding: 1rem;
            overflow: hidden;
        }
        
        .image-preview img {
            max-width: 100% !important;
            max-height: 100% !important;
            width: auto !important;
            height: auto !important;
            object-fit: contain !important;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.1);
            transition: opacity 0.3s ease;
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
        
        /* 添加缓存状态指示器样式 */
        .cache-badge {
            background: #17a2b8;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 3px;
            font-size: 0.8rem;
            margin-left: 0.5rem;
        }
        
        .loading-indicator {
            display: none;
            text-align: center;
            padding: 2rem;
            color: #666;
        }
        
        .loading-indicator.show {
            display: block;
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
                    <span class="cache-badge" id="cacheBadge" style="display: none;">📦 已缓存</span>
                </h2>
                <div class="file-meta">
                    <span>文件大小: <?php echo formatBytes($actualFileSize); ?></span>
                    <span>文件类型: <?php echo strtoupper($extension); ?></span>
                    <span>修改时间: <?php echo date('Y-m-d H:i:s', $fileModified); ?></span>
                </div>
            </div>
            
            <!-- 添加加载指示器 -->
            <div class="loading-indicator" id="loadingIndicator">
                <div>⏳ 正在加载文件内容...</div>
            </div>
            
            <div class="preview-content" id="previewContent">
                <?php if ($isImage): ?>
                    <div class="image-preview">
                        <!-- Using serve_file.php with original filename to properly decrypt and serve images -->
                        <img id="previewImage" src="/placeholder.svg" alt="<?php echo htmlspecialchars($originalFilename); ?>" style="display: none;">
                    </div>
                <?php elseif ($isText): ?>
                    <div class="text-preview" id="textPreview">
                        <!-- 内容将通过JavaScript加载 -->
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
    
    <!-- 引入缓存管理器 -->
    <script src="js/cache-manager.js"></script>
    <script>
        // 文件信息
        const filename = <?php echo json_encode($originalFilename); ?>;
        const fileModified = <?php echo $fileModified; ?>;
        const isImage = <?php echo $isImage ? 'true' : 'false'; ?>;
        const isText = <?php echo $isText ? 'true' : 'false'; ?>;
        
        document.addEventListener('DOMContentLoaded', function() {
            loadFileContent();
        });
        
        async function loadFileContent() {
            const loadingIndicator = document.getElementById('loadingIndicator');
            const previewContent = document.getElementById('previewContent');
            const cacheBadge = document.getElementById('cacheBadge');
            
            // 显示加载指示器
            loadingIndicator.classList.add('show');
            
            try {
                // 尝试从缓存加载
                const cachedData = fileCacheManager.getCache(filename, fileModified);
                
                if (cachedData) {
                    // 从缓存加载
                    loadFromCache(cachedData);
                    cacheBadge.style.display = 'inline-block';
                    console.log('从缓存加载文件:', filename);
                } else {
                    // 从服务器加载
                    await loadFromServer();
                    console.log('从服务器加载文件:', filename);
                }
            } catch (error) {
                console.error('文件加载失败:', error);
                showError('文件加载失败，请刷新页面重试');
            } finally {
                // 隐藏加载指示器
                loadingIndicator.classList.remove('show');
            }
        }
        
        function loadFromCache(cachedData) {
            if (isImage && cachedData.contentType === 'image') {
                const img = document.getElementById('previewImage');
                img.src = cachedData.data;
                
                img.style.display = 'block';
                img.style.maxWidth = '100%';
                img.style.maxHeight = '100%';
                img.style.width = 'auto';
                img.style.height = 'auto';
                img.style.objectFit = 'contain';
                
                // 确保图片加载完成后重新应用样式
                img.onload = function() {
                    // 强制重新计算尺寸
                    this.style.maxWidth = '100%';
                    this.style.maxHeight = '100%';
                    this.style.width = 'auto';
                    this.style.height = 'auto';
                    this.style.objectFit = 'contain';
                    
                    // 强制重新计算布局
                    this.offsetHeight;
                    
                    console.log('图片从缓存加载完成，尺寸已调整');
                };
                
                // 如果图片已经加载完成，立即触发onload
                if (img.complete) {
                    img.onload();
                }
            } else if (isText && cachedData.contentType === 'text/plain') {
                const textPreview = document.getElementById('textPreview');
                textPreview.textContent = cachedData.data;
            }
        }
        
        async function loadFromServer() {
            if (isImage) {
                await loadImageFromServer();
            } else if (isText) {
                await loadTextFromServer();
            }
        }
        
        async function loadImageFromServer() {
            return new Promise((resolve, reject) => {
                const img = document.getElementById('previewImage');
                
                img.onload = function() {
                    this.style.display = 'block';
                    this.style.maxWidth = '100%';
                    this.style.maxHeight = '100%';
                    this.style.width = 'auto';
                    this.style.height = 'auto';
                    this.style.objectFit = 'contain';
                    
                    // 强制重新计算布局
                    this.offsetHeight;
                    
                    // 缓存图片时使用适当的尺寸
                    try {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        
                        // 计算适合的缓存尺寸（不超过1920x1080）
                        const maxCacheWidth = 1920;
                        const maxCacheHeight = 1080;
                        let cacheWidth = this.naturalWidth;
                        let cacheHeight = this.naturalHeight;
                        
                        if (cacheWidth > maxCacheWidth || cacheHeight > maxCacheHeight) {
                            const ratio = Math.min(maxCacheWidth / cacheWidth, maxCacheHeight / cacheHeight);
                            cacheWidth = Math.floor(cacheWidth * ratio);
                            cacheHeight = Math.floor(cacheHeight * ratio);
                        }
                        
                        canvas.width = cacheWidth;
                        canvas.height = cacheHeight;
                        ctx.drawImage(this, 0, 0, cacheWidth, cacheHeight);
                        
                        const dataUrl = canvas.toDataURL('image/jpeg', 0.8);
                        fileCacheManager.setCache(filename, dataUrl, fileModified, 'image');
                    } catch (error) {
                        console.error('图片缓存失败:', error);
                    }
                    
                    resolve();
                };
                
                img.onerror = function() {
                    reject(new Error('图片加载失败'));
                };
                
                img.src = `serve_file.php?file=${encodeURIComponent(filename)}&t=${Date.now()}`;
            });
        }
        
        async function loadTextFromServer() {
            try {
                const response = await fetch(`preview.php?file=${encodeURIComponent(filename)}&ajax=1&t=${Date.now()}`);
                
                if (!response.ok) {
                    throw new Error('文本文件加载失败');
                }
                
                const text = await response.text();
                const textPreview = document.getElementById('textPreview');
                textPreview.textContent = text;
                
                // 缓存文本内容
                fileCacheManager.setCache(filename, text, fileModified, 'text/plain');
                
            } catch (error) {
                throw error;
            }
        }
        
        function showError(message) {
            const previewContent = document.getElementById('previewContent');
            previewContent.innerHTML = `
                <div class="error-message">
                    <div class="icon">❌</div>
                    <h3>加载失败</h3>
                    <p>${message}</p>
                </div>
            `;
        }
        
        // 重命名文件
        function renameFile(filename) {
            const newName = prompt('请输入新的文件名：', filename);
            if (newName && newName !== filename) {
                // 清除旧文件的缓存
                fileCacheManager.removeCache(filename);
                window.location.href = `rename.php?old=${encodeURIComponent(filename)}&new=${encodeURIComponent(newName)}`;
            }
        }
        
        // 删除文件
        function deleteFile(filename) {
            if (confirm(`确定要删除文件 "${filename}" 吗？此操作不可撤销。`)) {
                // 清除文件缓存
                fileCacheManager.removeCache(filename);
                window.location.href = `delete.php?file=${encodeURIComponent(filename)}`;
            }
        }
    </script>
</body>
</html>
