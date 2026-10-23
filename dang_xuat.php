<?php
/**
 * TRANG ĐĂNG XUẤT (dang_xuat.php)
 * session_destroy() rồi chuyển về trang chủ
 */

require_once __DIR__ . '/config.php';

// Xóa tất cả các biến session
$_SESSION = [];

// Hủy cookie session nếu có
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Hủy phiên làm việc
session_destroy();

// Chuyển hướng về trang chủ
header("Location: " . BASE_URL . "/index.php");
exit;
