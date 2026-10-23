<?php
/**
 * TRANG QUẢN LÝ ĐƠN HÀNG CỦA QUÁN (shop/don_hang.php)
 * Dành cho Chủ quán theo dõi toàn bộ đơn hàng của quán mình
 */

$page_title = "Quản lý đơn hàng của quán";
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
$filter_status = trim($_GET['status'] ?? '');

$sql = "SELECT o.*, b.name as bag_name, u.name as student_name, u.email as student_email 
        FROM orders o 
        JOIN bags b ON o.bag_id = b.id 
        JOIN users u ON o.user_id = u.id 
        WHERE o.shop_id = ?";

if ($filter_status !== '') {
    $sql .= " AND o.status = ?";
    $stmt = mysqli_prepare($conn, $sql . " ORDER BY o.id DESC");
    mysqli_stmt_bind_param($stmt, "is", $shop_id, $filter_status);
} else {
    $stmt = mysqli_prepare($conn, $sql . " ORDER BY o.id DESC");
    mysqli_stmt_bind_param($stmt, "i", $shop_id);
}

mysqli_stmt_execute($stmt);
$res_orders = mysqli_stmt_get_result($stmt);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">📋 Danh Sách Đơn Hàng Của Quán</h2>
            <p style="color: var(--text-muted); margin: 0;">Quán: <b><?php echo htmlspecialchars($shop['name']); ?></b></p>
        </div>
        <div>
            <a href="nhap_ma.php" class="btn btn-accent">⚡ Nhập mã nhận hàng</a>
        </div>
    </div>

    <!-- BỘ LỌC TRẠNG THÁI -->
    <div style="display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;">
        <a href="don_hang.php" class="btn btn-sm <?php echo $filter_status === '' ? 'btn-primary' : 'btn-outline'; ?>">Tất cả</a>
        <a href="don_hang.php?status=cho_nhan" class="btn btn-sm <?php echo $filter_status === 'cho_nhan' ? 'btn-primary' : 'btn-outline'; ?>">Chờ nhận hàng</a>
        <a href="don_hang.php?status=da_nhan" class="btn btn-sm <?php echo $filter_status === 'da_nhan' ? 'btn-primary' : 'btn-outline'; ?>">Đã nhận (Đang giữ tiền)</a>
        <a href="don_hang.php?status=hoan_thanh" class="btn btn-sm <?php echo $filter_status === 'hoan_thanh' ? 'btn-primary' : 'btn-outline'; ?>">Đã hoàn thành</a>
        <a href="don_hang.php?status=tranh_chap" class="btn btn-sm <?php echo $filter_status === 'tranh_chap' ? 'btn-danger' : 'btn-outline'; ?>" style="color: var(--loi);">Đang tranh chấp ⚠️</a>
        <a href="don_hang.php?status=huy_boi_khach" class="btn btn-sm <?php echo $filter_status === 'huy_boi_khach' ? 'btn-primary' : 'btn-outline'; ?>">Khách hủy</a>
        <a href="don_hang.php?status=huy_vang_mat" class="btn btn-sm <?php echo $filter_status === 'huy_vang_mat' ? 'btn-primary' : 'btn-outline'; ?>">Vắng mặt (Bồi thường)</a>
    </div>

    <?php if (mysqli_num_rows($res_orders) == 0): ?>
        <p style="color: var(--chu-phu); font-style: italic;">Không tìm thấy đơn hàng nào.</p>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Mã ĐH</th>
                        <th>Khách hàng</th>
                        <th>Túi bất ngờ</th>
                        <th>Giá trị</th>
                        <th>Mã nhận</th>
                        <th>Thời gian đặt</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($ord = mysqli_fetch_assoc($res_orders)): 
                        $st = get_order_status_info($ord['status']);
                    ?>
                        <tr>
                            <td><b>#<?php echo $ord['id']; ?></b></td>
                            <td>
                                <b><?php echo htmlspecialchars($ord['student_name']); ?></b>
                                <div style="font-size: 0.75rem; color: var(--chu-phu);"><?php echo htmlspecialchars($ord['student_email']); ?></div>
                            </td>
                            <td><?php echo htmlspecialchars($ord['bag_name']); ?></td>
                            <td><b style="color: var(--cam-sang); font-family: var(--font-game);"><?php echo format_money($ord['price']); ?></b></td>
                            <td>
                                <span style="font-family: var(--font-game); font-weight: bold; background: var(--nen-3); padding: 3px 6px; border: 1px solid var(--cam); border-radius: 4px; color: var(--cam-sang);">
                                    <?php echo htmlspecialchars($ord['pickup_code']); ?>
                                </span>
                            </td>
                            <td><?php echo date('H:i d/m', strtotime($ord['created_at'])); ?></td>
                            <td>
                                <span class="<?php echo $st['class']; ?>">
                                    <?php echo htmlspecialchars($st['name']); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($ord['status'] === 'cho_nhan'): ?>
                                    <a href="nhap_ma.php" class="btn btn-sm btn-accent">Nhập mã</a>
                                <?php elseif ($ord['status'] === 'tranh_chap'): ?>
                                    <a href="phan_hoi_khieu_nai.php?order_id=<?php echo $ord['id']; ?>" class="btn btn-sm btn-danger">Phản đối khiếu nại</a>
                                <?php else: ?>
                                    <span style="color: var(--chu-phu); font-size: 0.85rem;">-</span>
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
