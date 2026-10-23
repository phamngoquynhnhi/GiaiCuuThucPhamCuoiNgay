<?php
/**
 * TRANG NHẬP MÃ NHẬN HÀNG (shop/nhap_ma.php)
 * Dành cho Chủ quán nhập mã 6 ký tự do sinh viên đưa để bàn giao túi
 * Kiểm tra đầy đủ: đúng quán, đúng mã, chưa quá hạn, không trừ kho lại, không chuyển tiền ngay.
 */

$page_title = "Nhập mã nhận hàng";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Chủ quán
$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

// Lấy thông tin quán của chủ quán này
$stmt_shop = mysqli_prepare($conn, "SELECT id, name FROM shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_shop, "i", $owner_id);
mysqli_stmt_execute($stmt_shop);
$res_shop = mysqli_stmt_get_result($stmt_shop);
$shop = mysqli_fetch_assoc($res_shop);

if (!$shop) {
    header("Location: ho_so_quan.php");
    exit;
}

$shop_id = $shop['id'];
$error = '';
$success = '';
$order_detail = null;

// Xử lý khi chủ quán submit mã
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = strtoupper(trim($_POST['pickup_code'] ?? ''));

    if ($code === '') {
        $error = 'Vui lòng nhập mã nhận hàng 6 ký tự!';
    } else {
        // Tìm đơn hàng theo mã nhận
        $sql = "SELECT o.*, b.name as bag_name, s.name as shop_name, s.owner_id as shop_owner_id, u.name as student_name 
                FROM orders o 
                JOIN bags b ON o.bag_id = b.id 
                JOIN shops s ON o.shop_id = s.id 
                JOIN users u ON o.user_id = u.id 
                WHERE o.pickup_code = ? LIMIT 1";
        $stmt_find = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt_find, "s", $code);
        mysqli_stmt_execute($stmt_find);
        $res_find = mysqli_stmt_get_result($stmt_find);
        $order = mysqli_fetch_assoc($res_find);

        // KIỂM TRA TỪNG TRƯỜNG HỢP VỚI THÔNG BÁO TIẾNG VIỆT RÕ RÀNG
        if (!$order) {
            $error = "❌ Mã nhận hàng \"{$code}\" không tồn tại trong hệ thống! Vui lòng kiểm tra lại.";
        } elseif ((int)$order['shop_owner_id'] !== $owner_id) {
            $error = "❌ Mã nhận hàng này thuộc về quán khác! Không phải đơn đặt tại quán " . htmlspecialchars($shop['name']) . ".";
        } elseif ($order['status'] === 'da_nhan' || $order['status'] === 'hoan_thanh') {
            $time_picked = !empty($order['picked_up_at']) ? date('H:i d/m/Y', strtotime($order['picked_up_at'])) : '';
            $error = "⚠️ Đơn hàng này đã được nhận hàng trước đó (lúc {$time_picked})! Không thể nhận lại lần 2.";
        } elseif ($order['status'] === 'huy_boi_khach') {
            $error = "❌ Đơn hàng này đã bị khách hàng hủy trước đó.";
        } elseif ($order['status'] === 'huy_vang_mat') {
            $error = "❌ Đơn hàng này đã bị hệ thống hủy do khách vắng mặt quá giờ đóng cửa.";
        } elseif ($order['status'] === 'tranh_chap' || $order['status'] === 'hoan_tien_sv' || $order['status'] === 'chuyen_tien_quan') {
            $error = "⚠️ Đơn hàng này đang trong tình trạng tranh chấp khiếu nại.";
        } elseif (strtotime($order['close_at']) < time()) {
            $error = "❌ Đã quá giờ đóng cửa quán (" . date('H:i d/m/Y', strtotime($order['close_at'])) . ")! Đơn hàng đã hết hạn nhận.";
        } elseif ($order['status'] !== 'cho_nhan') {
            $error = "❌ Trạng thái đơn hàng không hợp lệ ({$order['status']}).";
        } else {
            // HỢP LỆ! BẮT ĐẦU TRANSACTION CHUYỂN TRẠNG THÁI SANG DA_NHAN
            $hold_minutes = (int)get_setting($conn, 'hold_minutes', 45);

            mysqli_begin_transaction($conn);

            // Cập nhật trạng thái kèm điều kiện trạng thái cũ 'cho_nhan'
            $stmt_up = mysqli_prepare($conn, "UPDATE orders 
                                              SET status = 'da_nhan', 
                                                  picked_up_at = NOW(), 
                                                  hold_until = DATE_ADD(NOW(), INTERVAL ? MINUTE) 
                                              WHERE id = ? AND status = 'cho_nhan'");
            mysqli_stmt_bind_param($stmt_up, "ii", $hold_minutes, $order['id']);
            mysqli_stmt_execute($stmt_up);

            if (mysqli_affected_rows($conn) > 0) {
                // Gửi thông báo cho sinh viên
                $msg_sv = "Quán {$shop['name']} đã bàn giao túi cho bạn (Đơn #{$order['id']}). Tiền đang được tạm giữ trong {$hold_minutes} phút để bạn kiểm tra đồ ăn.";
                add_notification($conn, $order['user_id'], $msg_sv);

                // Gửi thông báo cho chủ quán
                $msg_shop = "Đã xác nhận giao túi đơn #{$order['id']} thành công. Tiền sẽ được cộng vào ví sau {$hold_minutes} phút nếu không có khiếu nại.";
                add_notification($conn, $owner_id, $msg_shop);

                mysqli_commit($conn);

                $success = "✅ Xác nhận thành công! Đã bàn giao túi \"{$order['bag_name']}\" cho sinh viên {$order['student_name']}. Tiền (" . format_money($order['price']) . ") đang được tạm giữ trong {$hold_minutes} phút theo quy định.";
                $order_detail = $order;
            } else {
                mysqli_rollback($conn);
                $error = "Đơn hàng đã được thao tác bởi người khác hoặc trạng thái không còn là 'Chờ nhận'!";
            }
        }
    }
}

// Lấy danh sách 5 đơn vừa nhận gần đây của quán
$sql_recent = "SELECT o.*, b.name as bag_name, u.name as student_name 
               FROM orders o 
               JOIN bags b ON o.bag_id = b.id 
               JOIN users u ON o.user_id = u.id 
               WHERE o.shop_id = ? AND o.status = 'da_nhan' 
               ORDER BY o.picked_up_at DESC LIMIT 5";
$stmt_rec = mysqli_prepare($conn, $sql_recent);
mysqli_stmt_bind_param($stmt_rec, "i", $shop_id);
mysqli_stmt_execute($stmt_rec);
$res_recent = mysqli_stmt_get_result($stmt_rec);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box" style="max-width: 650px; margin: 20px auto;">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="font-size: 3rem; margin-bottom: 6px;">⚡</div>
        <h2 style="color: var(--accent); margin-bottom: 4px;">Nhập Mã Nhận Hàng Của Khách</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            Quán: <b><?php echo htmlspecialchars($shop['name']); ?></b>
        </p>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success" style="font-size: 1rem; line-height: 1.5;">
            <?php echo htmlspecialchars($success); ?>
        </div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="font-size: 1rem; line-height: 1.5;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <!-- FORM NHẬP MÃ -->
    <form method="POST" action="" style="background: var(--nen-2); border: 2px dashed var(--cam); border-radius: var(--radius-md); padding: 24px; margin-bottom: 24px; box-shadow: 0 0 16px var(--phat-cam);">
        <div class="form-group" style="margin-bottom: 16px;">
            <label style="font-size: 0.95rem; text-align: center; display: block;">Nhập mã nhận 6 ký tự sinh viên đưa:</label>
            <input type="text" name="pickup_code" class="form-control" autofocus required maxlength="10" placeholder="VD: 7A9K2M" 
                   style="font-family: var(--font-game); font-size: 1.8rem; text-align: center; font-weight: 900; letter-spacing: 4px; text-transform: uppercase; padding: 12px; color: var(--cam-sang);"
                   value="<?php echo htmlspecialchars($_POST['pickup_code'] ?? ''); ?>">
        </div>

        <button type="submit" class="btn btn-accent btn-block" style="padding: 14px; font-size: 1.1rem; font-weight: 800;">
            Xác Nhận Bàn Giao Túi
        </button>
    </form>

    <div style="background: var(--cam-mo); border-left: 4px solid var(--cam); border: 1px solid var(--cam); border-left-width: 4px; padding: 12px 16px; border-radius: 4px; font-size: 0.85rem; color: var(--chu); margin-bottom: 24px;">
        <b style="color: var(--cam-sang);">💡 Quy định bàn giao túi:</b><br>
        1. Khi nhập đúng mã, đơn hàng chuyển sang <b>Đã nhận</b>.<br>
        2. Hệ thống <b>tạm giữ tiền thêm 45 phút</b> để sinh viên mang về kiểm tra chất lượng.<br>
        3. Hết thời gian này (hoặc khi sinh viên bấm Hài lòng sớm), tiền sẽ tự động cộng vào ví của bạn.
    </div>

    <!-- DANH SÁCH CÁC ĐƠN ĐÃ BÀN GIAO GẦN ĐÂY -->
    <?php if (mysqli_num_rows($res_recent) > 0): ?>
        <h4 style="color: var(--text-main); margin-bottom: 10px;">📋 Đơn vừa nhận gần đây (Đang tạm giữ tiền):</h4>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Đơn</th>
                        <th>Khách</th>
                        <th>Túi</th>
                        <th>Tiền</th>
                        <th>Giữ tiền đến</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($r = mysqli_fetch_assoc($res_recent)): 
                        $ts_hold = strtotime($r['hold_until']);
                        $mins_left = max(0, ceil(($ts_hold - time()) / 60));
                    ?>
                        <tr>
                            <td>#<?php echo $r['id']; ?></td>
                            <td><b><?php echo htmlspecialchars($r['student_name']); ?></b></td>
                            <td><?php echo htmlspecialchars($r['bag_name']); ?></td>
                            <td style="color: var(--primary); font-weight: bold;"><?php echo format_money($r['price']); ?></td>
                            <td>
                                <?php if ($mins_left > 0): ?>
                                    <span class="status-badge status-info">Còn <?php echo $mins_left; ?> phút</span>
                                <?php else: ?>
                                    <span class="status-badge status-success">Đã hết hạn</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
