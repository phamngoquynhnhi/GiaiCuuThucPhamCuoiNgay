<?php
/**
 * TRANG TỔNG QUAN QUẢN TRỊ VIÊN (admin/tong_quan.php)
 * Đặt yeu_cau_admin() ở dòng đầu để phân quyền bảo vệ
 */

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Admin: chưa đăng nhập -> về dang_nhap.php, không phải admin -> hiện thông báo chặn
yeu_cau_admin();

$page_title = "Tổng quan Quản trị viên";

// 1. Thống kê số túi đã giải cứu
$q_bags = mysqli_query($conn, "SELECT COUNT(*) as rescued_bags FROM orders WHERE status IN ('da_nhan', 'hoan_thanh', 'chuyen_tien_quan')");
$row_bags = $q_bags ? mysqli_fetch_assoc($q_bags) : ['rescued_bags' => 0];
$rescued_bags = (int)$row_bags['rescued_bags'];

// 2. Tiền sinh viên tiết kiệm được
$q_savings = mysqli_query($conn, "SELECT COALESCE(SUM(b.original_price - o.price), 0) as total_savings 
                                  FROM orders o 
                                  JOIN bags b ON o.bag_id = b.id 
                                  WHERE o.status IN ('da_nhan', 'hoan_thanh', 'chuyen_tien_quan')");
$row_savings = $q_savings ? mysqli_fetch_assoc($q_savings) : ['total_savings' => 0];
$total_savings = (float)$row_savings['total_savings'];

// 3. Số lượng tranh chấp / khiếu nại
$q_disp = mysqli_query($conn, "SELECT COUNT(*) as total_disputes FROM disputes");
$row_disp = $q_disp ? mysqli_fetch_assoc($q_disp) : ['total_disputes' => 0];
$total_disputes = (int)$row_disp['total_disputes'];

// 4. Tổng số người dùng
$q_users = mysqli_query($conn, "SELECT 
    COUNT(*) as total_users,
    SUM(CASE WHEN role='user' OR role='student' THEN 1 ELSE 0 END) as user_count,
    SUM(CASE WHEN role='admin' THEN 1 ELSE 0 END) as admin_count
    FROM users");
$row_users = $q_users ? mysqli_fetch_assoc($q_users) : ['total_users' => 0, 'user_count' => 0, 'admin_count' => 0];

// 5. Thống kê lợi nhuận hoa hồng nền tảng của Admin (10%)
$commission_pct_curr = (float)get_setting($conn, 'commission_percent', 10);
$q_admin_profit = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total_profit FROM wallet_logs WHERE user_id IN (SELECT id FROM users WHERE role = 'admin') AND amount > 0 AND reason LIKE '%hoa hồng%'");
$row_profit = $q_admin_profit ? mysqli_fetch_assoc($q_admin_profit) : ['total_profit' => 0];
$total_profit = (float)$row_profit['total_profit'];

$q_admin_wallet = mysqli_query($conn, "SELECT balance FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
$row_admin_wallet = $q_admin_wallet ? mysqli_fetch_assoc($q_admin_wallet) : ['balance' => 0];
$admin_balance = (float)$row_admin_wallet['balance'];

// Lấy 8 đơn hàng mới nhất
$q_recent_orders = mysqli_query($conn, "SELECT o.*, b.name as bag_name, s.name as shop_name, u.name as student_name 
                                        FROM orders o 
                                        JOIN bags b ON o.bag_id = b.id 
                                        JOIN shops s ON o.shop_id = s.id 
                                        JOIN users u ON o.user_id = u.id 
                                        ORDER BY o.id DESC LIMIT 8");

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 24px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">📊 Tổng Quan Quản Trị Hệ Thống</h2>
            <p style="color: var(--text-muted); margin: 0;">Xin chào Admin: <b><?php echo htmlspecialchars($_SESSION['name'] ?? 'Quản trị viên'); ?></b></p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="<?php echo BASE_URL; ?>/admin/nguoi_dung.php" class="btn btn-primary">👥 Quản lý người dùng</a>
            <a href="<?php echo BASE_URL; ?>/admin/tranh_chap.php" class="btn btn-accent">⚖️ Xử lý tranh chấp</a>
            <a href="<?php echo BASE_URL; ?>/dang_xuat.php" class="btn btn-outline" style="color: var(--loi);">Đăng xuất</a>
        </div>
    </div>

    <!-- CÁC THẺ THỐNG KÊ SQL -->
    <div class="stats-grid">
        <div class="stat-card" style="border-top: 4px solid var(--cam);">
            <div class="stat-title">TÚI ĐÃ ĐƯỢC GIẢI CỨU</div>
            <div class="stat-value" style="color: var(--cam-sang); font-family: var(--font-game);">
                <?php echo number_format($rescued_bags); ?>
            </div>
            <div class="stat-sub">Tránh lãng phí thức ăn cuối ngày</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--thanh-cong);">
            <div class="stat-title">LỢI NHUẬN HOA HỒNG SÀN</div>
            <div class="stat-value" style="color: var(--thanh-cong); font-family: var(--font-game);">
                <?php echo format_money($total_profit); ?>
            </div>
            <div class="stat-sub">Ví Admin: <?php echo format_money($admin_balance); ?> (Thu <?php echo $commission_pct_curr; ?>%/túi)</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--cam-sang);">
            <div class="stat-title">TIỀN TIẾT KIỆM CHO NGƯỜI DÙNG</div>
            <div class="stat-value" style="color: var(--cam-sang); font-family: var(--font-game);">
                <?php echo format_money($total_savings); ?>
            </div>
            <div class="stat-sub">Chênh lệch so với giá gốc</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--loi);">
            <div class="stat-title">SỐ TRANH CHẤP / KHIẾU NẠI</div>
            <div class="stat-value" style="color: var(--loi); font-family: var(--font-game);">
                <?php echo number_format($total_disputes); ?>
            </div>
            <div class="stat-sub">Hồ sơ báo cáo thực phẩm</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--thong-tin);">
            <div class="stat-title">TỔNG NGƯỜI DÙNG</div>
            <div class="stat-value" style="color: var(--thong-tin); font-family: var(--font-game);">
                <?php echo number_format($row_users['total_users']); ?>
            </div>
            <div class="stat-sub"><?php echo $row_users['user_count']; ?> người dùng • <?php echo $row_users['admin_count']; ?> admin</div>
        </div>
    </div>

    <!-- BẢNG ĐƠN HÀNG GẦN ĐÂY NẾU CÓ -->
    <?php if ($q_recent_orders && mysqli_num_rows($q_recent_orders) > 0): ?>
        <div style="margin-top: 30px;">
            <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">
                🛒 Các Đơn Đặt Hàng Gần Đây
            </h3>

            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Đơn #</th>
                            <th>Người mua</th>
                            <th>Quán ăn</th>
                            <th>Túi bất ngờ</th>
                            <th>Giá web</th>
                            <th>Mã nhận</th>
                            <th>Thời gian</th>
                            <th>Trạng thái</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($ord = mysqli_fetch_assoc($q_recent_orders)): 
                            $st = get_order_status_info($ord['status']);
                        ?>
                            <tr>
                                <td><b>#<?php echo $ord['id']; ?></b></td>
                                <td><?php echo htmlspecialchars($ord['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($ord['shop_name']); ?></td>
                                <td><?php echo htmlspecialchars($ord['bag_name']); ?></td>
                                <td><b style="color: var(--cam-sang); font-family: var(--font-game);"><?php echo format_money($ord['price']); ?></b></td>
                                <td><span style="font-family: var(--font-game); font-weight: bold; color: var(--cam-sang);"><?php echo htmlspecialchars($ord['pickup_code']); ?></span></td>
                                <td><?php echo date('H:i d/m', strtotime($ord['created_at'])); ?></td>
                                <td><span class="<?php echo $st['class']; ?>"><?php echo htmlspecialchars($st['name']); ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
