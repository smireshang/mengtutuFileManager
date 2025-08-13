<?php
// 数据库配置和基本设置
session_start();

// 简单的用户配置 (实际项目中应使用数据库)
$users = [
    'admin' => password_hash('admin123', PASSWORD_DEFAULT),
    'user' => password_hash('user123', PASSWORD_DEFAULT)
];

// 文件上传配置 - 根据部署情况选择合适的路径

// 方案1: 相对路径 (推荐) - 适用于大多数情况
define('UPLOAD_DIR', 'uploads/');

// 方案2: 如果项目在子目录，如 /filemanager/，使用相对路径
// define('UPLOAD_DIR', 'uploads/');

// 方案3: 使用绝对路径 (如果需要指定完整路径)
// define('UPLOAD_DIR', $_SERVER['DOCUMENT_ROOT'] . '/your-subdirectory/uploads/');

// 方案4: 动态获取当前目录路径
// define('UPLOAD_DIR', dirname(__FILE__) . '/uploads/');

// 方案5: 如果项目在子目录，需要完整的Web访问路径
// $base_path = str_replace($_SERVER['DOCUMENT_ROOT'], '', dirname(__FILE__));
// define('UPLOAD_DIR', rtrim($base_path, '/') . '/uploads/');
// define('UPLOAD_URL', rtrim($base_path, '/') . '/uploads/'); // 用于Web访问

define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB

// 创建上传目录
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// 检查用户是否已登录
function isLoggedIn() {
    return isset($_SESSION['username']);
}

// 验证用户登录
function authenticateUser($username, $password) {
    global $users;
    if (isset($users[$username]) && password_verify($password, $users[$username])) {
        $_SESSION['username'] = $username;
        return true;
    }
    return false;
}

// 登出用户
function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}

// 获取上传文件的Web访问URL
function getFileUrl($filename) {
    // 如果使用子目录，可能需要调整这个路径
    $script_path = dirname($_SERVER['SCRIPT_NAME']);
    $upload_url = rtrim($script_path, '/') . '/' . UPLOAD_DIR . $filename;
    return $upload_url;
}
?>
