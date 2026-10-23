<?php
/**
 * TRANG BÁO CÁO DOANH THU CỦA QUÁN (shop/doanh_thu.php)
 * Thống kê rõ ràng: Tiền đang giữ / Đã nhận / Đang tranh chấp / Tiền bồi thường vắng mặt
 */

$page_title = "Báo cáo doanh thu quán";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

// Lấy thông tin quán
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

// 1. Tiền đang tạm giữ (status = 'da_nhan')
$q1 = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total, COUNT(*) as cnt FROM orders WHERE shop_id = {$shop_id} AND status = 'da_nhan'");
$row_holding = mysqli_fetch_assoc($q1);

// 2. Tiền đã nhận hoàn tất (status IN ('hoan_thanh', 'chuyen_tien_quan'))
$q2 = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total, COUNT(*) as cnt FROM orders WHERE shop_id = {$shop_id} AND status IN ('hoan_thanh', 'chuyen_tien_quan')");
$row_completed = mysqli_fetch_assoc($q2);

// 3. Tiền đang tranh chấp (status = 'tranh_chap')
$q3 = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total, COUNT(*) as cnt FROM orders WHERE shop_id = {$shop_id} AND status = 'tranh_chap'");
$row_dispute = mysqli_fetch_assoc($q3);

// 4. Tiền bồi thường do khách vắng mặt (status = 'huy_vang_mat')
$q4 = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total, COUNT(*) as cnt FROM orders WHERE shop_id = {$shop_id} AND status = 'huy_vang_mat'");
$row_absent = mysqli_fetch_assoc($q4);

// Lịch sử ví của chủ quán
$stmt_logs = mysqli_prepare($conn, "SELECT * FROM wallet_logs WHERE user_id = ? ORDER BY id DESC LIMIT 50");
mysqli_stmt_bind_param($stmt_logs, "i", $owner_id);
mysqli_stmt_execute($stmt_logs);
$res_logs = mysqli_stmt_get_result($stmt_logs);

// Lấy tỷ lệ hoa hồng nền tảng
$commission_pct = (float)get_setting($conn, 'commission_percent', 10);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="margin-bottom: 24px;">
        <h2 style="color: var(--primary); margin: 0 0 4px 0;">💵 Báo Cáo Doanh Thu Quán Ăn</h2>
        <p style="color: var(--text-muted); margin: 0;">Quán: <b><?php echo htmlspecialchars($shop['name']); ?></b></p>
    </div>

    <!-- THÔNG BÁO CHÍNH SÁCH HOA HỒNG NỀN TẢNG -->
    <div style="background: var(--nen-2); border: 2px solid var(--cam); border-radius: 10px; padding: 14px 18px; margin-bottom: 22px; font-size: 0.92rem; color: var(--chu); display: flex; align-items: center; gap: 12px; box-shadow: 0 0 10px var(--phat-cam);">
        <span style="font-size: 1.5rem;">💡</span>
        <div>
            <b style="color: var(--cam-sang);">Chính sách hoa hồng nền tảng:</b> Hệ thống áp dụng mức chiết khấu <b><?php echo $commission_pct; ?>%</b> trên mỗi túi giải cứu thành công để duy trì máy chủ và kết nối người mua. Khi đơn hoàn tất, quán thực nhận <b><?php echo (100 - $commission_pct); ?>%</b> doanh thu trực tiếp vào số dư ví khả dụng.
        </div>
    </div>

    <!-- CÁC THẺ THỐNG KÊ DOANH THU -->
    <div class="stats-grid">
        <div class="stat-card" style="border-top: 4px solid var(--cam);">
            <div class="stat-title">SỐ DƯ VÍ KHẢ DỤNG</div>
            <div class="stat-value" style="color: var(--cam-sang); font-family: var(--font-game);">
                <?php echo format_money($user['balance']); ?>
            </div>
            <div class="stat-sub">Tiền thực nhận trong ví chủ quán</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--thong-tin);">
            <div class="stat-title">TIỀN ĐANG TẠM GIỮ</div>
            <div class="stat-value" style="color: var(--thong-tin); font-family: var(--font-game);">
                <?php echo format_money($row_holding['total']); ?>
            </div>
            <div class="stat-sub"><?php echo $row_holding['cnt']; ?> đơn đã giao (giữ 45 phút)</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--thanh-cong);">
            <div class="stat-title">TỔNG ĐÃ HOÀN TẤT</div>
            <div class="stat-value" style="color: var(--thanh-cong); font-family: var(--font-game);">
                <?php echo format_money($row_completed['total']); ?>
            </div>
            <div class="stat-sub"><?php echo $row_completed['cnt']; ?> đơn hoàn thành giao dịch</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--loi);">
            <div class="stat-title">TIỀN TRANH CHẤP</div>
            <div class="stat-value" style="color: var(--loi); font-family: var(--font-game);">
                <?php echo format_money($row_dispute['total']); ?>
            </div>
            <div class="stat-sub"><?php echo $row_dispute['cnt']; ?> đơn có khiếu nại</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--canh-bao);">
            <div class="stat-title">BỒI THƯỜNG VẮNG MẶT</div>
            <div class="stat-value" style="color: var(--canh-bao); font-family: var(--font-game);">
                <?php echo format_money($row_absent['total']); ?>
            </div>
            <div class="stat-sub"><?php echo $row_absent['cnt']; ?> đơn khách không đến lấy</div>
        </div>
    </div>

    <!-- LỊCH SỬ BIẾN ĐỘNG VÍ CHỦ QUÁN -->
    <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">
        📜 Lịch Sử Biến Động Số Dư
    </h3>

    <?php if (mysqli_num_rows($res_logs) == 0): ?>
        <p style="color: var(--chu-phu); font-style: italic;">Chưa có giao dịch tiền nào được ghi nhận cho quán.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã GD</th>
                        <th>Thời gian</th>
                        <th>Số tiền</th>
                        <th>Lý do</th>
                        <th>Đơn hàng</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($log = mysqli_fetch_assoc($res_logs)): 
                        $is_positive = ($log['amount'] > 0);
                    ?>
                        <tr>
                            <td>#<?php echo $log['id']; ?></td>
                            <td><?php echo date('H:i:s d/m/Y', strtotime($log['created_at'])); ?></td>
                            <td>
                                <b style="color: <?php echo $is_positive ? 'var(--thanh-cong)' : 'var(--loi)'; ?>; font-family: var(--font-game);">
                                    <?php echo ($is_positive ? '+' : '') . format_money($log['amount']); ?>
                                </b>
                            </td>
                            <td><?php echo htmlspecialchars($log['reason']); ?></td>
                            <td>
                                <?php if (!empty($log['order_id'])): ?>
                                    <span style="font-weight: bold;">#<?php echo $log['order_id']; ?></span>
                                <?php else: ?>
                                    <span style="color: var(--chu-phu);">-</span>
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
