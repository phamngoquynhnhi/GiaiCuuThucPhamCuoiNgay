<?php
/**
 * TRANG CHỈNH SỬA TÚI BẤT NGỜ (shop/sua_tui.php)
 * Không cho phép sửa giá túi nếu đã có người đặt hàng
 */

$page_title = "Chỉnh sửa túi bất ngờ";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Chủ quán
$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

$bag_id = (int)($_GET['id'] ?? 0);
if ($bag_id <= 0) {
    header("Location: quan_ly_tui.php");
    exit;
}

// Lấy thông tin quán của user
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

// Lấy thông tin túi và kiểm tra xem có thuộc quán này không
$stmt_bag = mysqli_prepare($conn, "SELECT * FROM bags WHERE id = ? AND shop_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_bag, "ii", $bag_id, $shop_id);
mysqli_stmt_execute($stmt_bag);
$res_bag = mysqli_stmt_get_result($stmt_bag);
$bag = mysqli_fetch_assoc($res_bag);

if (!$bag) {
    die("Không tìm thấy túi hoặc bạn không có quyền chỉnh sửa túi này!");
}

// Kiểm tra xem túi đã có người đặt chưa
$stmt_cnt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM orders WHERE bag_id = ?");
mysqli_stmt_bind_param($stmt_cnt, "i", $bag_id);
mysqli_stmt_execute($stmt_cnt);
$res_cnt = mysqli_stmt_get_result($stmt_cnt);
$row_cnt = mysqli_fetch_assoc($res_cnt);
$has_orders = ($row_cnt['cnt'] > 0);

$discount_min = (float)get_setting($conn, 'discount_min', 30);
$discount_max = (float)get_setting($conn, 'discount_max', 50);
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $stock = (int)($_POST['stock'] ?? 0);
    $open_at = $_POST['open_at'] ?? '';
    $pickup_start = $_POST['pickup_start'] ?? '';
    $pickup_end = $_POST['pickup_end'] ?? '';
    $close_at = $_POST['close_at'] ?? '';

    // Nếu chưa có đơn đặt thì cho phép sửa giá
    if (!$has_orders) {
        $original_price = (float)($_POST['original_price'] ?? 0);
        $web_price = (float)($_POST['web_price'] ?? 0);
    } else {
        $original_price = (float)$bag['original_price'];
        $web_price = (float)$bag['web_price'];
    }

    if ($name === '' || $stock < 0) {
        $error = 'Vui lòng điền tên túi và số lượng tồn kho hợp lệ (>= 0)!';
    } elseif ($open_at === '' || $pickup_start === '' || $pickup_end === '' || $close_at === '') {
        $error = 'Vui lòng điền đầy đủ các mốc thời gian!';
    } else {
        // Kiểm tra giảm giá nếu sửa giá
        $discount_pct = (($original_price - $web_price) / $original_price) * 100;
        if ($discount_pct < $discount_min || $discount_pct > $discount_max) {
            $error = "Giá bán web phải giảm từ {$discount_min}% đến {$discount_max}% so với giá gốc! (Hiện tại đang giảm " . round($discount_pct, 1) . "%).";
        } else {
            // Kiểm tra thời gian
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
                $image_path = $bag['image'];
                if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                    $up = upload_image($_FILES['image']);
                    if ($up['success']) {
                        $image_path = $up['filename'];
                    } else {
                        $error = $up['error'];
                    }
                }

                if ($error === '') {
                    $stmt_up = mysqli_prepare($conn, "UPDATE bags SET name = ?, description = ?, image = ?, original_price = ?, web_price = ?, stock = ?, open_at = ?, pickup_start = ?, pickup_end = ?, close_at = ? WHERE id = ? AND shop_id = ?");
                    mysqli_stmt_bind_param($stmt_up, "sssddissssii", $name, $description, $image_path, $original_price, $web_price, $stock, $open_at, $pickup_start, $pickup_end, $close_at, $bag_id, $shop_id);

                    if (mysqli_stmt_execute($stmt_up)) {
                        header("Location: quan_ly_tui.php?msg=updated");
                        exit;
                    } else {
                        $error = 'Lỗi cập nhật CSDL: ' . mysqli_error($conn);
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
            <h2 style="color: var(--primary); margin-bottom: 4px;">✏️ Chỉnh Sửa Túi Bất Ngờ</h2>
            <p style="color: var(--text-muted);">Mã túi: #<?php echo $bag['id']; ?></p>
        </div>
        <a href="quan_ly_tui.php" class="btn btn-outline">⬅ Quay lại danh sách</a>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if ($has_orders): ?>
        <div class="alert alert-warning">
            🔒 <b>Khóa giá bán:</b> Túi này đã có <b><?php echo $row_cnt['cnt']; ?> đơn đặt hàng</b>. Để đảm bảo tính minh bạch và công bằng cho sinh viên đã mua, bạn không thể thay đổi giá gốc và giá web của túi này.
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <div class="form-group" style="margin-bottom: 14px;">
            <label>Tên túi bất ngờ *</label>
            <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($bag['name']); ?>">
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label>Mô tả gợi ý về túi</label>
            <textarea name="description" class="form-control" rows="3"><?php echo htmlspecialchars($bag['description'] ?? ''); ?></textarea>
        </div>

        <div style="display: flex; gap: 15px; margin-bottom: 14px; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1;">
                <label>Giá gốc niêm yết (VNĐ) <?php echo $has_orders ? '(Đã khóa)' : '*'; ?></label>
                <input type="number" name="original_price" step="1000" class="form-control" <?php echo $has_orders ? 'readonly style="background:var(--nen-toi); cursor:not-allowed; opacity:0.7;"' : 'required'; ?> value="<?php echo htmlspecialchars($bag['original_price']); ?>">
            </div>

            <div class="form-group" style="flex: 1;">
                <label>Giá bán web (VNĐ) <?php echo $has_orders ? '(Đã khóa)' : '*'; ?></label>
                <input type="number" name="web_price" step="1000" class="form-control" <?php echo $has_orders ? 'readonly style="background:var(--nen-toi); cursor:not-allowed; opacity:0.7;"' : 'required'; ?> value="<?php echo htmlspecialchars($bag['web_price']); ?>">
            </div>

            <div class="form-group" style="flex: 1;">
                <label>Số lượng tồn kho còn lại *</label>
                <input type="number" name="stock" min="0" max="100" class="form-control" required value="<?php echo htmlspecialchars($bag['stock']); ?>">
            </div>
        </div>

        <div style="background: var(--nen-2); border: 2px solid var(--vien); border-radius: var(--radius-md); padding: 16px; margin-bottom: 18px;">
            <h4 style="color: var(--chu); margin-bottom: 12px;">⏰ Thiết lập mốc thời gian</h4>
            
            <div style="display: flex; gap: 12px; margin-bottom: 12px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1;">
                    <label>1. Giờ mở bán trên web *</label>
                    <input type="datetime-local" name="open_at" class="form-control" required value="<?php echo date('Y-m-d\TH:i', strtotime($bag['open_at'])); ?>">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>2. Bắt đầu nhận túi tại quán *</label>
                    <input type="datetime-local" name="pickup_start" class="form-control" required value="<?php echo date('Y-m-d\TH:i', strtotime($bag['pickup_start'])); ?>">
                </div>
            </div>

            <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1;">
                    <label>3. Kết thúc nhận túi *</label>
                    <input type="datetime-local" name="pickup_end" class="form-control" required value="<?php echo date('Y-m-d\TH:i', strtotime($bag['pickup_end'])); ?>">
                </div>

                <div class="form-group" style="flex: 1;">
                    <label>4. Giờ đóng cửa quán (Hạn chót) *</label>
                    <input type="datetime-local" name="close_at" class="form-control" required value="<?php echo date('Y-m-d\TH:i', strtotime($bag['close_at'])); ?>">
                </div>
            </div>
        </div>

        <div class="form-group" style="margin-bottom: 20px;">
            <label>Ảnh đại diện túi (Tải ảnh mới nếu muốn thay đổi)</label>
            <?php if (!empty($bag['image'])): ?>
                <div style="margin: 8px 0;">
                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($bag['image']); ?>" alt="Ảnh túi" style="max-width: 150px; height: 100px; object-fit: cover; border-radius: 8px; border: 1px solid var(--vien);">
                </div>
            <?php endif; ?>
            <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp">
        </div>

        <button type="submit" class="btn btn-primary" style="padding: 12px 28px; font-size: 1rem;">Lưu Cập Nhật</button>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
