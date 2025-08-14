<?php
require_once 'auth_check.php';

date_default_timezone_set('Asia/Shanghai');

function getFileList() {
    $files = [];
    $mappingFile = UPLOAD_DIR . '.filename_mapping.json';
    
    if (file_exists($mappingFile)) {
        $mapping = json_decode(file_get_contents($mappingFile), true) ?: [];
        
        foreach ($mapping as $originalName => $encryptedName) {
            $encryptedPath = UPLOAD_DIR . $encryptedName;
            if (file_exists($encryptedPath) && is_file($encryptedPath)) {
                // 获取文件扩展名来确定类型
                $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
                $mimeTypes = [
                    'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
                    'gif' => 'image/gif', 'pdf' => 'application/pdf', 'txt' => 'text/plain',
                    'doc' => 'application/msword', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                    'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed'
                ];
                
                $files[] = [
                    'name' => $originalName,
                    'size' => filesize($encryptedPath),
                    'modified' => filemtime($encryptedPath),
                    'type' => isset($mimeTypes[$extension]) ? $mimeTypes[$extension] : 'application/octet-stream',
                    'extension' => $extension
                ];
            }
        }
    }
    
    // 按修改时间排序（最新的在前）
    usort($files, function($a, $b) {
        return $b['modified'] - $a['modified'];
    });
    
    return $files;
}

// 格式化文件大小
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}

// 获取文件图标
function getFileIcon($extension) {
    $icons = [
        'jpg' => '🖼️', 'jpeg' => '🖼️', 'png' => '🖼️', 'gif' => '🖼️',
        'pdf' => '📄', 'doc' => '📝', 'docx' => '📝', 'txt' => '📄',
        'zip' => '🗜️', 'rar' => '🗜️', 'mp3' => '🎵', 'mp4' => '🎬'
    ];
    
    return isset($icons[$extension]) ? $icons[$extension] : '📁';
}

// 检查文件是否支持预览的函数
function canPreviewFile($extension) {
    $previewableTypes = ['jpg', 'jpeg', 'png', 'gif', 'txt'];
    return in_array(strtolower($extension), $previewableTypes);
}

$files = getFileList();
$totalFiles = count($files);
$totalSize = array_sum(array_column($files, 'size'));
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>文件管理系统</title>
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
            max-width: 1200px;
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
        
        /* Added system info panel styles */
        .system-info-btn {
            background: #17a2b8 !important;
        }
        
        .system-info-btn:hover {
            background: #138496 !important;
        }
        
        .system-info-panel {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .system-info-header {
            background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .system-info-header h2 {
            font-size: 1.3rem;
        }
        
        .close-btn {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 0;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }
        
        .close-btn:hover {
            background: rgba(255,255,255,0.2);
        }
        
        .system-info-content {
            padding: 1.5rem;
        }
        
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1rem;
        }
        
        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #17a2b8;
        }
        
        .info-label {
            font-weight: 500;
            color: #333;
        }
        
        .info-value {
            color: #666;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            text-align: right;
            word-break: break-all;
        }
        
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .stat-card h3 {
            color: #333;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .stat-card p {
            color: #666;
            font-size: 0.9rem;
        }
        
        .files-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .files-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .files-header h2 {
            font-size: 1.3rem;
        }
        
        .search-box {
            padding: 0.5rem;
            border: none;
            border-radius: 5px;
            width: 250px;
            font-size: 0.9rem;
        }
        
        .files-list {
            padding: 0;
        }
        
        .file-item {
            display: flex;
            align-items: center;
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #eee;
            transition: background 0.2s;
        }
        
        .file-item:hover {
            background: #f8f9fa;
        }
        
        .file-item:last-child {
            border-bottom: none;
        }
        
        .file-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
            width: 40px;
            text-align: center;
        }
        
        .file-info {
            flex: 1;
            min-width: 0;
        }
        
        .file-name {
            font-weight: 500;
            color: #333;
            margin-bottom: 0.25rem;
            word-break: break-all;
        }
        
        .file-meta {
            font-size: 0.85rem;
            color: #666;
            display: flex;
            gap: 1rem;
        }
        
        .file-actions {
            display: flex;
            gap: 0.5rem;
        }
        
        .action-btn {
            padding: 0.25rem 0.75rem;
            border: none;
            border-radius: 3px;
            font-size: 0.8rem;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            transition: background 0.2s;
        }
        
        .download-btn {
            background: #28a745;
            color: white;
        }
        
        .download-btn:hover {
            background: #218838;
        }
        
        .rename-btn {
            background: #ffc107;
            color: #212529;
        }
        
        .rename-btn:hover {
            background: #e0a800;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        /* 添加预览按钮样式 */
        .preview-btn {
            background: #17a2b8;
            color: white;
        }
        
        .preview-btn:hover {
            background: #138496;
        }
        
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #666;
        }
        
        .empty-state .icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .empty-state h3 {
            margin-bottom: 0.5rem;
            color: #333;
        }
        
        .empty-state a {
            color: #667eea;
            text-decoration: none;
        }
        
        @media (max-width: 768px) {
            .file-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .file-meta {
                flex-direction: column;
                gap: 0.25rem;
            }
            
            .file-actions {
                width: 100%;
                justify-content: flex-end;
            }
            
            .search-box {
                width: 100%;
                margin-top: 1rem;
            }
            
            .files-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            /* Added responsive styles for system info */
            .info-grid {
                grid-template-columns: 1fr;
            }
            
            .info-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .info-value {
                text-align: left;
            }
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
            <a href="index.php" class="active">文件列表</a>
            <a href="upload.php">文件上传</a>
            <a href="#" class="system-info-btn" onclick="toggleSystemInfo()">系统信息</a>
        </div>
        
        <!-- Added system information panel -->
        <div class="system-info-panel" id="systemInfoPanel" style="display: none;">
            <div class="system-info-header">
                <h2>系统信息</h2>
                <button class="close-btn" onclick="toggleSystemInfo()">×</button>
            </div>
            <div class="system-info-content">
                <div class="info-grid">
                    <div class="info-item">
                        <div class="info-label">PHP版本</div>
                        <div class="info-value"><?php echo phpversion(); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">系统时间</div>
                        <div class="info-value" id="currentTime"><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">服务器操作系统</div>
                        <div class="info-value"><?php echo php_uname('s') . ' ' . php_uname('r'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">文件上传路径</div>
                        <div class="info-value"><?php echo realpath(UPLOAD_DIR); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">最大上传大小</div>
                        <div class="info-value"><?php echo ini_get('upload_max_filesize'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">POST最大大小</div>
                        <div class="info-value"><?php echo ini_get('post_max_size'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">内存限制</div>
                        <div class="info-value"><?php echo ini_get('memory_limit'); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">磁盘总空间</div>
                        <div class="info-value"><?php echo formatBytes(disk_total_space('.')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">磁盘可用空间</div>
                        <div class="info-value"><?php echo formatBytes(disk_free_space('.')); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">加密状态</div>
                        <div class="info-value">
                            <span style="color: #28a745;">✓ 已启用 AES-256-CBC</span>
                        </div>
                    </div>
                    <!-- Added cache stats section -->
                    <div id="cacheStatsSection"></div>
                </div>
            </div>
        </div>
        
        <div class="stats">
            <div class="stat-card">
                <h3><?php echo $totalFiles; ?></h3>
                <p>总文件数</p>
            </div>
            <div class="stat-card">
                <h3><?php echo formatBytes($totalSize); ?></h3>
                <p>总存储空间</p>
            </div>
            <div class="stat-card">
                <h3><?php echo formatBytes(disk_free_space('.')); ?></h3>
                <p>可用空间</p>
            </div>
        </div>
        
        <div class="files-section">
            <div class="files-header">
                <h2>我的文件 🔒</h2>
                <input type="text" class="search-box" placeholder="搜索文件..." id="searchBox">
            </div>
            
            <div class="files-list" id="filesList">
                <?php if (empty($files)): ?>
                    <div class="empty-state">
                        <div class="icon">📁</div>
                        <h3>还没有文件</h3>
                        <p>开始 <a href="upload.php">上传文件</a> 来管理您的文档</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($files as $file): ?>
                        <div class="file-item" data-filename="<?php echo strtolower($file['name']); ?>">
                            <div class="file-icon">
                                <?php echo getFileIcon($file['extension']); ?>
                            </div>
                            <div class="file-info">
                                <div class="file-name"><?php echo htmlspecialchars($file['name']); ?> 🔒</div>
                                <div class="file-meta">
                                    <span><?php echo formatBytes($file['size']); ?></span>
                                    <span><?php echo date('Y-m-d H:i:s', $file['modified']); ?></span>
                                    <span><?php echo strtoupper($file['extension']); ?></span>
                                </div>
                            </div>
                            <div class="file-actions">
                                <?php if (canPreviewFile($file['extension'])): ?>
                                    <!-- 添加预览按钮 -->
                                    <a href="preview.php?file=<?php echo urlencode($file['name']); ?>" class="action-btn preview-btn">预览</a>
                                <?php endif; ?>
                                <a href="download.php?file=<?php echo urlencode($file['name']); ?>" class="action-btn download-btn">下载</a>
                                <button class="action-btn rename-btn" onclick="renameFile('<?php echo htmlspecialchars($file['name']); ?>')">重命名</button>
                                <button class="action-btn delete-btn" onclick="deleteFile('<?php echo htmlspecialchars($file['name']); ?>')">删除</button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // 搜索功能
        const searchBox = document.getElementById('searchBox');
        const fileItems = document.querySelectorAll('.file-item');
        
        searchBox.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            
            fileItems.forEach(item => {
                const filename = item.dataset.filename;
                if (filename.includes(searchTerm)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        });
        
        function toggleSystemInfo() {
            const panel = document.getElementById('systemInfoPanel');
            if (panel.style.display === 'none') {
                panel.style.display = 'block';
                updateTime();
                showCacheStats();
            } else {
                panel.style.display = 'none';
            }
        }
        
        function showCacheStats() {
            // 确保缓存管理器已加载
            if (typeof fileCacheManager === 'undefined') {
                // 动态加载缓存管理器
                const script = document.createElement('script');
                script.src = 'js/cache-manager.js';
                script.onload = function() {
                    displayCacheStats();
                };
                document.head.appendChild(script);
            } else {
                displayCacheStats();
            }
        }
        
        function displayCacheStats() {
            const stats = fileCacheManager.getCacheStats();
            
            // 查找系统信息面板
            const infoGrid = document.querySelector('.info-grid');
            
            // 检查是否已经添加了缓存信息
            if (!document.getElementById('cacheStatsSection')) {
                const cacheStatsHTML = `
                    <div id="cacheStatsSection">
                        <div class="info-item">
                            <div class="info-label">缓存文件数</div>
                            <div class="info-value">${stats.totalFiles} 个</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">缓存大小</div>
                            <div class="info-value">${stats.formattedSize} / ${stats.maxSize}</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">缓存使用率</div>
                            <div class="info-value">${stats.usagePercent}%</div>
                        </div>
                        <div class="info-item">
                            <div class="info-label">缓存操作</div>
                            <div class="info-value">
                                <button onclick="clearAllCache()" style="background: #dc3545; color: white; border: none; padding: 0.25rem 0.5rem; border-radius: 3px; font-size: 0.8rem; cursor: pointer;">清空缓存</button>
                            </div>
                        </div>
                    </div>
                `;
                infoGrid.insertAdjacentHTML('beforeend', cacheStatsHTML);
            } else {
                // 更新现有的缓存统计信息
                document.querySelector('#cacheStatsSection .info-item:nth-child(1) .info-value').textContent = `${stats.totalFiles} 个`;
                document.querySelector('#cacheStatsSection .info-item:nth-child(2) .info-value').textContent = `${stats.formattedSize} / ${stats.maxSize}`;
                document.querySelector('#cacheStatsSection .info-item:nth-child(3) .info-value').textContent = `${stats.usagePercent}%`;
            }
        }
        
        function clearAllCache() {
            if (confirm('确定要清空所有缓存吗？这将删除所有已缓存的文件内容。')) {
                if (typeof fileCacheManager !== 'undefined') {
                    fileCacheManager.clearAllCache();
                    alert('缓存已清空');
                    // 更新统计信息
                    if (document.getElementById('systemInfoPanel').style.display !== 'none') {
                        displayCacheStats();
                    }
                }
            }
        }
        
        function updateTime() {
            const timeElement = document.getElementById('currentTime');
            if (timeElement && document.getElementById('systemInfoPanel').style.display !== 'none') {
                const now = new Date();
                const chinaTime = new Date(now.getTime() + (8 * 60 * 60 * 1000) + (now.getTimezoneOffset() * 60 * 1000));
                timeElement.textContent = chinaTime.getFullYear() + '-' + 
                    String(chinaTime.getMonth() + 1).padStart(2, '0') + '-' + 
                    String(chinaTime.getDate()).padStart(2, '0') + ' ' + 
                    String(chinaTime.getHours()).padStart(2, '0') + ':' + 
                    String(chinaTime.getMinutes()).padStart(2, '0') + ':' + 
                    String(chinaTime.getSeconds()).padStart(2, '0');
                setTimeout(updateTime, 1000);
            }
        }
        
        // 重命名文件
        function renameFile(filename) {
            const newName = prompt('请输入新的文件名：', filename);
            if (newName && newName !== filename) {
                if (typeof fileCacheManager !== 'undefined') {
                    fileCacheManager.removeCache(filename);
                }
                window.location.href = `rename.php?old=${encodeURIComponent(filename)}&new=${encodeURIComponent(newName)}`;
            }
        }
        
        // 删除文件
        function deleteFile(filename) {
            if (confirm(`确定要删除文件 "${filename}" 吗？此操作不可撤销。`)) {
                if (typeof fileCacheManager !== 'undefined') {
                    fileCacheManager.removeCache(filename);
                }
                window.location.href = `delete.php?file=${encodeURIComponent(filename)}`;
            }
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            // 延迟加载缓存管理器，避免阻塞页面渲染
            setTimeout(function() {
                if (typeof fileCacheManager === 'undefined') {
                    const script = document.createElement('script');
                    script.src = 'js/cache-manager.js';
                    script.onload = function() {
                        // 预加载前几个文件
                        preloadTopFiles();
                    };
                    document.head.appendChild(script);
                } else {
                    preloadTopFiles();
                }
            }, 1000);
        });
        
        function preloadTopFiles() {
            // 预加载前3个文件（通常是最新的文件）
            const fileItems = document.querySelectorAll('.file-item');
            const maxPreload = Math.min(3, fileItems.length);
            
            for (let i = 0; i < maxPreload; i++) {
                const fileItem = fileItems[i];
                const filename = fileItem.querySelector('.file-name').textContent.replace(' 🔒', '');
                const modifiedText = fileItem.querySelector('.file-meta span:nth-child(2)').textContent;
                
                // 解析修改时间为时间戳
                const modifiedDate = new Date(modifiedText);
                const fileModified = Math.floor(modifiedDate.getTime() / 1000);
                
                // 异步预加载
                setTimeout(() => {
                    fileCacheManager.preloadFile(filename, fileModified);
                }, i * 500); // 错开加载时间
            }
        }
    </script>
</body>
</html>
