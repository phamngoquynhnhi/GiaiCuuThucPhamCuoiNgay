<?php
/**
 * TRANG QUẢN LÝ TÚI BẤT NGỜ (shop/quan_ly_tui.php)
 * Dành cho Chủ quán theo dõi và quản lý các túi đã tạo
 */

$page_title = "Quản lý túi bất ngờ";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Chủ quán
$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

// Lấy thông tin quán
$stmt_shop = mysqli_prepare($conn, "SELECT id, name, status FROM shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_shop, "i", $owner_id);
mysqli_stmt_execute($stmt_shop);
$res_shop = mysqli_stmt_get_result($stmt_shop);
$shop = mysqli_fetch_assoc($res_shop);

if (!$shop) {
    header("Location: ho_so_quan.php");
    exit;
}

$shop_id = $shop['id'];

// Lấy danh sách các túi của quán kèm số đơn đã đặt
$sql_bags = "SELECT b.*, 
            (SELECT COUNT(*) FROM orders o WHERE o.bag_id = b.id) as order_count 
             FROM bags b 
             WHERE b.shop_id = ? 
             ORDER BY b.id DESC";
$stmt_bags = mysqli_prepare($conn, $sql_bags);
mysqli_stmt_bind_param($stmt_bags, "i", $shop_id);
mysqli_stmt_execute($stmt_bags);
$res_bags = mysqli_stmt_get_result($stmt_bags);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--primary); margin-bottom: 4px;">📦 Quản Lý Túi Bất Ngờ</h2>
            <p style="color: var(--text-muted);">
                Quán: <b><?php echo htmlspecialchars($shop['name']); ?></b>
                <?php if ($shop['status'] === 'da_duyet'): ?>
                    <span class="status-badge status-success">Đã duyệt</span>
                <?php else: ?>
                    <span class="status-badge status-warning">Chờ Admin duyệt</span>
                <?php endif; ?>
            </p>
        </div>

        <div>
            <a href="them_tui.php" class="btn btn-primary">➕ Thêm Túi Mới</a>
        </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
        <?php if ($_GET['msg'] === 'added'): ?>
            <div class="alert alert-success">✅ Đã thêm túi bất ngờ mới thành công!</div>
        <?php elseif ($_GET['msg'] === 'updated'): ?>
            <div class="alert alert-success">✅ Đã cập nhật thông tin túi thành công!</div>
        <?php elseif ($_GET['msg'] === 'deleted'): ?>
            <div class="alert alert-success">✅ Đã xóa túi thành công!</div>
        <?php elseif ($_GET['msg'] === 'cannot_delete'): ?>
            <div class="alert alert-danger">❌ Không thể xóa túi này vì đã có sinh viên đặt hàng!</div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if (mysqli_num_rows($res_bags) == 0): ?>
        <div style="text-align: center; padding: 40px 20px; background: var(--nen-2); border-radius: var(--radius-md); border: 1px dashed var(--vien);">
            <p style="font-size: 1.1rem; color: var(--chu-phu); margin-bottom: 15px;">Quán chưa đăng túi bất ngờ nào.</p>
            <a href="them_tui.php" class="btn btn-primary">Tạo túi bất ngờ đầu tiên ngay</a>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Ảnh</th>
                        <th>Tên túi</th>
                        <th>Giá gốc / Giá web</th>
                        <th>Giảm (%)</th>
                        <th>Còn lại</th>
                        <th>Giờ mở bán</th>
                        <th>Khung giờ nhận</th>
                        <th>Đóng cửa</th>
                        <th>Trạng thái</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($bag = mysqli_fetch_assoc($res_bags)): 
                        $now = time();
                        $open_ts = strtotime($bag['open_at']);
                        $close_ts = strtotime($bag['close_at']);
                        $discount_pct = calculate_discount_percent($bag['original_price'], $bag['web_price']);
                    ?>
                        <tr>
                            <td>
                                <?php if (!empty($bag['image'])): ?>
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($bag['image']); ?>" alt="Bag" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;">
                                <?php else: ?>
                                    <div style="width: 50px; height: 50px; background: var(--nen-3); border: 1px solid var(--vien); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 20px;">🛍️</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <b><?php echo htmlspecialchars($bag['name']); ?></b>
                                <div style="font-size: 0.78rem; color: var(--chu-phu);">Đã có <?php echo $bag['order_count']; ?> đơn đặt</div>
                            </td>
                            <td>
                                <div style="text-decoration: line-through; color: var(--chu-phu); font-size: 0.85rem;"><?php echo format_money($bag['original_price']); ?></div>
                                <div style="color: var(--cam-sang); font-weight: bold; font-family: var(--font-game);"><?php echo format_money($bag['web_price']); ?></div>
                            </td>
                            <td>
                                <span class="status-badge status-warning">-<?php echo $discount_pct; ?>%</span>
                            </td>
                            <td>
                                <b style="color: <?php echo $bag['stock'] > 0 ? 'var(--thanh-cong)' : 'var(--loi)'; ?>;"><?php echo $bag['stock']; ?></b> túi
                            </td>
                            <td><?php echo date('H:i d/m', $open_ts); ?></td>
                            <td>
                                <?php echo date('H:i', strtotime($bag['pickup_start'])) . ' - ' . date('H:i', strtotime($bag['pickup_end'])); ?>
                            </td>
                            <td><?php echo date('H:i d/m', $close_ts); ?></td>
                            <td>
                                <?php if ($now < $open_ts): ?>
                                    <span class="status-badge status-info">Sắp mở bán</span>
                                <?php elseif ($now > $close_ts): ?>
                                    <span class="status-badge status-secondary">Đã đóng cửa</span>
                                <?php elseif ($bag['stock'] <= 0): ?>
                                    <span class="status-badge status-danger">Hết hàng</span>
                                <?php else: ?>
                                    <span class="status-badge status-success">Đang mở bán</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div style="display: flex; gap: 6px;">
                                    <a href="<?php echo BASE_URL; ?>/tui_chi_tiet.php?id=<?php echo $bag['id']; ?>" class="btn btn-sm btn-outline" title="Xem trên web">👁️</a>
                                    <a href="sua_tui.php?id=<?php echo $bag['id']; ?>" class="btn btn-sm btn-outline" title="Sửa túi">✏️</a>
                                    <a href="xoa_tui.php?id=<?php echo $bag['id']; ?>" class="btn btn-sm btn-outline" style="color: #ef4444;" title="Xóa túi" onclick="return confirm('Bạn có chắc chắn muốn xóa túi này không?');">🗑️</a>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
