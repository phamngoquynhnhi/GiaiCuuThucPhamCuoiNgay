<?php
/**
 * TRANG XỬ LÝ HỦY ĐƠN HÀNG (student/huy_don.php)
 * Sinh viên được phép hủy đơn nếu cách giờ đóng cửa >= cancel_before_close_minutes (90p)
 * Hoàn 100% tiền và CỘNG LẠI kho túi bất ngờ
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
$sql = "SELECT o.*, b.name as bag_name, s.owner_id as shop_owner_id 
        FROM orders o 
        JOIN bags b ON o.bag_id = b.id 
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

if ($order['status'] !== 'cho_nhan') {
    die("<div style='font-family: Arial; padding: 20px; color: red;'>
        Đơn hàng không ở trạng thái Chờ nhận, không thể hủy!
        <p><a href='chi_tiet_don.php?id={$order_id}'>Quay lại</a></p>
    </div>");
}

// 2. Kiểm tra điều kiện thời gian hủy trước giờ đóng cửa
$cancel_before_close = (int)get_setting($conn, 'cancel_before_close_minutes', 90);
$now = time();
$ts_close = strtotime($order['close_at']);
$deadline = $ts_close - ($cancel_before_close * 60);

if ($now > $deadline) {
    die("<div style='font-family: Arial; padding: 20px; color: red;'>
        Đã quá hạn hủy đơn! Theo quy định, sinh viên chỉ được hủy đơn trước giờ đóng cửa ít nhất {$cancel_before_close} phút.
        <p><a href='chi_tiet_don.php?id={$order_id}'>Quay lại đơn hàng</a></p>
    </div>");
}

$price = (float)$order['price'];
$bag_id = (int)$order['bag_id'];
$owner_id = (int)$order['shop_owner_id'];

// 3. THỰC HIỆN HỦY ĐƠN TRONG TRANSACTION
mysqli_begin_transaction($conn);

try {
    // Bước A: Cập nhật trạng thái đơn sang huy_boi_khach kèm điều kiện cũ
    $stmt_up = mysqli_prepare($conn, "UPDATE orders SET status = 'huy_boi_khach' WHERE id = ? AND status = 'cho_nhan' AND user_id = ?");
    mysqli_stmt_bind_param($stmt_up, "ii", $order_id, $user_id);
    mysqli_stmt_execute($stmt_up);

    if (mysqli_affected_rows($conn) <= 0) {
        mysqli_rollback($conn);
        die("Không thể hủy đơn hàng do trạng thái đơn đã bị thay đổi!");
    }

    // Bước B: Hoàn 100% tiền cho sinh viên
    $stmt_refund = mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_refund, "di", $price, $user_id);
    mysqli_stmt_execute($stmt_refund);

    // Bước C: CỘNG LẠI kho túi bất ngờ
    $stmt_stock = mysqli_prepare($conn, "UPDATE bags SET stock = stock + 1 WHERE id = ?");
    mysqli_stmt_bind_param($stmt_stock, "i", $bag_id);
    mysqli_stmt_execute($stmt_stock);

    // Bước D: Ghi lịch sử biến động số dư (wallet_logs)
    $reason = "Hoàn tiền 100% do hủy đơn hàng #" . $order_id;
    $stmt_log = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_log, "idsi", $user_id, $price, $reason, $order_id);
    mysqli_stmt_execute($stmt_log);

    // Bước E: Gửi thông báo cho sinh viên và chủ quán
    $msg_sv = "Bạn đã hủy thành công đơn hàng #{$order_id}. " . format_money($price) . " đã được hoàn lại vào ví.";
    add_notification($conn, $user_id, $msg_sv);

    $msg_shop = "Đơn hàng #{$order_id} đã bị sinh viên hủy trước giờ quy định. Túi bất ngờ đã được tự động cộng trả lại vào kho.";
    add_notification($conn, $owner_id, $msg_shop);

    mysqli_commit($conn);
    header("Location: chi_tiet_don.php?id=" . $order_id);
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Lỗi khi hủy đơn: " . htmlspecialchars($e->getMessage()));
}
