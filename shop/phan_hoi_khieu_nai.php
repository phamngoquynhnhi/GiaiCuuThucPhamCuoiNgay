<?php
/**
 * TRANG PHẢN ĐỐI KHIẾU NẠI CỦA QUÁN (shop/phan_hoi_khieu_nai.php)
 * Dành cho Chủ quán xem khiếu nại của sinh viên và gửi phản biện / ảnh đối chứng
 */

$page_title = "Phản đối khiếu nại";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

$order_id = (int)($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    header("Location: don_hang.php");
    exit;
}

// Lấy thông tin đơn hàng và tranh chấp
$sql = "SELECT o.*, b.name as bag_name, s.name as shop_name, s.owner_id as shop_owner_id, 
               u.name as student_name, d.id as dispute_id, d.reason, d.student_note, d.student_image, 
               d.shop_note, d.shop_image, d.status as dispute_status, d.admin_note, d.created_at as dispute_date
        FROM orders o 
        JOIN bags b ON o.bag_id = b.id 
        JOIN shops s ON o.shop_id = s.id 
        JOIN users u ON o.user_id = u.id 
        JOIN disputes d ON o.id = d.order_id 
        WHERE o.id = ? AND s.owner_id = ? LIMIT 1";
$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "ii", $order_id, $owner_id);
mysqli_stmt_execute($stmt);
$res = mysqli_stmt_get_result($stmt);
$data = mysqli_fetch_assoc($res);

if (!$data) {
    die("Không tìm thấy hồ sơ tranh chấp này hoặc bạn không sở hữu quán liên quan!");
}

$error = '';
$success = '';

// Xử lý khi quán gửi phản biện
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $shop_note = trim($_POST['shop_note'] ?? '');

    if ($shop_note === '') {
        $error = 'Vui lòng nhập nội dung giải trình / phản đối của quán!';
    } else {
        $shop_image_path = $data['shop_image'];
        if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] === UPLOAD_ERR_OK) {
            $up = upload_image($_FILES['shop_image']);
            if ($up['success']) {
                $shop_image_path = $up['filename'];
            } else {
                $error = $up['error'];
            }
        }

        if ($error === '') {
            $stmt_up = mysqli_prepare($conn, "UPDATE disputes SET shop_note = ?, shop_image = ?, status = 'cho_admin_xu_ly' WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up, "ssi", $shop_note, $shop_image_path, $data['dispute_id']);

            if (mysqli_stmt_execute($stmt_up)) {
                $success = 'Đã gửi phản đối khiếu nại thành công! Hồ sơ đã được chuyển lên Quản trị viên để phân xử.';
                
                // Thông báo cho sinh viên
                add_notification($conn, $data['user_id'], "Quán ăn đã gửi phản hồi cho khiếu nại đơn #{$order_id}. Admin sẽ phân xử trong thời gian sớm nhất.");

                // Cập nhật lại dữ liệu hiển thị
                $data['shop_note'] = $shop_note;
                $data['shop_image'] = $shop_image_path;
                $data['dispute_status'] = 'cho_admin_xu_ly';
            } else {
                $error = 'Lỗi cập nhật CSDL: ' . mysqli_error($conn);
            }
        }
    }
}

require_once __DIR__ . '/../header.php';
?>

<div class="content-box" style="max-width: 800px; margin: 20px auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--accent); margin: 0 0 4px 0;">⚖️ Phản Đối Khiếu Nại Đơn Hàng #<?php echo $order_id; ?></h2>
            <p style="color: var(--text-muted); margin: 0;">Khách hàng: <b><?php echo htmlspecialchars($data['student_name']); ?></b></p>
        </div>
        <a href="don_hang.php" class="btn btn-outline">⬅ Quay lại danh sách đơn</a>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <!-- THÔNG TIN KHIẾU NẠI CỦA SINH VIÊN -->
    <div style="background: var(--nen-2); border: 2px solid var(--loi); border-radius: var(--radius-md); padding: 20px; margin-bottom: 24px;">
        <h3 style="color: var(--loi); margin-top: 0; font-family: var(--font-game);">Báo Cáo Từ Sinh Viên:</h3>
        <p><b>Lý do:</b> <span style="color: var(--loi); font-weight: bold;"><?php echo htmlspecialchars($data['reason']); ?></span></p>
        <p><b>Ghi chú chi tiết:</b> <?php echo nl2br(htmlspecialchars($data['student_note'] ?? 'Không có')); ?></p>
        <p><b>Thời gian gửi:</b> <?php echo date('H:i d/m/Y', strtotime($data['dispute_date'])); ?></p>
        
        <div style="margin-top: 12px;">
            <b>Ảnh bằng chứng do sinh viên chụp:</b><br>
            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($data['student_image']); ?>" alt="Ảnh bằng chứng của khách" style="max-width: 250px; border-radius: 8px; margin-top: 6px; border: 1px solid var(--vien);">
        </div>
    </div>

    <!-- NẾU ADMIN ĐÃ PHÁN QUYẾT -->
    <?php if (!empty($data['admin_note'])): ?>
        <div style="background: var(--nen-3); border: 1px solid var(--thanh-cong); border-radius: var(--radius-md); padding: 18px; margin-bottom: 24px;">
            <h4 style="color: var(--thanh-cong); margin-top: 0;">⚖️ Phán Quyết Từ Quản Trị Viên:</h4>
            <p style="margin: 0; color: var(--chu);"><?php echo nl2br(htmlspecialchars($data['admin_note'])); ?></p>
        </div>
    <?php endif; ?>

    <!-- FORM PHẢN BÁC CỦA CHỦ QUÁN -->
    <?php if ($data['dispute_status'] === 'cho_quan_phan_hoi'): ?>
        <h3 style="color: var(--text-main); margin-bottom: 12px;">📝 Gửi Phản Đối & Bằng Chứng Của Quán:</h3>
        <p style="color: var(--chu-phu); font-size: 0.9rem; margin-bottom: 16px;">
            Bạn có <b>48 giờ</b> để gửi phản biện. Hãy giải thích rõ tình trạng món ăn trước khi giao hoặc cung cấp hình ảnh minh chứng để bảo vệ quyền lợi của quán.
        </p>

        <form method="POST" action="" enctype="multipart/form-data">
            <div class="form-group" style="margin-bottom: 16px;">
                <label>Giải trình / Phản đối của quán: *</label>
                <textarea name="shop_note" class="form-control" rows="4" required placeholder="Ví dụ: Đồ ăn được đóng gói lúc 21h đảm bảo còn nóng và hạn sử dụng trong ngày, không có dấu hiệu ôi thiu như ảnh chụp..."></textarea>
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label>Ảnh đối chứng của quán (nếu có):</label>
                <input type="file" name="shop_image" class="form-control" accept="image/png, image/jpeg, image/webp">
            </div>

            <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: bold;">
                Gửi Phản Đối Cho Admin Xử Lý
            </button>
        </form>
    <?php else: ?>
        <div class="alert alert-info">
            <b>Trạng thái:</b> Hồ sơ phản đối của quán đã được ghi nhận và đang chờ Quản trị viên đưa ra quyết định cuối cùng.
            <?php if (!empty($data['shop_note'])): ?>
                <div style="margin-top: 10px; padding-top: 10px; border-top: 1px dashed #bae6fd;">
                    <b>Nội dung quán đã giải trình:</b> <?php echo nl2br(htmlspecialchars($data['shop_note'])); ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
