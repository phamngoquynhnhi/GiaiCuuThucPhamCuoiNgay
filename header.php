<?php
/**
 * PHẦN ĐẦU TRANG (header.php)
 * Menu điều hướng theo từng vai trò (Khách, Sinh viên, Chủ quán, Quản trị viên)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Tự động kiểm tra và chuyển trạng thái các đơn hàng đến hạn
check_orders($conn);

// Lấy thông tin người dùng đang đăng nhập (nếu có)
$current_user = get_current_user_data($conn);
$unread_count = 0;
if ($current_user) {
    $unread_count = get_unread_notifications_count($conn, $current_user['id']);
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . ' - ' : ''; ?>Giải Cứu Thực Phẩm Đà Nẵng</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Baloo+2:wght@500;600;700;800&family=Be+Vietnam+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo BASE_URL; ?>/css/style.css">
</head>
<body>

<?php
$cur_file = basename($_SERVER['PHP_SELF']);
?>

<!-- THANH THÔNG BÁO TÁC ĐỘNG MÔI TRƯỜNG & MARKETING DEAL HOT -->
<div class="top-announcement">
    <div class="top-announcement-inner">
        <span>🔥 <b>SĂN DEAL ĐÊM NAY:</b> Hơn <b>1.820+</b> bữa ăn ngon đã được giải cứu tại Đà Nẵng • Tiết kiệm 50% chi phí ăn uống!</span>
        <span class="top-announcement-right">⏰ Khung giờ vàng nhận túi: 19h30 - 22h30 • TP. Đà Nẵng</span>
    </div>
</div>

<header class="site-header">
    <div class="header-container">
        <!-- Logo thương hiệu -->
        <a href="<?php echo BASE_URL; ?>/index.php" class="brand-logo">
            <span class="logo-icon">🌱</span>
            <span class="logo-text">EcoRescue</span>
            <span class="tag">Đà Nẵng</span>
        </a>

        <!-- Menu chính theo vai trò -->
        <nav class="main-nav">
            <a href="<?php echo BASE_URL; ?>/index.php" class="nav-link <?php echo ($cur_file === 'index.php') ? 'active' : ''; ?>">🏠 Trang chủ</a>

            <?php if (!$current_user): ?>
                <!-- Menu cho Khách vãng lai -->
                <a href="<?php echo BASE_URL; ?>/dang_nhap.php" class="nav-link <?php echo ($cur_file === 'dang_nhap.php') ? 'active' : ''; ?>">🔑 Đăng nhập</a>
                <a href="<?php echo BASE_URL; ?>/dang_ky.php" class="nav-link <?php echo ($cur_file === 'dang_ky.php') ? 'active' : ''; ?>">📝 Đăng ký</a>
                <a href="<?php echo BASE_URL; ?>/tao_du_lieu_mau.php" class="nav-link <?php echo ($cur_file === 'tao_du_lieu_mau.php') ? 'active' : ''; ?>">🌱 Dữ liệu demo</a>
            
            <?php elseif ($current_user['role'] === 'student' || $current_user['role'] === 'user'): ?>
                <!-- Menu cho Sinh viên / Người dùng -->
                <a href="<?php echo BASE_URL; ?>/student/don_hang.php" class="nav-link <?php echo ($cur_file === 'don_hang.php' || $cur_file === 'chi_tiet_don.php') ? 'active' : ''; ?>">🛍️ Đơn của tôi</a>
                <a href="<?php echo BASE_URL; ?>/student/vi_tien.php" class="nav-link <?php echo ($cur_file === 'vi_tien.php') ? 'active' : ''; ?>">
                    💰 Ví: <b><?php echo format_money($current_user['balance']); ?></b>
                </a>
                <a href="<?php echo BASE_URL; ?>/student/ho_so.php" class="nav-link <?php echo ($cur_file === 'ho_so.php') ? 'active' : ''; ?>">⚙️ Cài đặt hồ sơ</a>
                <a href="<?php echo BASE_URL; ?>/student/thong_bao.php" class="nav-link <?php echo ($cur_file === 'thong_bao.php') ? 'active' : ''; ?>">
                    🔔 Thông báo
                    <?php if ($unread_count > 0): ?>
                        <span class="notif-pill"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>

            <?php elseif ($current_user['role'] === 'shop_owner'): ?>
                <!-- Menu cho Chủ quán -->
                <a href="<?php echo BASE_URL; ?>/shop/nhap_ma.php" class="nav-link <?php echo ($cur_file === 'nhap_ma.php') ? 'active' : ''; ?>">
                    ⚡ Nhập mã nhận
                </a>
                <a href="<?php echo BASE_URL; ?>/shop/quan_ly_tui.php" class="nav-link <?php echo ($cur_file === 'quan_ly_tui.php' || $cur_file === 'them_tui.php' || $cur_file === 'sua_tui.php') ? 'active' : ''; ?>">📦 Quản lý túi</a>
                <a href="<?php echo BASE_URL; ?>/shop/don_hang.php" class="nav-link <?php echo ($cur_file === 'don_hang.php') ? 'active' : ''; ?>">📋 Đơn hàng</a>
                <a href="<?php echo BASE_URL; ?>/shop/doanh_thu.php" class="nav-link <?php echo ($cur_file === 'doanh_thu.php') ? 'active' : ''; ?>">
                    💵 Doanh thu: <b><?php echo format_money($current_user['balance']); ?></b>
                </a>
                <a href="<?php echo BASE_URL; ?>/shop/ho_so_quan.php" class="nav-link <?php echo ($cur_file === 'ho_so_quan.php') ? 'active' : ''; ?>">⚙️ Cài đặt quán</a>
                <a href="<?php echo BASE_URL; ?>/student/thong_bao.php" class="nav-link <?php echo ($cur_file === 'thong_bao.php') ? 'active' : ''; ?>">
                    🔔 Thông báo
                    <?php if ($unread_count > 0): ?>
                        <span class="notif-pill"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                </a>

            <?php elseif ($current_user['role'] === 'admin'): ?>
                <!-- Menu cho Quản trị viên -->
                <a href="<?php echo BASE_URL; ?>/admin/tong_quan.php" class="nav-link <?php echo ($cur_file === 'tong_quan.php') ? 'active' : ''; ?>">📊 Tổng quan</a>
                <a href="<?php echo BASE_URL; ?>/admin/duyet_quan.php" class="nav-link <?php echo ($cur_file === 'duyet_quan.php') ? 'active' : ''; ?>">🏪 Duyệt quán</a>
                <a href="<?php echo BASE_URL; ?>/admin/tranh_chap.php" class="nav-link <?php echo ($cur_file === 'tranh_chap.php') ? 'active' : ''; ?>">⚖️ Tranh chấp</a>
                <a href="<?php echo BASE_URL; ?>/admin/nguoi_dung.php" class="nav-link <?php echo ($cur_file === 'nguoi_dung.php') ? 'active' : ''; ?>">👥 Người dùng</a>
                <a href="<?php echo BASE_URL; ?>/admin/cai_dat.php" class="nav-link <?php echo ($cur_file === 'cai_dat.php') ? 'active' : ''; ?>">⚙️ Cài đặt</a>
                <a href="<?php echo BASE_URL; ?>/admin/kiem_tra_tien.php" class="nav-link <?php echo ($cur_file === 'kiem_tra_tien.php') ? 'active' : ''; ?>">🛡️ Kiểm tra ví</a>
            <?php endif; ?>

            <?php if ($current_user): ?>
                <!-- Thông tin user và nút đăng xuất -->
                <div class="user-badge-wrap" style="margin-left: 8px;">
                    <span class="role-tag"><?php echo htmlspecialchars($current_user['role']); ?></span>
                    <span style="font-weight: 600; font-size: 0.88rem; max-width: 130px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo htmlspecialchars($current_user['name']); ?></span>
                    <a href="<?php echo BASE_URL; ?>/dang_xuat.php" class="btn btn-sm btn-outline" style="border-color: var(--loi); color: var(--loi); padding: 4px 10px;">Thoát</a>
                </div>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="main-wrapper">
