<?php
/**
 * TRANG VÍ TIỀN ẢO (student/vi_tien.php)
 * Dành cho Sinh viên xem số dư, nạp tiền demo và xem lịch sử ví
 */

$page_title = "Ví tiền của tôi";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền sinh viên
$user = require_role($conn, ['student']);
$user_id = $user['id'];

$error = '';
$success = '';

// Xử lý nạp tiền ảo demo
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit_amount'])) {
    $amount = (float)($_POST['deposit_amount'] ?? 0);

    if ($amount <= 0) {
        $error = 'Số tiền nạp phải lớn hơn 0đ!';
    } elseif ($amount > 5000000) {
        $error = 'Số tiền nạp demo tối đa một lần là 5.000.000 đ!';
    } else {
        // Thực hiện Transaction nạp tiền
        mysqli_begin_transaction($conn);

        $stmt_up = mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_up, "di", $amount, $user_id);
        $ok1 = mysqli_stmt_execute($stmt_up);

        $reason = "Nạp tiền demo vào ví";
        $stmt_log = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, NULL, NOW())");
        mysqli_stmt_bind_param($stmt_log, "ids", $user_id, $amount, $reason);
        $ok2 = mysqli_stmt_execute($stmt_log);

        if ($ok1 && $ok2) {
            mysqli_commit($conn);
            $success = "🎉 Nạp thành công " . format_money($amount) . " vào ví demo!";
            // Cập nhật lại số dư mới nhất để hiển thị
            $user = get_current_user_data($conn);
        } else {
            mysqli_rollback($conn);
            $error = 'Đã có lỗi xảy ra khi nạp tiền. Vui lòng thử lại!';
        }
    }
}

// Lấy lịch sử biến động số dư của sinh viên
$stmt_logs = mysqli_prepare($conn, "SELECT * FROM wallet_logs WHERE user_id = ? ORDER BY id DESC LIMIT 50");
mysqli_stmt_bind_param($stmt_logs, "i", $user_id);
mysqli_stmt_execute($stmt_logs);
$res_logs = mysqli_stmt_get_result($stmt_logs);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <h2 style="color: var(--primary); margin-bottom: 6px;">💰 Ví Tiền Ảo Của Bạn</h2>
    <p style="color: var(--text-muted); margin-bottom: 24px;">
        Hệ thống sử dụng tiền ảo demo để mô phỏng quy trình đặt hàng, thanh toán và giữ tiền an toàn.
    </p>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;" class="wallet-grid">
        <!-- KHỐI SỐ DƯ HIỆN TẠI -->
        <div style="background: var(--nen-2); border: 2px solid var(--cam); color: var(--chu); border-radius: var(--radius-md); padding: 26px; box-shadow: 0 0 16px var(--phat-cam); position: relative;">
            <div style="font-size: 0.9rem; color: var(--chu-phu); margin-bottom: 6px;">SỐ DƯ KHẢ DỤNG:</div>
            <div style="font-size: 2.4rem; font-weight: 900; letter-spacing: -0.5px; color: var(--cam-sang); font-family: var(--font-game);">
                <?php echo format_money($user['balance']); ?>
            </div>
            <div style="margin-top: 14px; font-size: 0.85rem; color: var(--chu-phu); line-height: 1.4;">
                🛡️ Tiền của bạn được đảm bảo: Khi đặt túi, tiền được giữ trong đơn. Quán chỉ nhận tiền sau 45 phút bạn nhận túi nếu không có khiếu nại.
            </div>
        </div>

        <!-- KHỐI NẠP TIỀN DEMO -->
        <div style="background: var(--nen-2); border: 2px solid var(--vien); border-radius: var(--radius-md); padding: 22px; box-shadow: var(--shadow-card);">
            <h3 style="color: var(--text-main); font-size: 1.1rem; margin-bottom: 12px;">⚡ Nạp Tiền Demo Nhanh</h3>
            
            <form method="POST" action="">
                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Chọn hoặc nhập số tiền (VNĐ):</label>
                    <input type="number" name="deposit_amount" id="deposit_input" step="10000" min="10000" max="5000000" class="form-control" required placeholder="Nhập số tiền..." value="100000">
                </div>

                <div style="display: flex; gap: 8px; margin-bottom: 16px; flex-wrap: wrap;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('deposit_input').value=50000;">+50.000 đ</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('deposit_input').value=100000;">+100.000 đ</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('deposit_input').value=200000;">+200.000 đ</button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('deposit_input').value=500000;">+500.000 đ</button>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 11px;">
                    💳 Nạp Tiền Ngay
                </button>
            </form>
        </div>
    </div>

    <!-- LỊCH SỬ BIẾN ĐỘNG SỐ DƯ (WALLET_LOGS) -->
    <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">
        📜 Lịch Sử Biến Động Số Dư
    </h3>

    <?php if (mysqli_num_rows($res_logs) == 0): ?>
        <p style="color: var(--chu-phu); font-style: italic;">Chưa có giao dịch nào được ghi nhận.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã GD</th>
                        <th>Thời gian</th>
                        <th>Số tiền</th>
                        <th>Nội dung biến động</th>
                        <th>Đơn hàng liên quan</th>
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
                                <b style="color: <?php echo $is_positive ? 'var(--thanh-cong)' : 'var(--loi)'; ?>; font-size: 0.95rem; font-family: var(--font-game);">
                                    <?php echo ($is_positive ? '+' : '') . format_money($log['amount']); ?>
                                </b>
                            </td>
                            <td><?php echo htmlspecialchars($log['reason']); ?></td>
                            <td>
                                <?php if (!empty($log['order_id'])): ?>
                                    <a href="<?php echo BASE_URL; ?>/student/chi_tiet_don.php?id=<?php echo $log['order_id']; ?>" style="font-weight: 600;">
                                        Xem đơn #<?php echo $log['order_id']; ?>
                                    </a>
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

<style>
@media (max-width: 768px) {
    .wallet-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
