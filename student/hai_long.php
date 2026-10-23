<?php
/**
 * TRANG XÁC NHẬN "TÔI HÀI LÒNG" (student/hai_long.php)
 * Cho phép sinh viên chủ động nhả tiền sớm cho quán ăn thay vì chờ hết 45 phút
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$user = require_role($conn, ['student']);
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: don_hang.php");
    exit;
}

$order_id = (int)($_POST['order_id'] ?? 0);
if ($order_id <= 0) {
    die("Đơn hàng không hợp lệ!");
}

// 1. Lấy thông tin đơn hàng
$sql = "SELECT o.*, s.owner_id as shop_owner_id, s.name as shop_name 
        FROM orders o 
        JOIN shops s ON o.shop_id = s.id 
        WHERE o.id = ? AND o.user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);

if (!$order) {
    die("Không tìm thấy đơn hàng!");
}

if ($order['status'] !== 'da_nhan') {
    die("<div style='font-family: Arial; padding: 20px; color: red;'>
        Đơn hàng không ở trạng thái Đã nhận để xác nhận hài lòng!
        <p><a href='chi_tiet_don.php?id={$order_id}'>Quay lại</a></p>
    </div>");
}

$price = (float)$order['price'];
$owner_id = (int)$order['shop_owner_id'];

// 2. THỰC HIỆN CHUYỂN TIỀN CHO QUÁN TRONG TRANSACTION
mysqli_begin_transaction($conn);

try {
    // Cập nhật trạng thái đơn sang hoan_thanh kèm điều kiện trạng thái cũ da_nhan
    $stmt_up = mysqli_prepare($conn, "UPDATE orders SET status = 'hoan_thanh' WHERE id = ? AND status = 'da_nhan' AND user_id = ?");
    mysqli_stmt_bind_param($stmt_up, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt_up);

    if (mysqli_affected_rows($conn) <= 0) {
        mysqli_rollback($conn);
        die("Đơn hàng đã được hoàn thành hoặc có thay đổi trạng thái trước đó!");
    }

    // Chia tiền cho Quán và trích 10% hoa hồng cho Admin
    complete_order_payment($conn, $order_id, $price, $owner_id, $order['shop_name'], 'Sinh viên xác nhận hài lòng');

    // Gửi thông báo cảm ơn cho sinh viên
    $msg_sv = "Cảm ơn bạn đã xác nhận hài lòng với đơn hàng #{$order_id}! Đơn đã hoàn tất.";
    add_notification($conn, $user_id, $msg_sv);

    mysqli_commit($conn);
    header("Location: chi_tiet_don.php?id=" . $order_id . "&msg=released");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Lỗi xử lý xác nhận hài lòng: " . htmlspecialchars($e->getMessage()));
}
