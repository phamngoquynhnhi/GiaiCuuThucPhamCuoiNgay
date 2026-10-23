<?php
/**
 * TRANG THÔNG BÁO HỆ THỐNG (student/thong_bao.php)
 * Dành cho mọi người dùng đã đăng nhập (Sinh viên, Chủ quán, Quản trị viên)
 */

$page_title = "Thông báo hệ thống";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$user = require_role($conn);
$user_id = $user['id'];

// Xử lý đánh dấu tất cả là đã đọc
if (isset($_POST['mark_all_read'])) {
    $stmt_read = mysqli_prepare($conn, "UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    mysqli_stmt_bind_param($stmt_read, "i", $user_id);
    mysqli_stmt_execute($stmt_read);
    header("Location: thong_bao.php");
    exit;
}

// Lấy danh sách thông báo
$stmt_notifs = mysqli_prepare($conn, "SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 50");
mysqli_stmt_bind_param($stmt_notifs, "i", $user_id);
mysqli_stmt_execute($stmt_notifs);
$res_notifs = mysqli_stmt_get_result($stmt_notifs);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box" style="max-width: 800px; margin: 20px auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 16px; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">🔔 Thông Báo Của Bạn</h2>
            <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">
                Cập nhật thông tin đơn hàng, thanh toán và xử lý khiếu nại
            </p>
        </div>

        <?php if ($unread_count > 0): ?>
            <form method="POST" action="" style="margin: 0;">
                <button type="submit" name="mark_all_read" class="btn btn-sm btn-outline">
                    ✓ Đánh dấu tất cả là đã đọc
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (mysqli_num_rows($res_notifs) == 0): ?>
        <div style="text-align: center; padding: 50px 20px; background: var(--nen-2); border-radius: var(--radius-md); border: 1px dashed var(--vien);">
            <div style="font-size: 3rem; margin-bottom: 10px;">📭</div>
            <h3 style="color: var(--chu); margin-bottom: 6px;">Bạn chưa có thông báo nào</h3>
            <p style="color: var(--chu-phu); font-size: 0.9rem;">Các tin tức về đơn hàng sẽ xuất hiện tại đây.</p>
        </div>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <?php while ($n = mysqli_fetch_assoc($res_notifs)): 
                $is_unread = ($n['is_read'] == 0);
            ?>
                <div style="background: <?php echo $is_unread ? 'var(--nen-3)' : 'var(--nen-2)'; ?>; border: 2px solid <?php echo $is_unread ? 'var(--cam)' : 'var(--vien)'; ?>; border-radius: var(--radius-sm); padding: 14px 18px; display: flex; gap: 14px; align-items: flex-start; box-shadow: <?php echo $is_unread ? '0 0 10px var(--phat-cam)' : 'none'; ?>;">
                    <div style="font-size: 1.5rem; line-height: 1;">
                        <?php echo $is_unread ? '🟢' : '⚪'; ?>
                    </div>
                    <div style="flex: 1;">
                        <p style="margin: 0 0 6px 0; font-size: 0.95rem; color: var(--chu); line-height: 1.4;">
                            <?php echo htmlspecialchars($n['message']); ?>
                        </p>
                        <span style="font-size: 0.78rem; color: var(--chu-phu);">
                            <?php echo date('H:i d/m/Y', strtotime($n['created_at'])); ?>
                        </span>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
