<?php
/**
 * TRANG CHI TIẾT TÚI BẤT NGỜ (tui_chi_tiet.php)
 * Hiển thị đầy đủ thông tin túi, giờ mở bán, giá và nút đặt hàng
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$bag_id = (int)($_GET['id'] ?? 0);
if ($bag_id <= 0) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Truy vấn thông tin túi cùng thông tin quán
$sql = "SELECT b.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone, s.district as shop_district, 
               s.type as shop_type, s.image as shop_image, s.violation_count, s.status as shop_status 
        FROM bags b 
        JOIN shops s ON b.shop_id = s.id 
        WHERE b.id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $bag_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$bag = mysqli_fetch_assoc($res);

if (!$bag) {
    die("<div style='font-family: Arial, sans-serif; padding: 30px; text-align: center;'>
        <h2>❌ Không tìm thấy túi bất ngờ này</h2>
        <p><a href='" . BASE_URL . "/index.php'>Quay lại trang chủ</a></p>
    </div>");
}

$page_title = $bag['name'];

// Tính toán thời gian và trạng thái bằng PHP trên máy chủ
$now = time();
$ts_open = strtotime($bag['open_at']);
$ts_start = strtotime($bag['pickup_start']);
$ts_end = strtotime($bag['pickup_end']);
$ts_close = strtotime($bag['close_at']);

$discount_pct = calculate_discount_percent($bag['original_price'], $bag['web_price']);
$saving_amount = $bag['original_price'] - $bag['web_price'];

$is_open = ($now >= $ts_open && $now <= $ts_close && $bag['stock'] > 0);
$is_upcoming = ($now < $ts_open);
$is_closed = ($now > $ts_close);
$is_out_of_stock = ($bag['stock'] <= 0);

require_once __DIR__ . '/header.php';
?>

<!-- ĐIỀU HƯỚNG BREADCRUMB -->
<div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
    <a href="<?php echo BASE_URL; ?>/index.php">Trang chủ</a> &gt; 
    <span>Túi bất ngờ #<?php echo $bag['id']; ?></span> &gt; 
    <span style="color: var(--text-main); font-weight: 600;"><?php echo htmlspecialchars($bag['name']); ?></span>
</div>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; align-items: start;" class="detail-container">
    <!-- CỘT TRÁI: HÌNH ẢNH VÀ THÔNG ĐIỆP -->
    <div>
        <div class="content-box" style="padding: 20px; position: relative;">
            <div style="width: 100%; height: 350px; background: var(--nen-3); border: 1px solid var(--vien); border-radius: var(--radius-md); overflow: hidden; display: flex; align-items: center; justify-content: center;">
                <?php if (!empty($bag['image'])): ?>
                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($bag['image']); ?>" alt="Ảnh túi" style="width: 100%; height: 100%; object-fit: cover;">
                <?php else: ?>
                    <span style="font-size: 5rem;">🎁</span>
                <?php endif; ?>
            </div>

            <!-- DÒNG CAM KẾT QUY ĐỊNH BẮT BUỘC -->
            <div style="margin-top: 18px; background: var(--cam-mo); border-left: 4px solid var(--cam); border: 1px solid var(--cam); border-left-width: 4px; padding: 14px 18px; border-radius: 0 8px 8px 0;">
                <p style="font-size: 1rem; color: var(--cam-sang); font-weight: 700; margin-bottom: 4px;">
                    📢 "Túi bất ngờ: bạn không chọn được món, đổi lại giá rất tốt."
                </p>
                <p style="font-size: 0.85rem; color: var(--chu); line-height: 1.4;">
                    Khách mua trực tiếp tại quán vẫn trả giá gốc và được chọn món; mức giảm giá <b><?php echo $discount_pct; ?>%</b> này chỉ áp dụng cho người mua trên web chấp nhận mô hình "túi mù" để giúp quán giải cứu đồ ăn dư cuối ngày.
                </p>
            </div>
        </div>

        <!-- THÔNG TIN QUÁN ĂN -->
        <div class="content-box" style="margin-top: 20px;">
            <h3 style="color: var(--text-main); font-size: 1.15rem; margin-bottom: 12px;">🏪 Thông Tin Quán Cung Cấp</h3>
            <div style="display: flex; gap: 15px; align-items: center;">
                <?php if (!empty($bag['shop_image'])): ?>
                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($bag['shop_image']); ?>" alt="Shop" style="width: 60px; height: 60px; border-radius: 12px; object-fit: cover; border: 1px solid var(--border-color);">
                <?php else: ?>
                    <div style="width: 60px; height: 60px; border-radius: 12px; background: var(--nen-3); border: 1px solid var(--vien); display: flex; align-items: center; justify-content: center; font-size: 24px;">🏪</div>
                <?php endif; ?>
                <div>
                    <h4 style="margin: 0; font-size: 1.05rem;"><?php echo htmlspecialchars($bag['shop_name']); ?></h4>
                    <p style="margin: 4px 0 0 0; font-size: 0.85rem; color: var(--chu-phu);">
                        📍 <?php echo htmlspecialchars($bag['shop_address']); ?>, Q. <?php echo htmlspecialchars($bag['shop_district']); ?>
                    </p>
                    <?php if (!empty($bag['shop_phone'])): ?>
                        <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--thanh-cong); font-weight: 600;">
                            📞 Hotline: <a href="tel:<?php echo htmlspecialchars($bag['shop_phone']); ?>" style="color: var(--thanh-cong); text-decoration: underline;"><?php echo htmlspecialchars($bag['shop_phone']); ?></a>
                        </p>
                    <?php endif; ?>
                    <p style="margin: 2px 0 0 0; font-size: 0.85rem; color: var(--chu-phu);">
                        🍜 Loại ẩm thực: <b><?php echo htmlspecialchars($bag['shop_type']); ?></b>
                    </p>
                </div>
            </div>

            <div style="margin-top: 14px; padding-top: 12px; border-top: 1px dashed var(--border-color); display: flex; justify-content: space-between; align-items: center;">
                <span style="font-size: 0.85rem; color: var(--chu-phu);">Độ tin cậy của quán:</span>
                <?php if ($bag['violation_count'] == 0): ?>
                    <span class="status-badge status-success">⭐ Quán uy tín (0 vi phạm)</span>
                <?php else: ?>
                    <span class="status-badge status-danger">⚠️ <?php echo $bag['violation_count']; ?> lần vi phạm</span>
                <?php endif; ?>
            </div>

            <!-- BẢN ĐỒ VỊ TRÍ NHẬN TÚI TẠI QUÁN -->
            <?php 
                $shop_map_query = urlencode($bag['shop_address'] . ', ' . $bag['shop_district'] . ', Đà Nẵng');
            ?>
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border-color);">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 0.88rem; font-weight: 700; color: var(--text-main);">🗺️ Bản đồ điểm nhận túi:</span>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $shop_map_query; ?>" target="_blank" style="font-size: 0.82rem; color: var(--cam); font-weight: 600; text-decoration: underline;">
                        Chỉ đường Google Maps ↗
                    </a>
                </div>
                <div style="border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border-color); height: 180px;">
                    <iframe 
                        width="100%" 
                        height="100%" 
                        style="border:0;" 
                        loading="lazy" 
                        allowfullscreen 
                        src="https://maps.google.com/maps?q=<?php echo $shop_map_query; ?>&t=&z=15&ie=UTF8&iwloc=&output=embed">
                    </iframe>
                </div>
            </div>
        </div>
    </div>

    <!-- CỘT PHẢI: CHI TIẾT GIÁ, MỐC THỜI GIAN VÀ ĐẶT HÀNG -->
    <div>
        <div class="content-box">
            <div style="display: flex; gap: 8px; margin-bottom: 10px;">
                <span class="status-badge status-warning" style="font-size: 0.85rem;">Giảm -<?php echo $discount_pct; ?>%</span>
                <span class="status-badge <?php echo $bag['stock'] > 0 ? 'status-success' : 'status-danger'; ?>">
                    Còn <?php echo $bag['stock']; ?> túi
                </span>
            </div>

            <h1 style="font-size: 1.8rem; font-weight: 800; color: var(--text-main); margin-bottom: 12px;">
                <?php echo htmlspecialchars($bag['name']); ?>
            </h1>

            <!-- KHỐI GIÁ TIỀN -->
            <div style="background: var(--nen-3); border: 1px solid var(--vien); border-radius: var(--radius-md); padding: 18px; margin-bottom: 20px;">
                <div style="display: flex; justify-content: space-between; align-items: baseline;">
                    <div>
                        <span style="font-size: 0.85rem; color: var(--chu-phu); display: block;">Giá bán web cứu trợ:</span>
                        <span style="font-size: 2rem; font-weight: 900; color: var(--cam-sang); font-family: var(--font-game);">
                            <?php echo format_money($bag['web_price']); ?>
                        </span>
                    </div>
                    <div style="text-align: right;">
                        <span style="font-size: 0.85rem; color: var(--chu-phu); display: block;">Giá gốc tại quán:</span>
                        <span style="font-size: 1.2rem; color: var(--chu-phu); text-decoration: line-through;">
                            <?php echo format_money($bag['original_price']); ?>
                        </span>
                    </div>
                </div>
                <div style="margin-top: 8px; font-size: 0.88rem; color: var(--cam); font-weight: 600;">
                    🎉 Bạn tiết kiệm được <?php echo format_money($saving_amount); ?>!
                </div>
            </div>

            <!-- MÔ TẢ TÚI -->
            <div style="margin-bottom: 20px;">
                <h4 style="color: var(--chu); margin-bottom: 6px;">Mô tả sản phẩm:</h4>
                <p style="color: var(--chu-phu); font-size: 0.95rem; line-height: 1.6;">
                    <?php echo nl2br(htmlspecialchars($bag['description'] ?? 'Túi thực phẩm dư cuối ngày được chọn lọc kỹ càng, đảm bảo tiêu chuẩn vệ sinh an toàn thực phẩm.')); ?>
                </p>
            </div>

            <!-- CÁC MỐC THỜI GIAN (TÍNH THEO SERVER) -->
            <div style="background: var(--nen-3); border: 1px solid var(--vien); border-radius: var(--radius-md); padding: 16px; margin-bottom: 24px; font-size: 0.9rem;">
                <h4 style="color: var(--chu); margin-bottom: 10px;">⏰ Lịch trình nhận túi:</h4>
                <div style="display: flex; flex-direction: column; gap: 8px;">
                    <div>• Giờ mở bán: <b><?php echo date('H:i d/m/Y', $ts_open); ?></b></div>
                    <div>• Khung giờ nhận túi: <b style="color: var(--cam-sang);"><?php echo date('H:i', $ts_start); ?> - <?php echo date('H:i', $ts_end); ?> ngày <?php echo date('d/m/Y', $ts_start); ?></b></div>
                    <div>• Giờ đóng cửa (Hạn chót): <b><?php echo date('H:i d/m/Y', $ts_close); ?></b></div>
                    <div style="font-size: 0.8rem; color: var(--chu-phu); margin-top: 4px;">
                        <i>(Giờ máy chủ hiện tại: <?php echo date('H:i:s d/m/Y', $now); ?> - Hãy F5 để cập nhật)</i>
                    </div>
                </div>
            </div>

            <!-- XỬ LÝ NÚT MUA VÀ QUYỀN TRUY CẬP -->
            <?php if (!$current_user): ?>
                <div class="alert alert-info">
                    💡 Vui lòng <a href="<?php echo BASE_URL; ?>/dang_nhap.php"><b>Đăng nhập</b></a> tài khoản Sinh viên để đặt mua túi bất ngờ này.
                </div>
                <a href="<?php echo BASE_URL; ?>/dang_nhap.php" class="btn btn-primary btn-block" style="padding: 14px; font-size: 1.05rem;">
                    🔑 Đăng nhập để đặt túi
                </a>

            <?php elseif ($current_user['role'] === 'student'): ?>
                <?php if ($is_upcoming): 
                    $mins_left = ceil(($ts_open - $now) / 60);
                ?>
                    <div class="alert alert-warning">
                        ⏳ <b>Chưa đến giờ mở bán!</b> Túi sẽ chính thức mở bán vào lúc <b><?php echo date('H:i', $ts_open); ?></b> (còn khoảng <?php echo $mins_left; ?> phút nữa).
                    </div>
                    <button class="btn btn-block disabled" disabled style="padding: 14px; font-size: 1rem;">
                        Chưa mở bán (Vui lòng F5 khi đến giờ)
                    </button>

                <?php elseif ($is_closed): ?>
                    <div class="alert alert-danger">
                        ❌ Quán đã đóng cửa lúc <b><?php echo date('H:i', $ts_close); ?></b>. Hẹn gặp lại bạn vào ngày mai!
                    </div>
                    <button class="btn btn-block disabled" disabled style="padding: 14px;">Đã quá giờ đóng cửa</button>

                <?php elseif ($is_out_of_stock): ?>
                    <div class="alert alert-danger">
                        ❌ Túi bất ngờ này đã được các bạn sinh viên khác giải cứu hết!
                    </div>
                    <button class="btn btn-block disabled" disabled style="padding: 14px;">Đã hết túi</button>

                <?php else: ?>
                    <!-- ĐANG MỞ BÁN VÀ CÒN HÀNG -->
                    <div style="margin-bottom: 16px; padding: 12px; background: var(--nen-3); border: 1px solid var(--thanh-cong); border-radius: var(--radius-sm); font-size: 0.9rem; color: var(--chu);">
                        💰 Số dư ví hiện tại của bạn: <b style="color: var(--thanh-cong);"><?php echo format_money($current_user['balance']); ?></b>
                    </div>

                    <?php if ($current_user['balance'] >= $bag['web_price']): ?>
                        <form method="POST" action="<?php echo BASE_URL; ?>/student/dat_tui.php">
                            <input type="hidden" name="bag_id" value="<?php echo $bag['id']; ?>">
                            <button type="submit" class="btn btn-primary btn-block btn-buy" style="padding: 15px; font-size: 1.15rem; font-weight: 800; cursor: pointer;">
                                ⚡ Chốt Túi Bí Mật Ngay (Trừ <?php echo format_money($bag['web_price']); ?>)
                            </button>
                        </form>
                        <p style="font-size: 0.82rem; color: var(--chu-phu); text-align: center; margin-top: 8px;">
                            🛡️ Tiền của bạn được hệ thống tạm giữ an toàn 45 phút. Quán chỉ được nhận tiền khi bạn đã mang túi về kiểm tra và hoàn toàn hài lòng!
                        </p>
                    <?php else: ?>
                        <div class="alert alert-danger">
                            ⚠️ <b>Số dư ví không đủ!</b> Túi có giá <b><?php echo format_money($bag['web_price']); ?></b>, ví của bạn chỉ còn <b><?php echo format_money($current_user['balance']); ?></b>.
                        </div>
                        <a href="<?php echo BASE_URL; ?>/student/vi_tien.php" class="btn btn-accent btn-block" style="padding: 14px; font-size: 1.05rem; font-weight: 800;">
                            💰 Nạp Thêm Tiền Ảo Vào Ví Để Đặt Túi
                        </a>
                    <?php endif; ?>

                <?php endif; ?>

            <?php else: ?>
                <!-- Tài khoản chủ quán hoặc admin -->
                <div class="alert alert-secondary">
                    ℹ️ Bạn đang đăng nhập với vai trò <b><?php echo htmlspecialchars($current_user['role']); ?></b>. Để đặt túi, vui lòng sử dụng tài khoản Sinh viên.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .detail-container {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/footer.php'; ?>
