<?php
/**
 * TRANG BÁO CÁO GIAN LẬN / KHIẾU NẠI (student/khieu_nai.php)
 * Sinh viên báo cáo thực phẩm ôi thiu / nấm mốc / sai mô tả
 * Kiểm tra giới hạn số lần khiếu nại mỗi tháng (max_disputes_per_month)
 * Bắt buộc chọn lý do và tải lên ít nhất 1 ảnh bằng chứng
 */

$page_title = "Báo cáo khiếu nại đơn hàng";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$user = require_role($conn, ['student']);
$user_id = $user['id'];

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    header("Location: don_hang.php");
    exit;
}

// Lấy thông tin đơn hàng
$sql = "SELECT o.*, b.name as bag_name, s.name as shop_name, s.owner_id as shop_owner_id 
        FROM orders o 
        JOIN bags b ON o.bag_id = b.id 
        JOIN shops s ON o.shop_id = s.id 
        WHERE o.id = ? AND o.user_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $user_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$order = mysqli_fetch_assoc($res);

if (!$order) {
    die("Không tìm thấy đơn hàng!");
}

// Kiểm tra xem đơn đã có tranh chấp chưa
$stmt_disp = mysqli_prepare($conn, "SELECT * FROM disputes WHERE order_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_disp, "i", $order_id);
mysqli_stmt_execute($stmt_disp);
$res_disp = mysqli_stmt_get_result($stmt_disp);
$dispute = mysqli_fetch_assoc($res_disp);

$error = '';
$max_disputes = (int)get_setting($conn, 'max_disputes_per_month', 3);

// Kiểm tra số lần khiếu nại trong tháng hiện tại của sinh viên
$sql_cnt = "SELECT COUNT(*) as cnt 
            FROM disputes d 
            JOIN orders o ON d.order_id = o.id 
            WHERE o.user_id = ? 
              AND MONTH(d.created_at) = MONTH(CURRENT_DATE()) 
              AND YEAR(d.created_at) = YEAR(CURRENT_DATE())";
$stmt_cnt = mysqli_prepare($conn, $sql_cnt);
mysqli_stmt_bind_param($stmt_cnt, "i", $user_id);
mysqli_stmt_execute($stmt_cnt);
$res_cnt = mysqli_stmt_get_result($stmt_cnt);
$cnt_row = mysqli_fetch_assoc($res_cnt);
$disputes_this_month = (int)$cnt_row['cnt'];

// Xử lý gửi báo cáo khiếu nại mới
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$dispute) {
    $reason = trim($_POST['reason'] ?? '');
    $student_note = trim($_POST['student_note'] ?? '');

    // Kiểm tra điều kiện đơn hàng hợp lệ để khiếu nại:
    // Đơn phải ở trạng thái cho_nhan hoặc da_nhan và còn trong thời hạn hold_until
    $now = time();
    $is_valid_status = false;

    if ($order['status'] === 'cho_nhan') {
        $is_valid_status = true;
    } elseif ($order['status'] === 'da_nhan' && !empty($order['hold_until'])) {
        if ($now <= strtotime($order['hold_until'])) {
            $is_valid_status = true;
        }
    }

    if (!$is_valid_status) {
        $error = 'Đơn hàng đã hết thời hạn bảo vệ khiếu nại hoặc trạng thái không hợp lệ!';
    } elseif ($disputes_this_month >= $max_disputes) {
        $error = "Bạn đã đạt giới hạn tối đa {$max_disputes} lần khiếu nại trong tháng này! Không thể gửi thêm khiếu nại mới.";
    } elseif ($reason === '') {
        $error = 'Vui lòng chọn lý do khiếu nại!';
    } elseif (!isset($_FILES['student_image']) || $_FILES['student_image']['error'] !== UPLOAD_ERR_OK) {
        $error = 'Bắt buộc phải tải lên ít nhất 1 ảnh bằng chứng (ảnh đồ ăn hỏng, mốc, hóa đơn,...)!';
    } else {
        // Tải ảnh lên
        $upload_res = upload_image($_FILES['student_image']);
        if (!$upload_res['success']) {
            $error = $upload_res['error'];
        } else {
            $image_path = $upload_res['filename'];

            // THỰC HIỆN TRONG TRANSACTION
            mysqli_begin_transaction($conn);

            try {
                // Cập nhật trạng thái đơn hàng sang tranh_chap với điều kiện kiểm tra trạng thái cũ
                $stmt_up_order = mysqli_prepare($conn, "UPDATE orders 
                                                        SET status = 'tranh_chap' 
                                                        WHERE id = ? 
                                                          AND (status = 'cho_nhan' OR (status = 'da_nhan' AND hold_until >= NOW())) 
                                                          AND user_id = ?");
                mysqli_stmt_bind_param($stmt_up_order, "ii", $order_id, $user_id);
                mysqli_stmt_execute($stmt_up_order);

                if (mysqli_affected_rows($conn) <= 0) {
                    mysqli_rollback($conn);
                    $error = 'Không thể gửi khiếu nại do đơn hàng vừa hết thời gian giữ tiền hoặc bị thay đổi trạng thái!';
                } else {
                    // Thêm bản ghi vào bảng disputes
                    $stmt_in_disp = mysqli_prepare($conn, "INSERT INTO disputes (order_id, reason, student_note, student_image, status, created_at) VALUES (?, ?, ?, ?, 'cho_quan_phan_hoi', NOW())");
                    mysqli_stmt_bind_param($stmt_in_disp, "isss", $order_id, $reason, $student_note, $image_path);
                    mysqli_stmt_execute($stmt_in_disp);

                    // Thông báo cho chủ quán
                    $msg_shop = "Đơn hàng #{$order_id} vừa nhận được báo cáo khiếu nại từ sinh viên. Bạn có 48 giờ để gửi phản đối kèm ảnh.";
                    add_notification($conn, $order['shop_owner_id'], $msg_shop);

                    // Thông báo cho sinh viên
                    $msg_sv = "Bạn đã gửi báo cáo khiếu nại đơn #{$order_id} thành công. Tiền đang được đóng băng an toàn.";
                    add_notification($conn, $user_id, $msg_sv);

                    mysqli_commit($conn);
                    header("Location: chi_tiet_don.php?id=" . $order_id . "&msg=dispute_sent");
                    exit;
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = 'Lỗi hệ thống: ' . htmlspecialchars($e->getMessage());
            }
        }
    }
}

require_once __DIR__ . '/../header.php';
?>

<div class="content-box" style="max-width: 750px; margin: 20px auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="color: #dc2626; margin: 0 0 4px 0;">⚠️ Báo Cáo Gian Lận & Khiếu Nại</h2>
            <p style="color: var(--text-muted); margin: 0;">Đơn hàng: #<?php echo $order['id']; ?> — Túi: <b><?php echo htmlspecialchars($order['bag_name']); ?></b></p>
        </div>
        <a href="chi_tiet_don.php?id=<?php echo $order['id']; ?>" class="btn btn-outline">⬅ Quay lại đơn</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($dispute): ?>
        <!-- ĐÃ CÓ KHIẾU NẠI: HIỂN THỊ TIẾN TRÌNH VÀ BẰNG CHỨNG -->
        <div style="background: var(--nen-2); border: 2px solid var(--loi); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 10px;">
                <h3 style="color: var(--loi); margin: 0; font-family: var(--font-game);">Tình Trạng Hồ Sơ Khiếu Nại</h3>
                <span class="status-badge status-warning" style="font-size: 0.9rem;">
                    <?php 
                        $status_names = [
                            'cho_quan_phan_hoi' => '⏳ Chờ quán phản hồi (48h)',
                            'cho_admin_xu_ly' => '⚖️ Đang chờ Admin phân xử',
                            'chap_nhan_khieu_nai' => '✅ Admin chấp thuận (Đã hoàn tiền)',
                            'bac_khieu_nai' => '❌ Admin bác bỏ (Tiền về quán)'
                        ];
                        echo $status_names[$dispute['status']] ?? $dispute['status'];
                    ?>
                </span>
            </div>

            <div style="font-size: 0.92rem; color: var(--chu); line-height: 1.6;">
                <p><b>Lý do khiếu nại:</b> <?php echo htmlspecialchars($dispute['reason']); ?></p>
                <p><b>Ghi chú của bạn:</b> <?php echo nl2br(htmlspecialchars($dispute['student_note'] ?? 'Không có')); ?></p>
                <p><b>Thời gian gửi:</b> <?php echo date('H:i d/m/Y', strtotime($dispute['created_at'])); ?></p>
            </div>

            <div style="margin-top: 14px;">
                <b>Ảnh bằng chứng do bạn cung cấp:</b><br>
                <img src="<?php echo BASE_URL . '/' . htmlspecialchars($dispute['student_image']); ?>" alt="Bằng chứng" style="max-width: 250px; border-radius: 8px; margin-top: 6px; border: 1px solid var(--vien);">
            </div>

            <?php if (!empty($dispute['shop_note']) || !empty($dispute['shop_image'])): ?>
                <div style="margin-top: 20px; padding-top: 16px; border-top: 1px dashed var(--vien);">
                    <h4 style="color: var(--canh-bao); margin-bottom: 8px;">🏪 Phản đối từ Chủ Quán:</h4>
                    <p><b>Nội dung giải trình:</b> <?php echo nl2br(htmlspecialchars($dispute['shop_note'] ?? '')); ?></p>
                    <?php if (!empty($dispute['shop_image'])): ?>
                        <p><b>Ảnh đối chứng của quán:</b></p>
                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($dispute['shop_image']); ?>" alt="Ảnh quán" style="max-width: 250px; border-radius: 8px; border: 1px solid var(--vien);">
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($dispute['admin_note'])): ?>
                <div style="margin-top: 20px; padding: 14px; background: var(--nen-3); border-radius: 8px; border-left: 4px solid var(--thong-tin);">
                    <h4 style="color: var(--cam-sang); margin-bottom: 4px;">⚖️ Phán Quyết Của Quản Trị Viên (Admin):</h4>
                    <p style="font-size: 0.92rem; color: var(--chu); margin: 0;">
                        <?php echo nl2br(htmlspecialchars($dispute['admin_note'])); ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>

    <?php else: ?>
        <!-- FORM ĐIỀN BÁO CÁO KHIẾU NẠI MỚI -->
        <div style="background: var(--nen-3); border: 1px solid var(--canh-bao); border-radius: var(--radius-sm); padding: 14px; margin-bottom: 20px; font-size: 0.88rem; color: var(--chu);">
            📌 <b>Chính sách giải quyết tranh chấp:</b><br>
            • Khi bạn gửi khiếu nại, tiền đơn hàng (<b><?php echo format_money($order['price']); ?></b>) sẽ <b>tiếp tục bị giữ an toàn</b>.<br>
            • Quán có 48 giờ để giải trình. Admin sẽ là trọng tài đưa ra phán quyết.<br>
            • Mỗi sinh viên được gửi tối đa <b><?php echo $max_disputes; ?> lần khiếu nại/tháng</b> (bạn đã dùng <?php echo $disputes_this_month; ?>/<?php echo $max_disputes; ?>). Khiếu nại sai sự thật quá 3 lần sẽ bị khóa tài khoản!
        </div>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Lý do khiếu nại gian lận: *</label>
                <select name="reason" class="form-control" required>
                    <option value="">-- Chọn lý do chính xác --</option>
                    <option value="Đồ ăn bị ôi thiu / biến chất / có mùi lạ">Đồ ăn bị ôi thiu / biến chất / có mùi lạ</option>
                    <option value="Thực phẩm bị nấm mốc / có dị vật gây hại">Thực phẩm bị nấm mốc / có dị vật gây hại</option>
                    <option value="Giá trị thực tế quá thấp so với mô tả / giá gốc giả mạo">Giá trị thực tế quá thấp so với mô tả / giá gốc giả mạo</option>
                    <option value="Quán đóng cửa sớm / từ chối giao hàng khi tôi đến">Quán đóng cửa sớm / từ chối giao hàng khi tôi đến</option>
                    <option value="Lý do khác">Lý do khác</option>
                </select>
            </div>

            <div class="form-group" style="margin-bottom: 16px;">
                <label>Mô tả chi tiết tình trạng thực phẩm:</label>
                <textarea name="student_note" class="form-control" rows="4" placeholder="Mô tả rõ tình trạng đồ ăn, thời gian bạn đến lấy, mùi vị hoặc dấu hiệu hư hỏng..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 24px;">
                <label>Tải lên ảnh bằng chứng (BẮT BUỘC, tối đa 2MB) *</label>
                <input type="file" name="student_image" class="form-control" accept="image/png, image/jpeg, image/webp" required>
                <small style="color: #64748b; margin-top: 4px; display: block;">Chụp rõ món ăn có vấn đề hoặc hóa đơn/túi đựng để làm bằng chứng cho Admin phân xử.</small>
            </div>

            <button type="submit" class="btn btn-danger btn-block" style="padding: 13px; font-size: 1rem; font-weight: 700;">
                Gửi Báo Cáo Khiếu Nại & Đóng Băng Tiền
            </button>
        </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
