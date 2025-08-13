<?php
require_once 'auth_check.php';

// 获取文件列表
function getFileList() {
    $files = [];
    if (is_dir(UPLOAD_DIR)) {
        $fileList = scandir(UPLOAD_DIR);
        foreach ($fileList as $file) {
            if ($file != '.' && $file != '..' && is_file(UPLOAD_DIR . $file)) {
                $filepath = UPLOAD_DIR . $file;
                $files[] = [
                    'name' => $file,
                    'size' => filesize($filepath),
                    'modified' => filemtime($filepath),
                    'type' => mime_content_type($filepath),
                    'extension' => strtolower(pathinfo($file, PATHINFO_EXTENSION))
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
                <h2>我的文件</h2>
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
                                <div class="file-name"><?php echo htmlspecialchars($file['name']); ?></div>
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
