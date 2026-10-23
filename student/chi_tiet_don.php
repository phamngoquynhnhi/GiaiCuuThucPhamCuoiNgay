<?php
/**
 * TRANG CHI TIẾT ĐƠN HÀNG (student/chi_tiet_don.php)
 * Hiển thị mã nhận hàng to rõ, đếm ngược thời gian giữ tiền / hủy đơn, nút Tôi hài lòng và Khiếu nại
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền sinh viên
$user = require_role($conn, ['student']);
$user_id = $user['id'];

$order_id = (int)($_GET['id'] ?? 0);
if ($order_id <= 0) {
    header("Location: don_hang.php");
    exit;
}

// Lấy thông tin đơn hàng đầy đủ
$sql = "SELECT o.*, b.name as bag_name, b.description as bag_desc, b.original_price, b.pickup_start, b.pickup_end, b.image as bag_image,
               s.name as shop_name, s.address as shop_address, s.phone as shop_phone, s.district as shop_district, s.type as shop_type, s.violation_count
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
    die("<div style='font-family: Arial; padding: 20px; color: red;'>Không tìm thấy đơn hàng hoặc bạn không sở hữu đơn này!</div>");
}

$page_title = "Chi tiết đơn hàng #" . $order['id'];
$st_info = get_order_status_info($order['status']);

// Đọc cấu hình thời gian
$cancel_before_close = (int)get_setting($conn, 'cancel_before_close_minutes', 90);
$hold_minutes = (int)get_setting($conn, 'hold_minutes', 45);

$now = time();
$ts_close = strtotime($order['close_at']);
$ts_cancel_deadline = $ts_close - ($cancel_before_close * 60);
$can_cancel = ($order['status'] === 'cho_nhan' && $now <= $ts_cancel_deadline);

$ts_hold_until = !empty($order['hold_until']) ? strtotime($order['hold_until']) : null;
$in_hold_period = ($order['status'] === 'da_nhan' && $ts_hold_until && $now < $ts_hold_until);

require_once __DIR__ . '/../header.php';
?>

<div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
    <a href="<?php echo BASE_URL; ?>/index.php">Trang chủ</a> &gt; 
    <a href="<?php echo BASE_URL; ?>/student/don_hang.php">Đơn của tôi</a> &gt; 
    <span style="color: var(--text-main); font-weight: 600;">Đơn #<?php echo $order['id']; ?></span>
</div>

<div class="content-box" style="max-width: 850px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">📦 Chi Tiết Đơn Hàng #<?php echo $order['id']; ?></h2>
            <div style="font-size: 0.85rem; color: #64748b;">
                Đặt lúc: <?php echo date('H:i:s d/m/Y', strtotime($order['created_at'])); ?>
            </div>
        </div>

        <div>
            <span class="<?php echo $st_info['class']; ?>" style="font-size: 0.95rem; padding: 6px 16px;">
                <?php echo htmlspecialchars($st_info['name']); ?>
            </span>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'order_success'): ?>
            <div class="alert alert-success">
                🎉 <b>Đặt túi thành công!</b> Mã nhận hàng của bạn đã được tạo bên dưới. Tiền đang được hệ thống tạm giữ an toàn.
            </div>
        <?php elseif ($_GET['msg'] === 'released'): ?>
            <div class="alert alert-success">
                ✅ <b>Cảm ơn bạn!</b> Bạn đã xác nhận hài lòng sớm. Tiền đơn hàng đã được cộng trực tiếp vào ví của quán.
            </div>
        <?php elseif ($_GET['msg'] === 'dispute_sent'): ?>
            <div class="alert alert-warning">
                ⚠️ <b>Đã gửi báo cáo khiếu nại!</b> Đơn hàng đã chuyển sang trạng thái tranh chấp. Tiền đang đóng băng để bảo vệ bạn.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- KHỐI MÃ NHẬN HÀNG LỚN NỔI BẬT -->
    <div class="pickup-box">
        <span style="font-size: 0.9rem; color: var(--cam-sang); font-weight: 800; letter-spacing: 0.5px; display: block; margin-bottom: 6px;">
            🔑 MÃ NHẬN HÀNG TẠI QUÁN
        </span>
        <div class="pickup-code">
            <?php echo htmlspecialchars($order['pickup_code']); ?>
        </div>
        <p style="font-size: 0.85rem; color: var(--chu-phu); margin-top: 6px;">
            Hãy đưa mã này cho nhân viên quán khi đến lấy túi. Chủ quán sẽ nhập mã vào hệ thống để bàn giao cho bạn.
        </p>
    </div>

    <!-- KHỐI BÁO THỜI GIAN THEO TỪNG TRẠNG THÁI -->
    <?php if ($order['status'] === 'cho_nhan'): ?>
        <div style="background: var(--nen-3); border: 1px solid var(--canh-bao); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 20px;">
            <h4 style="color: var(--canh-bao); margin-bottom: 6px;">⏳ Đang chờ bạn đến lấy túi</h4>
            <p style="font-size: 0.9rem; color: var(--chu); margin-bottom: 10px;">
                • Khung giờ nhận: <b><?php echo date('H:i', strtotime($order['pickup_start'])) . ' - ' . date('H:i', strtotime($order['pickup_end'])); ?></b><br>
                • Giờ đóng cửa quán (Hạn chót): <b><?php echo date('H:i d/m/Y', $ts_close); ?></b>
            </p>

            <?php if ($can_cancel): 
                $mins_to_cancel = ceil(($ts_cancel_deadline - $now) / 60);
            ?>
                <div style="padding: 10px; background: var(--nen-2); border-radius: 6px; border: 1px dashed var(--canh-bao); margin-bottom: 12px; font-size: 0.88rem; color: var(--chu);">
                    💡 <b>Quyền hủy đơn:</b> Bạn còn khoảng <b><?php echo $mins_to_cancel; ?> phút</b> nữa để hủy đơn và nhận lại 100% tiền (phải hủy trước giờ đóng cửa ít nhất <?php echo $cancel_before_close; ?> phút, tức trước <?php echo date('H:i', $ts_cancel_deadline); ?>).
                </div>
                <form method="POST" action="huy_don.php" onsubmit="return confirm('Bạn có chắc chắn muốn hủy đơn hàng này không? 100% tiền sẽ được hoàn vào ví của bạn.');">
                    <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                    <button type="submit" class="btn btn-outline" style="color: var(--loi); border-color: var(--loi);">
                        ❌ Tôi muốn hủy đơn này (Hoàn 100% tiền)
                    </button>
                </form>
            <?php else: ?>
                <div style="padding: 10px; background: rgba(255,90,95,0.15); border: 1px solid var(--loi); border-radius: 6px; color: var(--loi); font-size: 0.85rem;">
                    ⚠️ <b>Không thể hủy đơn:</b> Hiện tại đã cách giờ đóng cửa dưới <?php echo $cancel_before_close; ?> phút. Theo quy định, quán đã chuẩn bị đóng gói túi, bạn không thể hủy đơn tự do. Nếu không đến lấy, đơn sẽ chuyển thành "Vắng mặt" và tiền bồi thường cho quán.
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($order['status'] === 'da_nhan'): ?>
        <div style="background: var(--nen-3); border: 1px solid var(--thanh-cong); border-radius: var(--radius-sm); padding: 18px; margin-bottom: 20px;">
            <h4 style="color: var(--thanh-cong); margin-bottom: 6px;">🎉 Bạn đã nhận túi thành công!</h4>
            <p style="font-size: 0.88rem; color: var(--chu-phu); margin-bottom: 12px;">
                Đã nhận lúc: <?php echo date('H:i:s d/m/Y', strtotime($order['picked_up_at'])); ?>.
            </p>

            <?php if ($in_hold_period): 
                $mins_hold_left = ceil(($ts_hold_until - $now) / 60);
            ?>
                <div style="background: var(--nen-2); border: 1px solid var(--thanh-cong); border-radius: 8px; padding: 14px; margin-bottom: 16px;">
                    <div style="font-weight: 700; color: var(--thanh-cong); margin-bottom: 4px;">
                        🛡️ Hệ thống đang tạm giữ tiền bảo vệ bạn: Còn <?php echo $mins_hold_left; ?> phút (Đến <?php echo date('H:i', $ts_hold_until); ?>)
                    </div>
                    <p style="font-size: 0.85rem; color: var(--chu); margin: 0;">
                        Quán chưa được cộng tiền ngay. Bạn có thời gian mang túi về kiểm tra chất lượng. Nếu hết <?php echo $mins_hold_left; ?> phút mà bạn không khiếu nại, tiền sẽ tự động chuyển cho quán.
                    </p>
                </div>

                <!-- 2 NÚT HÀNH ĐỘNG QUAN TRỌNG -->
                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                    <!-- Nút 1: Tôi hài lòng -->
                    <form method="POST" action="hai_long.php" style="margin: 0;">
                        <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                        <button type="submit" class="btn btn-success" style="padding: 10px 18px;" onclick="return confirm('Bạn xác nhận hài lòng với túi bất ngờ này? Tiền sẽ được chuyển ngay cho quán.');">
                            ✅ Tôi hài lòng (Nhả tiền sớm cho quán)
                        </button>
                    </form>

                    <!-- Nút 2: Báo cáo gian lận -->
                    <a href="khieu_nai.php?order_id=<?php echo $order['id']; ?>" class="btn btn-danger" style="padding: 10px 18px;">
                        ⚠️ Báo cáo gian lận / Khiếu nại chất lượng
                    </a>
                </div>
            <?php else: ?>
                <div class="alert alert-info" style="margin: 0;">
                    Đã hết thời gian giữ tiền tạm thời. Đơn hàng đang được hệ thống chuyển sang trạng thái hoàn tất.
                </div>
            <?php endif; ?>
        </div>

    <?php elseif ($order['status'] === 'tranh_chap'): ?>
        <div class="alert alert-danger">
            <b>⚖️ Đơn hàng đang có khiếu nại tranh chấp:</b><br>
            Tiền đơn hàng (<?php echo format_money($order['price']); ?>) đang được đóng băng an toàn trong hệ thống. Quán có 48 giờ để phản đối, sau đó Quản trị viên sẽ xem xét bằng chứng và đưa ra phán quyết cuối cùng.
            <div style="margin-top: 8px;">
                <a href="khieu_nai.php?order_id=<?php echo $order['id']; ?>" class="btn btn-sm btn-outline">Xem chi tiết khiếu nại</a>
            </div>
        </div>

    <?php elseif ($order['status'] === 'hoan_tien_sv'): ?>
        <div class="alert alert-success">
            <b>🎉 Khiếu nại thành công:</b> Quản trị viên đã chấp nhận khiếu nại của bạn. 100% số tiền <b><?php echo format_money($order['price']); ?></b> đã được hoàn trả về ví của bạn. Quán đã bị ghi nhận 1 lần vi phạm.
        </div>

    <?php elseif ($order['status'] === 'chuyen_tien_quan'): ?>
        <div class="alert alert-warning">
            <b>Phán quyết Admin:</b> Admin đã bác khiếu nại của bạn và chuyển tiền cho quán. Tài khoản của bạn bị ghi nhận 1 cảnh cáo.
        </div>

    <?php elseif ($order['status'] === 'huy_boi_khach'): ?>
        <div class="alert alert-info">
            <b>Đã hủy đơn:</b> Bạn đã hủy đơn này đúng quy định trước giờ đóng cửa. Tiền đã được hoàn lại đầy đủ vào ví của bạn.
        </div>

    <?php elseif ($order['status'] === 'huy_vang_mat'): ?>
        <div class="alert alert-danger">
            <b>Hủy do vắng mặt:</b> Bạn đã không đến nhận túi trước giờ đóng cửa của quán. Tiền đơn hàng đã được chuyển cho quán làm phí bồi thường.
        </div>
    <?php endif; ?>

    <!-- THÔNG TIN TÚI VÀ QUÁN ĂN -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-top: 24px; border-top: 1px solid var(--border-color); padding-top: 20px;" class="info-grid">
        <div>
            <h4 style="color: var(--text-main); margin-bottom: 10px;">🛍️ Thông tin túi:</h4>
            <p style="margin-bottom: 6px;"><b>Tên:</b> <?php echo htmlspecialchars($order['bag_name']); ?></p>
            <p style="margin-bottom: 6px;"><b>Mô tả:</b> <?php echo htmlspecialchars($order['bag_desc'] ?? 'Không có'); ?></p>
            <p style="margin-bottom: 6px;"><b>Giá gốc niêm yết:</b> <span style="text-decoration: line-through; color: var(--chu-phu);"><?php echo format_money($order['original_price']); ?></span></p>
            <p style="margin-bottom: 6px;"><b>Giá web thanh toán:</b> <b style="color: var(--cam-sang); font-size: 1.1rem; font-family: var(--font-game);"><?php echo format_money($order['price']); ?></b></p>
        </div>

        <div>
            <h4 style="color: var(--text-main); margin-bottom: 10px;">🏪 Địa chỉ nhận hàng:</h4>
            <p style="margin-bottom: 6px;"><b>Quán:</b> <?php echo htmlspecialchars($order['shop_name']); ?></p>
            <p style="margin-bottom: 6px;"><b>Địa chỉ:</b> <?php echo htmlspecialchars($order['shop_address']); ?>, Q. <?php echo htmlspecialchars($order['shop_district']); ?></p>
            <?php if (!empty($order['shop_phone'])): ?>
                <p style="margin-bottom: 6px;"><b>Hotline quán:</b> <a href="tel:<?php echo htmlspecialchars($order['shop_phone']); ?>" style="color: var(--cam-sang); font-weight: bold; text-decoration: underline;">📞 <?php echo htmlspecialchars($order['shop_phone']); ?></a></p>
            <?php endif; ?>
            <p style="margin-bottom: 6px;"><b>Loại quán:</b> <?php echo htmlspecialchars($order['shop_type']); ?></p>
            <p style="margin-bottom: 6px;"><b>Hạn chót đóng cửa:</b> <?php echo date('H:i d/m/Y', $ts_close); ?></p>

            <!-- BẢN ĐỒ VỊ TRÍ QUÁN ĂN ĐỂ SINH VIÊN ĐẾN LẤY TÚI -->
            <?php $order_map_query = urlencode($order['shop_address'] . ', ' . $order['shop_district'] . ', Đà Nẵng'); ?>
            <div style="margin-top: 12px;">
                <div style="border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border-color); height: 160px; margin-bottom: 8px;">
                    <iframe 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        loading="lazy" 
                        allowfullscreen 
                        src="https://maps.google.com/maps?q=<?php echo $order_map_query; ?>&t=&z=15&ie=UTF8&iwloc=&output=embed">
                    </iframe>
                </div>
                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $order_map_query; ?>" target="_blank" class="btn btn-sm btn-outline" style="font-size: 0.82rem; display: inline-flex; align-items: center; gap: 4px; padding: 5px 12px;">
                    🧭 Chỉ đường đến quán trên Google Maps
                </a>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .info-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
