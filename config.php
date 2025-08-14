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

define('MAX_FILE_SIZE', 50 * 1024 * 1024); // 50MB

define('ENCRYPTION_METHOD', 'AES-256-CBC');
define('ENCRYPTION_KEY', hash('sha256', $_SERVER['HTTP_HOST'] . 'file_manager_secret_key_2024'));

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

// 加载文件名映射
function loadFilenameMapping() {
    $mappingFile = UPLOAD_DIR . '.filename_mapping.json';
    if (file_exists($mappingFile)) {
        return json_decode(file_get_contents($mappingFile), true) ?: [];
    }
    return [];
}

// 生成加密文件名
function generateEncryptedFilename($filename) {
    return hash('md5', $filename . ENCRYPTION_KEY) . '.enc';
}

// 从加密文件名获取原始文件名映射
function getOriginalFilename($encryptedFilename) {
    // 这里需要一个映射文件来存储原始文件名和加密文件名的对应关系
    $mappingFile = UPLOAD_DIR . '.filename_mapping.json';
    if (file_exists($mappingFile)) {
        $mapping = json_decode(file_get_contents($mappingFile), true);
        return array_search($encryptedFilename, $mapping);
    }
    return false;
}

// 保存整个文件名映射数组
function saveFilenameMapping($mapping) {
    $mappingFile = UPLOAD_DIR . '.filename_mapping.json';
    
    // 检查目录是否可写
    if (!is_writable(UPLOAD_DIR)) {
        error_log("文件映射保存失败：上传目录不可写 - " . UPLOAD_DIR);
        return false;
    }
    
    // 尝试编码JSON
    $jsonData = json_encode($mapping, JSON_PRETTY_PRINT);
    if ($jsonData === false) {
        error_log("文件映射保存失败：JSON编码失败");
        return false;
    }
    
    // 尝试写入文件
    $result = file_put_contents($mappingFile, $jsonData);
    if ($result === false) {
        error_log("文件映射保存失败：无法写入文件 - " . $mappingFile);
        return false;
    }
    
    return true;
}

// 保存单个文件名映射
function addFilenameMapping($originalFilename, $encryptedFilename) {
    $mappingFile = UPLOAD_DIR . '.filename_mapping.json';
    $mapping = [];
    if (file_exists($mappingFile)) {
        $mapping = json_decode(file_get_contents($mappingFile), true) ?: [];
    }
    $mapping[$originalFilename] = $encryptedFilename;
    
    return saveFilenameMapping($mapping);
}

// 加密文件内容
function encryptFile($sourcePath, $destinationPath) {
    $data = file_get_contents($sourcePath);
    if ($data === false) {
        return false;
    }
    
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENCRYPTION_METHOD));
    $encrypted = openssl_encrypt($data, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    
    if ($encrypted === false) {
        return false;
    }
    
    // 将IV和加密数据一起存储
    $encryptedData = base64_encode($iv . $encrypted);
    return file_put_contents($destinationPath, $encryptedData) !== false;
}

// 解密文件内容
function decryptFile($encryptedFilePath) {
    $encryptedData = file_get_contents($encryptedFilePath);
    if ($encryptedData === false) {
        return false;
    }
    
    $data = base64_decode($encryptedData);
    $ivLength = openssl_cipher_iv_length(ENCRYPTION_METHOD);
    $iv = substr($data, 0, $ivLength);
    $encrypted = substr($data, $ivLength);
    
    $decrypted = openssl_decrypt($encrypted, ENCRYPTION_METHOD, ENCRYPTION_KEY, 0, $iv);
    return $decrypted;
}
?>
