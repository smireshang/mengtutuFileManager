<?php
// 认证检查中间件
require_once 'config.php';

if (!isLoggedIn()) {
    header('Location: login.php');
    exit;
}
?>
