<?php
/**
 * TRANG ĐƠN HÀNG CỦA TÔI (student/don_hang.php)
 * Hiển thị danh sách đơn của sinh viên, mã nhận hàng to rõ, trạng thái có màu
 */

$page_title = "Đơn hàng của tôi";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền sinh viên
$user = require_role($conn, ['student']);
$user_id = $user['id'];

// Lấy danh sách đơn hàng của sinh viên này
$sql_orders = "SELECT o.*, b.name as bag_name, b.image as bag_image, b.pickup_start, b.pickup_end, 
                      s.name as shop_name, s.address as shop_address, s.district as shop_district 
               FROM orders o 
               JOIN bags b ON o.bag_id = b.id 
               JOIN shops s ON o.shop_id = s.id 
               WHERE o.user_id = ? 
               ORDER BY o.id DESC";
$stmt = mysqli_prepare($conn, $sql_orders);
mysqli_stmt_bind_param($stmt, "i", $user_id);
mysqli_stmt_execute($stmt);
$res_orders = mysqli_stmt_get_result($stmt);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--primary); margin-bottom: 4px;">🛍️ Đơn Hàng Cứu Đồ Ăn Của Tôi</h2>
            <p style="color: var(--text-muted);">
                Theo dõi mã nhận hàng và trạng thái đơn túi bất ngờ
            </p>
        </div>
        <div>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">➕ Săn thêm túi mới</a>
        </div>
    </div>

    <?php if (mysqli_num_rows($res_orders) == 0): ?>
        <div style="text-align: center; padding: 50px 20px; background: var(--nen-2); border-radius: var(--radius-md); border: 1px dashed var(--vien);">
            <div style="font-size: 3rem; margin-bottom: 10px;">🎒</div>
            <h3 style="color: var(--chu); margin-bottom: 8px;">Bạn chưa có đơn đặt túi nào</h3>
            <p style="color: var(--chu-phu); font-size: 0.95rem; margin-bottom: 20px;">
                Hãy khám phá các túi đồ ăn cuối ngày đang được giảm giá 30% - 50% hôm nay!
            </p>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary">Khám phá túi ngay</a>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 18px;">
            <?php while ($order = mysqli_fetch_assoc($res_orders)): 
                $st_info = get_order_status_info($order['status']);
                $is_waiting = ($order['status'] === 'cho_nhan');
                $is_picked_up = ($order['status'] === 'da_nhan');
            ?>
                <div style="background: var(--nen-2); border: 2px solid var(--vien); border-radius: var(--radius-md); padding: 20px; box-shadow: var(--shadow-card); display: flex; flex-wrap: wrap; gap: 20px; align-items: center; justify-content: space-between;">
                    <!-- Cột 1: Thông tin túi & Quán -->
                    <div style="display: flex; gap: 15px; align-items: center; min-width: 280px; flex: 2;">
                        <?php if (!empty($order['bag_image'])): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($order['bag_image']); ?>" alt="Bag" style="width: 75px; height: 75px; object-fit: cover; border-radius: 12px; border: 1px solid var(--border-color);">
                        <?php else: ?>
                            <div style="width: 75px; height: 75px; border-radius: 12px; background: var(--nen-3); border: 1px solid var(--vien); display: flex; align-items: center; justify-content: center; font-size: 30px;">🎁</div>
                        <?php endif; ?>

                        <div>
                            <div style="font-size: 0.8rem; color: var(--chu-phu); margin-bottom: 2px;">
                                Đơn hàng #<?php echo $order['id']; ?> • Ngày <?php echo date('d/m/Y H:i', strtotime($order['created_at'])); ?>
                            </div>
                            <h3 style="margin: 0 0 4px 0; font-size: 1.15rem;">
                                <a href="chi_tiet_don.php?id=<?php echo $order['id']; ?>" style="color: var(--text-main);">
                                    <?php echo htmlspecialchars($order['bag_name']); ?>
                                </a>
                            </h3>
                            <div style="font-size: 0.85rem; color: var(--chu-phu);">
                                🏪 <b><?php echo htmlspecialchars($order['shop_name']); ?></b> (<?php echo htmlspecialchars($order['shop_address']); ?>, Q. <?php echo htmlspecialchars($order['shop_district']); ?>)
                            </div>
                            <div style="font-size: 0.82rem; color: var(--cam-sang); margin-top: 3px;">
                                ⏰ Khung giờ nhận: <?php echo date('H:i', strtotime($order['pickup_start'])) . ' - ' . date('H:i', strtotime($order['pickup_end'])); ?>
                            </div>
                        </div>
                    </div>

                    <!-- Cột 2: Mã nhận hàng to rõ ràng -->
                    <div style="background: var(--nen-3); border: 2px dashed var(--cam); border-radius: var(--radius-sm); padding: 10px 18px; text-align: center; min-width: 160px; box-shadow: 0 0 10px var(--phat-cam);">
                        <span style="font-size: 0.72rem; color: var(--cam-sang); font-weight: bold; display: block;">MÃ NHẬN HÀNG:</span>
                        <span style="font-family: var(--font-game); font-size: 1.6rem; font-weight: 900; color: var(--cam-sang); letter-spacing: 2px;">
                            <?php echo htmlspecialchars($order['pickup_code']); ?>
                        </span>
                    </div>

                    <!-- Cột 3: Giá và trạng thái -->
                    <div style="min-width: 150px; text-align: right;">
                        <div style="font-size: 1.25rem; font-weight: 900; color: var(--cam-sang); font-family: var(--font-game); margin-bottom: 6px;">
                            <?php echo format_money($order['price']); ?>
                        </div>
                        <div>
                            <span class="<?php echo $st_info['class']; ?>">
                                <?php echo htmlspecialchars($st_info['name']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- Cột 4: Nút chi tiết -->
                    <div>
                        <a href="chi_tiet_don.php?id=<?php echo $order['id']; ?>" class="btn btn-outline" style="white-space: nowrap;">
                            Xem chi tiết 🔍
                        </a>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
