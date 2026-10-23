<?php
/**
 * TRANG DUYỆT QUÁN ĂN (admin/duyet_quan.php)
 * Dành cho Quản trị viên duyệt quán mới hoặc tạm dừng quán vi phạm
 */

$page_title = "Phê duyệt quán ăn";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

yeu_cau_admin();
$admin = require_role($conn, ['admin']);

$msg = '';

// Xử lý duyệt hoặc tạm dừng quán
if (isset($_GET['action']) && isset($_GET['shop_id'])) {
    $action = $_GET['action'];
    $shop_id = (int)$_GET['shop_id'];

    $stmt_find = mysqli_prepare($conn, "SELECT s.*, u.name as owner_name, u.id as owner_id FROM shops s JOIN users u ON s.owner_id = u.id WHERE s.id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt_find, "i", $shop_id);
    mysqli_stmt_execute($stmt_find);
    $res = mysqli_stmt_get_result($stmt_find);
    $shop = mysqli_fetch_assoc($res);

    if ($shop) {
        if ($action === 'approve') {
            $stmt_up = mysqli_prepare($conn, "UPDATE shops SET status = 'da_duyet' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up, "i", $shop_id);
            mysqli_stmt_execute($stmt_up);

            add_notification($conn, $shop['owner_id'], "🎉 Chúc mừng! Quán '{$shop['name']}' của bạn đã được Admin phê duyệt. Bạn có thể đăng bán túi bất ngờ ngay bây giờ!");
            $msg = "<div class='alert alert-success'>✅ Đã phê duyệt quán '<b>" . htmlspecialchars($shop['name']) . "</b>' thành công!</div>";
        } elseif ($action === 'suspend') {
            $stmt_up = mysqli_prepare($conn, "UPDATE shops SET status = 'cho_duyet' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up, "i", $shop_id);
            mysqli_stmt_execute($stmt_up);

            add_notification($conn, $shop['owner_id'], "⚠️ Quán '{$shop['name']}' của bạn đã bị chuyển về trạng thái Chờ duyệt bởi Quản trị viên.");
            $msg = "<div class='alert alert-warning'>⏸️ Đã tạm dừng hoạt động của quán '<b>" . htmlspecialchars($shop['name']) . "</b>'.</div>";
        }
    }
}

// Lấy danh sách quán
$filter = trim($_GET['status'] ?? '');
$sql = "SELECT s.*, u.name as owner_name, u.email as owner_email, 
               (SELECT COUNT(*) FROM bags b WHERE b.shop_id = s.id) as bag_count 
        FROM shops s 
        JOIN users u ON s.owner_id = u.id";

if ($filter === 'cho_duyet') {
    $sql .= " WHERE s.status = 'cho_duyet'";
} elseif ($filter === 'da_duyet') {
    $sql .= " WHERE s.status = 'da_duyet'";
}

$sql .= " ORDER BY CASE WHEN s.status = 'cho_duyet' THEN 0 ELSE 1 END, s.id DESC";
$res_shops = mysqli_query($conn, $sql);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">🏪 Phê Duyệt & Quản Lý Quán Ăn</h2>
            <p style="color: var(--text-muted); margin: 0;">Kiểm duyệt các quán đăng ký tham gia mạng lưới giải cứu thực phẩm</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="duyet_quan.php" class="btn btn-sm <?php echo $filter === '' ? 'btn-primary' : 'btn-outline'; ?>">Tất cả</a>
            <a href="duyet_quan.php?status=cho_duyet" class="btn btn-sm <?php echo $filter === 'cho_duyet' ? 'btn-primary' : 'btn-outline'; ?>">Chờ duyệt</a>
            <a href="duyet_quan.php?status=da_duyet" class="btn btn-sm <?php echo $filter === 'da_duyet' ? 'btn-primary' : 'btn-outline'; ?>">Đã duyệt</a>
        </div>
    </div>

    <?php echo $msg; ?>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th style="min-width: 80px;">Ảnh mặt tiền / GPKD</th>
                    <th>Tên quán & SĐT</th>
                    <th>Chủ quán</th>
                    <th>Địa chỉ / Quận</th>
                    <th>Loại ẩm thực</th>
                    <th>Số túi</th>
                    <th>Vi phạm</th>
                    <th>Trạng thái</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($s = mysqli_fetch_assoc($res_shops)): ?>
                    <tr>
                        <td>
                            <?php if (!empty($s['image'])): ?>
                                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($s['image']); ?>" target="_blank" title="Bấm để xem ảnh phóng to">
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($s['image']); ?>" alt="Ảnh mặt tiền" style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px; border: 1px solid var(--vien);">
                                </a>
                                <div style="font-size: 0.72rem; margin-top: 2px;">
                                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($s['image']); ?>" target="_blank" style="color: var(--cam-sang); text-decoration: underline;">🔍 Xem ảnh</a>
                                </div>
                            <?php else: ?>
                                <div style="width: 60px; height: 60px; background: var(--nen-3); border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 11px; color: var(--chu-phu); text-align: center; border: 1px dashed var(--vien); padding: 4px;">Chưa tải ảnh</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <b style="font-size: 0.95rem;"><?php echo htmlspecialchars($s['name']); ?></b>
                            <?php if (!empty($s['phone'])): ?>
                                <div style="font-size: 0.82rem; margin-top: 4px; color: var(--thanh-cong); font-weight: 600;">
                                    📞 <a href="tel:<?php echo htmlspecialchars($s['phone']); ?>" style="color: var(--thanh-cong); text-decoration: underline;"><?php echo htmlspecialchars($s['phone']); ?></a>
                                </div>
                            <?php else: ?>
                                <div style="font-size: 0.78rem; color: var(--chu-phu); margin-top: 4px;">📞 Chưa có SĐT</div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($s['owner_name']); ?></div>
                            <div style="font-size: 0.75rem; color: var(--chu-phu);"><?php echo htmlspecialchars($s['owner_email']); ?></div>
                        </td>
                        <td>
                            <div><?php echo htmlspecialchars($s['address']); ?></div>
                            <span class="status-badge status-secondary" style="font-size: 0.72rem;">Q. <?php echo htmlspecialchars($s['district']); ?></span>
                        </td>
                        <td><?php echo htmlspecialchars($s['type']); ?></td>
                        <td><?php echo $s['bag_count']; ?> túi</td>
                        <td>
                            <?php if ($s['violation_count'] > 0): ?>
                                <span class="status-badge status-danger">⚠️ <?php echo $s['violation_count']; ?> lần</span>
                            <?php else: ?>
                                <span class="status-badge status-success">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['status'] === 'da_duyet'): ?>
                                <span class="status-badge status-success">Đã duyệt</span>
                            <?php else: ?>
                                <span class="status-badge status-warning">Chờ duyệt</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($s['status'] === 'cho_duyet'): ?>
                                <a href="duyet_quan.php?action=approve&shop_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Xác nhận phê duyệt quán này?');">
                                    ✅ Duyệt quán
                                </a>
                            <?php else: ?>
                                <a href="duyet_quan.php?action=suspend&shop_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-outline" style="color: var(--loi);" onclick="return confirm('Tạm dừng hoạt động của quán này?');">
                                    ⏸️ Tạm dừng
                                </a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
