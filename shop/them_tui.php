<?php
/**
 * TRANG THÊM TÚI BẤT NGỜ MỚI (shop/them_tui.php)
 * Kiểm tra giảm giá 30-50% và ràng buộc thời gian mở bán - nhận hàng
 */

$page_title = "Thêm túi bất ngờ";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Chủ quán
$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

// Lấy quán của chủ quán
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
$error = '';

// Lấy cấu hình tỷ lệ giảm giá từ database
$discount_min = (float)get_setting($conn, 'discount_min', 30);
$discount_max = (float)get_setting($conn, 'discount_max', 50);

// Thiết lập giá trị mặc định cho thời gian thuận tiện nhập liệu
$default_open = date('Y-m-d\TH:i', strtotime('+10 minutes'));
$default_start = date('Y-m-d\TH:i', strtotime('+60 minutes'));
$default_end = date('Y-m-d\TH:i', strtotime('+120 minutes'));
$default_close = date('Y-m-d\TH:i', strtotime('+150 minutes'));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($shop['status'] !== 'da_duyet') {
        $error = '⛔ Quán của bạn chưa được Admin phê duyệt! Bạn không thể đăng bán túi cho tới khi Admin xác minh quán.';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $original_price = (float)($_POST['original_price'] ?? 0);
        $web_price = (float)($_POST['web_price'] ?? 0);
        $stock = (int)($_POST['stock'] ?? 0);
    $open_at = $_POST['open_at'] ?? '';
    $pickup_start = $_POST['pickup_start'] ?? '';
    $pickup_end = $_POST['pickup_end'] ?? '';
    $close_at = $_POST['close_at'] ?? '';

    // Validate dữ liệu cơ bản
    if ($name === '' || $original_price <= 0 || $web_price <= 0 || $stock <= 0) {
        $error = 'Vui lòng điền tên túi, giá gốc > 0, giá web > 0 và số lượng túi > 0!';
    } elseif ($open_at === '' || $pickup_start === '' || $pickup_end === '' || $close_at === '') {
        $error = 'Vui lòng chọn đầy đủ các mốc thời gian!';
    } else {
        // 1. Kiểm tra tỷ lệ giảm giá (bắt buộc 30% - 50%)
        $discount_pct = (($original_price - $web_price) / $original_price) * 100;
        if ($discount_pct < $discount_min || $discount_pct > $discount_max) {
            $error = "Giá bán trên web phải giảm từ {$discount_min}% đến {$discount_max}% so với giá gốc! (Hiện tại bạn đang để giảm " . round($discount_pct, 1) . "%).";
        } else {
            // 2. Kiểm tra ràng buộc thời gian: open_at < pickup_start < pickup_end <= close_at
            $ts_open = strtotime($open_at);
            $ts_start = strtotime($pickup_start);
            $ts_end = strtotime($pickup_end);
            $ts_close = strtotime($close_at);

            if ($ts_open >= $ts_start) {
                $error = 'Giờ mở bán phải diễn ra trước giờ bắt đầu nhận hàng!';
            } elseif ($ts_start >= $ts_end) {
                $error = 'Giờ bắt đầu nhận hàng phải trước giờ kết thúc nhận hàng!';
            } elseif ($ts_end > $ts_close) {
                $error = 'Giờ kết thúc nhận hàng không được vượt quá giờ đóng cửa!';
            } else {
                // 3. Xử lý upload ảnh nếu có
                $image_path = null;
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $up = upload_image($_FILES['image']);
                    if ($up['success']) {
                        $image_path = $up['filename'];
                    } else {
                        $error = $up['error'];
                    }
                }

                if ($error === '') {
                    // Thêm vào cơ sở dữ liệu
                    $stmt_in = mysqli_prepare($conn, "INSERT INTO bags (shop_id, name, description, image, original_price, web_price, stock, open_at, pickup_start, pickup_end, close_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt_in, "isssddissss", $shop_id, $name, $description, $image_path, $original_price, $web_price, $stock, $open_at, $pickup_start, $pickup_end, $close_at);

                    if (mysqli_stmt_execute($stmt_in)) {
                        header("Location: quan_ly_tui.php?msg=added");
                        exit;
                    } else {
                        $error = 'Lỗi lưu vào CSDL: ' . mysqli_error($conn);
                    }
                }
            }
        }
    }
}
}

require_once __DIR__ . '/../header.php';
?>

<div class="content-box" style="max-width: 800px; margin: 20px auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--primary); margin-bottom: 4px;">➕ Thêm Túi Bất Ngờ Mới</h2>
            <p style="color: var(--text-muted);">Quán: <b><?php echo htmlspecialchars($shop['name']); ?></b></p>
        </div>
        <a href="quan_ly_tui.php" class="btn btn-outline">⬅ Quay lại danh sách</a>
    </div>

    <?php if ($shop['status'] !== 'da_duyet'): ?>
        <div class="alert alert-warning" style="font-size: 0.95rem; line-height: 1.6; padding: 20px;">
            <h3 style="color: #b45309; margin-top: 0; margin-bottom: 8px;">⏳ Quán Của Bạn Đang Ở Trạng Thái "Chờ Duyệt"</h3>
            <p style="margin-bottom: 10px;">
                Để ngăn chặn các trường hợp <b>quán ảo, thông tin giả mạo hoặc đăng bậy phá hoại</b>, hệ thống yêu cầu Quản trị viên (Admin) phải xác minh địa chỉ và phê duyệt quán trước khi bạn được phép đăng bán túi bất ngờ.
            </p>
            <p style="margin: 0;">
                👉 Bạn có thể theo dõi trạng thái quán tại: <a href="ho_so_quan.php" style="color: #b45309; font-weight: bold; text-decoration: underline;">Trang Hồ Sơ Quán</a>.
            </p>
        </div>
    <?php else: ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <div style="background: var(--cam-mo); border-left: 4px solid var(--cam); border: 1px solid var(--cam); border-left-width: 4px; padding: 12px 16px; border-radius: 4px; margin-bottom: 20px; font-size: 0.9rem; color: var(--chu);">
        <b style="color: var(--cam-sang);">📌 Quy định mô hình Túi Bất Ngờ:</b><br>
        1. Giá bán web bắt buộc giảm từ <b><?php echo $discount_min; ?>% đến <?php echo $discount_max; ?>%</b> so với giá gốc.<br>
        2. Thứ tự thời gian bắt buộc: <b>Giờ mở bán &lt; Bắt đầu nhận &lt; Kết thúc nhận &le; Giờ đóng cửa</b>.
    </div>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group" style="margin-bottom: 14px;">
            <label>Tên túi bất ngờ *</label>
            <input type="text" name="name" class="form-control" required placeholder="Ví dụ: Túi Bánh Ngọt Cuối Ngày 3-4 Món" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label>Mô tả gợi ý về túi (không nêu chính xác từng món, chỉ nêu danh mục để giữ yếu tố bất ngờ)</label>
            <textarea name="description" class="form-control" rows="3" placeholder="Ví dụ: Túi gồm 3-4 phần bánh mặn và ngọt nướng trong ngày tại tiệm, đảm bảo còn hạn dùng và thơm ngon..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 14px; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1;">
                <label>Giá gốc niêm yết tại quán (VNĐ) *</label>
                <input type="number" name="original_price" step="1000" min="1000" class="form-control" required placeholder="Ví dụ: 80000" value="<?php echo htmlspecialchars($_POST['original_price'] ?? ''); ?>">
            </div>

            <div class="form-group" style="flex: 1;">
                <label>Giá bán web (Giảm 30%-50%) *</label>
                <input type="number" name="web_price" step="1000" min="1000" class="form-control" required placeholder="Ví dụ: 45000" value="<?php echo htmlspecialchars($_POST['web_price'] ?? ''); ?>">
            </div>

            <div class="form-group" style="flex: 1;">
                <label>Số lượng túi mở bán *</label>
                <input type="number" name="stock" min="1" max="100" class="form-control" required placeholder="Ví dụ: 5" value="<?php echo htmlspecialchars($_POST['stock'] ?? '5'); ?>">
            </div>
        </div>

        <div style="background: var(--nen-2); border: 2px solid var(--vien); border-radius: var(--radius-md); padding: 16px; margin-bottom: 18px;">
            <h4 style="color: var(--chu); margin-bottom: 12px;">⏰ Thiết lập mốc thời gian (Theo giờ máy chủ)</h4>
            
            <div style="display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1;">
                    <label>1. Giờ mở bán trên web *</label>
                    <input type="datetime-local" name="open_at" class="form-control" required value="<?php echo htmlspecialchars($_POST['open_at'] ?? $default_open); ?>">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>2. Bắt đầu nhận túi tại quán *</label>
                    <input type="datetime-local" name="pickup_start" class="form-control" required value="<?php echo htmlspecialchars($_POST['pickup_start'] ?? $default_start); ?>">
                </div>
            </div>

            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1;">
                    <label>3. Kết thúc nhận túi *</label>
                    <input type="datetime-local" name="pickup_end" class="form-control" required value="<?php echo htmlspecialchars($_POST['pickup_end'] ?? $default_end); ?>">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>4. Giờ đóng cửa quán (Hạn chót) *</label>
                    <input type="datetime-local" name="close_at" class="form-control" required value="<?php echo htmlspecialchars($_POST['close_at'] ?? $default_close); ?>">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Ảnh đại diện túi (JPG, PNG, WEBP, tối đa 2MB)</label>
            <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp">
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 1rem;">Đăng Bán Túi Bất Ngờ</button>
    </form>
    <?php endif; ?>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
