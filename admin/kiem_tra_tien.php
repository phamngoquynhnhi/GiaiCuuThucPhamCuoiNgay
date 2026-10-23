<?php
/**
 * TRANG KIỂM TRA TÍNH TOÀN VẸN DÒNG TIỀN (admin/kiem_tra_tien.php)
 * Công thức kế toán bảo toàn tiền ảo:
 * Tổng số dư ví (users.balance) + Tổng tiền đang giữ trong đơn (orders) = Tổng tiền đã nạp (wallet_logs)
 * Cảnh báo màu đỏ nổi bật nếu có bất kỳ sự sai lệch nào.
 */

$page_title = "Kiểm tra toàn vẹn dòng tiền";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

yeu_cau_admin();
$admin = require_role($conn, ['admin']);

// 1. Tổng số dư ví hiện tại của tất cả người dùng (A)
$q_balance = mysqli_query($conn, "SELECT COALESCE(SUM(balance), 0) as total_balance, COUNT(*) as total_users FROM users");
$row_balance = mysqli_fetch_assoc($q_balance);
$total_balance = (float)$row_balance['total_balance'];

// 2. Tổng tiền đang bị giữ trong các đơn hàng chưa giải phóng (B)
// Gồm các đơn: cho_nhan (chờ nhận), da_nhan (giữ 45 phút), tranh_chap (đóng băng)
$q_held = mysqli_query($conn, "SELECT COALESCE(SUM(price), 0) as total_held, COUNT(*) as total_held_orders 
                               FROM orders 
                               WHERE status IN ('cho_nhan', 'da_nhan', 'tranh_chap')");
$row_held = mysqli_fetch_assoc($q_held);
$total_held = (float)$row_held['total_held'];

// 3. Tổng tiền đã nạp demo vào hệ thống từ trước đến nay (C)
$q_dep = mysqli_query($conn, "SELECT COALESCE(SUM(amount), 0) as total_deposited, COUNT(*) as total_deposits 
                              FROM wallet_logs 
                              WHERE amount > 0 AND order_id IS NULL AND (reason LIKE '%Nạp tiền%' OR reason LIKE '%demo%')");
$row_dep = mysqli_fetch_assoc($q_dep);
$total_deposited = (float)$row_dep['total_deposited'];

// 4. Tính toán chênh lệch kế toán
$total_assets = $total_balance + $total_held;
$diff = round($total_assets - $total_deposited, 2);
$is_balanced = (abs($diff) < 0.01);

// Chi tiết các đơn đang giữ tiền
$q_held_list = mysqli_query($conn, "SELECT o.*, b.name as bag_name, s.name as shop_name, u.name as student_name 
                                     FROM orders o 
                                     JOIN bags b ON o.bag_id = b.id 
                                     JOIN shops s ON o.shop_id = s.id 
                                     JOIN users u ON o.user_id = u.id 
                                     WHERE o.status IN ('cho_nhan', 'da_nhan', 'tranh_chap') 
                                     ORDER BY o.id DESC");

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="margin-bottom: 24px;">
        <h2 style="color: var(--primary); margin: 0 0 4px 0;">🛡️ Kiểm Tra Tính Toàn Vẹn Dòng Tiền Ảo</h2>
        <p style="color: var(--text-muted); margin: 0;">
            Kiểm toán tự động đối chiếu số dư ví người dùng, tiền đang ký quỹ trong đơn hàng và tổng tiền đã nạp
        </p>
    </div>

    <!-- HỘP THÔNG BÁO KẾT QUẢ KIỂM TOÁN -->
    <?php if ($is_balanced): ?>
        <div class="alert alert-success" style="padding: 20px; font-size: 1.05rem; border-left: 6px solid var(--thanh-cong);">
            <div style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">
                ✅ DÒNG TIỀN CÂN BẰNG TUYỆT ĐỐI (100% Khớp)!
            </div>
            <p style="margin: 0; line-height: 1.5;">
                Tổng số dư ví (<b><?php echo format_money($total_balance); ?></b>) + Tiền đang tạm giữ trong các đơn (<b><?php echo format_money($total_held); ?></b>) đúng bằng Tổng tiền nạp vào hệ thống (<b><?php echo format_money($total_deposited); ?></b>).<br>
                Hệ thống hoạt động chuẩn xác theo nguyên tắc bảo toàn tài sản, không xảy ra thất thoát tiền hay lỗi làm phát sinh tiền ảo ngoài luồng.
            </p>
        </div>
    <?php else: ?>
        <div class="alert alert-danger" style="padding: 20px; font-size: 1.05rem; border-left: 6px solid var(--loi);">
            <div style="font-size: 1.3rem; font-weight: 800; margin-bottom: 6px;">
                ⚠️ CẢNH BÁO LỆCH DÒNG TIỀN KẾ TOÁN!
            </div>
            <p style="margin: 0; line-height: 1.5;">
                Phát hiện sai lệch giữa Tài sản hiện có (Số dư + Đơn giữ tiền) và Tiền đã nạp!<br>
                Mức độ chênh lệch: <b style="font-size: 1.2rem;"><?php echo format_money($diff); ?></b>. Hãy kiểm tra lại các bản ghi trong <code>wallet_logs</code>.
            </p>
        </div>
    <?php endif; ?>

    <!-- CÁC THẺ THÀNH PHẦN KẾ TOÁN -->
    <div class="stats-grid" style="margin-top: 24px;">
        <div class="stat-card" style="border-top: 4px solid var(--thong-tin);">
            <div class="stat-title">A. TỔNG SỐ DƯ TẤT CẢ VÍ</div>
            <div class="stat-value" style="color: var(--thong-tin); font-family: var(--font-game);">
                <?php echo format_money($total_balance); ?>
            </div>
            <div class="stat-sub">Trong <?php echo $row_balance['total_users']; ?> tài khoản người dùng</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--cam);">
            <div class="stat-title">B. TIỀN ĐANG GIỮ TRONG ĐƠN</div>
            <div class="stat-value" style="color: var(--cam-sang); font-family: var(--font-game);">
                <?php echo format_money($total_held); ?>
            </div>
            <div class="stat-sub"><?php echo $row_held['total_held_orders']; ?> đơn chưa kết thúc (ký quỹ)</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--thanh-cong);">
            <div class="stat-title">TỔNG HIỆN HỮU (A + B)</div>
            <div class="stat-value" style="color: var(--thanh-cong); font-family: var(--font-game);">
                <?php echo format_money($total_assets); ?>
            </div>
            <div class="stat-sub">Tổng tài sản đang lưu hành</div>
        </div>

        <div class="stat-card" style="border-top: 4px solid var(--chu-phu);">
            <div class="stat-title">C. TỔNG TIỀN ĐÃ NẠP VÀO</div>
            <div class="stat-value" style="color: var(--chu); font-family: var(--font-game);">
                <?php echo format_money($total_deposited); ?>
            </div>
            <div class="stat-sub">Từ <?php echo $row_dep['total_deposits']; ?> lần nạp demo</div>
        </div>
    </div>

    <!-- DANH SÁCH CHI TIẾT CÁC ĐƠN ĐANG GIỮ TIỀN -->
    <div style="margin-top: 30px;">
        <h3 style="font-size: 1.2rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px;">
            📦 Danh Sách Các Đơn Đang Tạm Giữ Tiền (Ký Quỹ Chưa Chuyển Cho Quán)
        </h3>

        <?php if (mysqli_num_rows($q_held_list) == 0): ?>
            <p style="color: var(--chu-phu); font-style: italic;">Hiện không có đơn hàng nào đang trong trạng thái giữ tiền.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Đơn #</th>
                            <th>Sinh viên</th>
                            <th>Quán ăn</th>
                            <th>Túi bất ngờ</th>
                            <th>Tiền đang giữ</th>
                            <th>Trạng thái</th>
                            <th>Thời điểm giữ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($h = mysqli_fetch_assoc($q_held_list)): 
                            $st = get_order_status_info($h['status']);
                        ?>
                            <tr>
                                <td><b>#<?php echo $h['id']; ?></b></td>
                                <td><?php echo htmlspecialchars($h['student_name']); ?></td>
                                <td><?php echo htmlspecialchars($h['shop_name']); ?></td>
                                <td><?php echo htmlspecialchars($h['bag_name']); ?></td>
                                <td><b style="color: var(--cam-sang); font-family: var(--font-game);"><?php echo format_money($h['price']); ?></b></td>
                                <td><span class="<?php echo $st['class']; ?>"><?php echo htmlspecialchars($st['name']); ?></span></td>
                                <td>
                                    <?php if (!empty($h['hold_until'])): ?>
                                        Giữ đến: <?php echo date('H:i d/m', strtotime($h['hold_until'])); ?>
                                    <?php else: ?>
                                        Đóng cửa: <?php echo date('H:i d/m', strtotime($h['close_at'])); ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
