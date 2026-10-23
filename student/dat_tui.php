<?php
/**
 * TRANG XỬ LÝ ĐẶT TÚI BẤT NGỜ (student/dat_tui.php)
 * Áp dụng Transaction, Trừ kho nguyên tử, Trừ tiền ảo và tạo mã nhận hàng ngẫu nhiên 6 ký tự
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền sinh viên
$user = require_role($conn, ['student']);
$user_id = $user['id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

$bag_id = (int)($_POST['bag_id'] ?? 0);
if ($bag_id <= 0) {
    die("Dữ liệu túi không hợp lệ!");
}

// 1. Lấy thông tin túi và quán
$sql_bag = "SELECT b.*, s.name as shop_name, s.owner_id as shop_owner_id, s.status as shop_status 
            FROM bags b 
            JOIN shops s ON b.shop_id = s.id 
            WHERE b.id = ? LIMIT 1";
$stmt_bag = mysqli_prepare($conn, $sql_bag);
mysqli_stmt_bind_param($stmt_bag, "i", $bag_id);
mysqli_stmt_execute($stmt_bag);
$res_bag = mysqli_stmt_get_result($stmt_bag);
$bag = mysqli_fetch_assoc($res_bag);

if (!$bag) {
    die("Không tìm thấy túi bất ngờ!");
}

// 2. Kiểm tra trạng thái quán đã được duyệt chưa
if ($bag['shop_status'] !== 'da_duyet') {
    die("<div style='font-family: Arial; padding: 20px; color: red;'>Quán này chưa được duyệt hoạt động!</div>");
}

// 3. Kiểm tra giờ mở bán và đóng cửa trên server PHP
$now = time();
$ts_open = strtotime($bag['open_at']);
$ts_close = strtotime($bag['close_at']);

if ($now < $ts_open) {
    die("<div style='font-family: Arial; padding: 20px; color: orange;'>
        Chưa tới giờ mở bán! Vui lòng quay lại lúc " . date('H:i d/m/Y', $ts_open) . ".
        <p><a href='" . BASE_URL . "/tui_chi_tiet.php?id={$bag_id}'>Quay lại</a></p>
    </div>");
}

if ($now > $ts_close) {
    die("<div style='font-family: Arial; padding: 20px; color: red;'>
        Túi đã quá giờ đóng cửa quán! Không thể đặt hàng.
        <p><a href='" . BASE_URL . "/index.php'>Quay lại</a></p>
    </div>");
}

// 4. Kiểm tra giới hạn số túi mỗi người (max_bags_per_user)
$max_bags = (int)get_setting($conn, 'max_bags_per_user', 2);
$stmt_limit = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM orders WHERE user_id = ? AND bag_id = ? AND status IN ('cho_nhan', 'da_nhan')");
mysqli_stmt_bind_param($stmt_limit, "ii", $user_id, $bag_id);
mysqli_stmt_execute($stmt_limit);
$res_limit = mysqli_stmt_get_result($stmt_limit);
$row_limit = mysqli_fetch_assoc($res_limit);

if ($row_limit['cnt'] >= $max_bags) {
    die("<div style='font-family: Arial; padding: 20px; color: red;'>
        Bạn đã đặt tối đa {$max_bags} túi loại này đang chờ nhận! Vui lòng nhường cơ hội cho các bạn sinh viên khác.
        <p><a href='" . BASE_URL . "/student/don_hang.php'>Xem đơn hàng của bạn</a></p>
    </div>");
}

$price = (float)$bag['web_price'];

// 5. THỰC HIỆN ĐẶT HÀNG TRONG TRANSACTION TOÀN VẸN
mysqli_begin_transaction($conn);

try {
    // Bước A: Trừ kho nguyên tử (stock >= 1)
    $stmt_stock = mysqli_prepare($conn, "UPDATE bags SET stock = stock - 1 WHERE id = ? AND stock >= 1");
    mysqli_stmt_bind_param($stmt_stock, "i", $bag_id);
    mysqli_stmt_execute($stmt_stock);

    if (mysqli_affected_rows($conn) <= 0) {
        // Hết hàng
        mysqli_rollback($conn);
        die("<div style='font-family: Arial; padding: 20px; color: red;'>
            Rất tiếc! Túi bất ngờ này vừa được người khác mua hết.
            <p><a href='" . BASE_URL . "/index.php'>Quay lại trang chủ</a></p>
        </div>");
    }

    // Bước B: Trừ tiền ảo của sinh viên (balance >= price)
    $stmt_balance = mysqli_prepare($conn, "UPDATE users SET balance = balance - ? WHERE id = ? AND balance >= ?");
    mysqli_stmt_bind_param($stmt_balance, "did", $price, $user_id, $price);
    mysqli_stmt_execute($stmt_balance);

    if (mysqli_affected_rows($conn) <= 0) {
        // Số dư không đủ
        mysqli_rollback($conn);
        die("<div style='font-family: Arial; padding: 20px; color: red;'>
            Số dư ví không đủ để thanh toán túi này!
            <p><a href='" . BASE_URL . "/student/vi_tien.php'>Nạp thêm tiền vào ví</a></p>
        </div>");
    }

    // Bước C: Tạo mã nhận hàng 6 ký tự không trùng lặp
    $pickup_code = generate_pickup_code($conn);

    // Bước D: Tạo đơn hàng mới (trạng thái ban đầu: cho_nhan)
    // Lưu ý: Tiền được giữ trong đơn, CHƯA cộng cho chủ quán
    $stmt_order = mysqli_prepare($conn, "INSERT INTO orders (user_id, bag_id, shop_id, price, status, pickup_code, close_at, created_at) VALUES (?, ?, ?, ?, 'cho_nhan', ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_order, "iiidss", $user_id, $bag_id, $bag['shop_id'], $price, $pickup_code, $bag['close_at']);
    mysqli_stmt_execute($stmt_order);
    $order_id = mysqli_insert_id($conn);

    // Bước E: Ghi lịch sử biến động số dư (wallet_logs)
    $reason_user = "Thanh toán đặt túi bất ngờ #" . $bag_id . " (" . $bag['name'] . ")";
    $neg_price = -$price;
    $stmt_log = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_log, "idsi", $user_id, $neg_price, $reason_user, $order_id);
    mysqli_stmt_execute($stmt_log);

    // Bước F: Gửi thông báo cho sinh viên và chủ quán
    $msg_sv = "Đặt túi thành công! Đơn hàng #{$order_id}. Mã nhận hàng của bạn là: {$pickup_code}. Vui lòng đến lấy trước " . date('H:i d/m', $ts_close);
    add_notification($conn, $user_id, $msg_sv);

    $msg_quan = "Quán bạn có đơn đặt túi mới #{$order_id}! Giá trị: " . format_money($price) . ". Mã nhận: {$pickup_code}";
    add_notification($conn, $bag['shop_owner_id'], $msg_quan);

    // Hoàn tất giao dịch
    mysqli_commit($conn);

    // Chuyển hướng sang trang chi tiết đơn hàng
    header("Location: " . BASE_URL . "/student/chi_tiet_don.php?id=" . $order_id . "&msg=order_success");
    exit;

} catch (Exception $e) {
    mysqli_rollback($conn);
    die("Lỗi hệ thống: " . htmlspecialchars($e->getMessage()));
}
