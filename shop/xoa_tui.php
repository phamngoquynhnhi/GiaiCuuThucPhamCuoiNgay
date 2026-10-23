<?php
/**
 * TRANG XÓA TÚI BẤT NGỜ (shop/xoa_tui.php)
 * Chỉ cho phép xóa túi khi chưa có sinh viên nào đặt hàng
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

$bag_id = (int)($_GET['id'] ?? 0);
if ($bag_id <= 0) {
    header("Location: quan_ly_tui.php");
    exit;
}

// Lấy thông tin quán
$stmt_shop = mysqli_prepare($conn, "SELECT id FROM shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_shop, "i", $owner_id);
mysqli_stmt_execute($stmt_shop);
$res_shop = mysqli_stmt_get_result($stmt_shop);
$shop = mysqli_fetch_assoc($res_shop);

if (!$shop) {
    header("Location: ho_so_quan.php");
    exit;
}

$shop_id = $shop['id'];

// Kiểm tra xem túi có đơn hàng chưa
$stmt_cnt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM orders WHERE bag_id = ?");
mysqli_stmt_bind_param($stmt_cnt, "i", $bag_id);
mysqli_stmt_execute($stmt_cnt);
$res_cnt = mysqli_stmt_get_result($stmt_cnt);
$row = mysqli_fetch_assoc($res_cnt);

if ($row['cnt'] > 0) {
    // Đã có đơn hàng, không được xóa!
    header("Location: quan_ly_tui.php?msg=cannot_delete");
    exit;
}

// Xóa túi
$stmt_del = mysqli_prepare($conn, "DELETE FROM bags WHERE id = ? AND shop_id = ?");
mysqli_stmt_bind_param($stmt_del, "ii", $bag_id, $shop_id);
mysqli_stmt_execute($stmt_del);

header("Location: quan_ly_tui.php?msg=deleted");
exit;
