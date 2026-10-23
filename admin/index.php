<?php
/**
 * TRANG ĐIỀU HƯỚNG QUẢN TRỊ (admin/index.php)
 * Tự động chuyển hướng về trang Tổng quan Quản trị viên chính (admin/tong_quan.php)
 */

require_once __DIR__ . '/../config.php';
header("Location: " . BASE_URL . "/admin/tong_quan.php");
exit;
