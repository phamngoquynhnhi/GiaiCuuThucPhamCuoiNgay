<?php
/**
 * TRANG CÀI ĐẶT HỒ SƠ QUÁN ĂN (shop/ho_so_quan.php)
 * Dành cho Chủ quán theo dõi trạng thái duyệt, cập nhật tên quán, địa chỉ, số điện thoại, ảnh đại diện và xem vị trí bản đồ
 */

$page_title = "Cài đặt quán & Hồ sơ";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Kiểm tra quyền Chủ quán
$user = require_role($conn, ['shop_owner']);
$owner_id = $user['id'];

$error = '';
$success = '';
$error_pwd = '';
$success_pwd = '';

// Lấy thông tin quán hiện tại
$stmt_shop = mysqli_prepare($conn, "SELECT * FROM shops WHERE owner_id = ? LIMIT 1");
mysqli_stmt_bind_param($stmt_shop, "i", $owner_id);
mysqli_stmt_execute($stmt_shop);
$res_shop = mysqli_stmt_get_result($stmt_shop);
$shop = mysqli_fetch_assoc($res_shop);

$districts = get_danang_districts();
$types = get_shop_types();

// 1. Xử lý cập nhật thông tin quán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_shop'])) {
    $shop_name = trim($_POST['name'] ?? '');
    $shop_address = trim($_POST['address'] ?? '');
    $shop_phone = trim($_POST['phone'] ?? '');
    $shop_district = $_POST['district'] ?? '';
    $shop_type = $_POST['type'] ?? '';

    if ($shop_name === '' || $shop_address === '' || $shop_phone === '' || $shop_district === '' || $shop_type === '') {
        $error = 'Vui lòng điền đầy đủ tên quán, địa chỉ, số điện thoại, quận và loại hình ẩm thực!';
    } elseif (!preg_match('/^[0-9]{9,11}$/', $shop_phone)) {
        $error = 'Số điện thoại quán không hợp lệ (phải từ 9 đến 11 chữ số)!';
    } else {
        $image_path = $shop ? $shop['image'] : null;

        // Xử lý upload ảnh mới nếu có
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $up = upload_image($_FILES['image']);
            if ($up['success']) {
                $image_path = $up['filename'];
            } else {
                $error = $up['error'];
            }
        }

        if ($error === '') {
            if ($shop) {
                // Cập nhật quán cũ (kèm SĐT)
                $stmt_up = mysqli_prepare($conn, "UPDATE shops SET name = ?, address = ?, phone = ?, district = ?, type = ?, image = ? WHERE id = ?");
                mysqli_stmt_bind_param($stmt_up, "ssssssi", $shop_name, $shop_address, $shop_phone, $shop_district, $shop_type, $image_path, $shop['id']);
                if (mysqli_stmt_execute($stmt_up)) {
                    $success = 'Đã cập nhật thông tin quán và địa chỉ thành công!';
                    // Tải lại thông tin mới
                    $shop['name'] = $shop_name;
                    $shop['address'] = $shop_address;
                    $shop['phone'] = $shop_phone;
                    $shop['district'] = $shop_district;
                    $shop['type'] = $shop_type;
                    $shop['image'] = $image_path;
                } else {
                    $error = 'Lỗi cập nhật CSDL: ' . mysqli_error($conn);
                }
            } else {
                // Thêm quán mới nếu chưa có
                $stmt_in = mysqli_prepare($conn, "INSERT INTO shops (owner_id, name, address, phone, district, type, image, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 'cho_duyet', NOW())");
                mysqli_stmt_bind_param($stmt_in, "issssss", $owner_id, $shop_name, $shop_address, $shop_phone, $shop_district, $shop_type, $image_path);
                if (mysqli_stmt_execute($stmt_in)) {
                    $success = 'Đã tạo hồ sơ quán thành công! Vui lòng chờ Admin phê duyệt.';
                    header("Location: ho_so_quan.php");
                    exit;
                } else {
                    $error = 'Lỗi tạo quán mới: ' . mysqli_error($conn);
                }
            }
        }
    }
}

// 2. Xử lý đổi mật khẩu chủ quán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_change_password'])) {
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ($current_pass === '' || $new_pass === '' || $confirm_pass === '') {
        $error_pwd = 'Vui lòng điền đầy đủ các ô mật khẩu!';
    } elseif (strlen($new_pass) < 6) {
        $error_pwd = 'Mật khẩu mới phải có tối thiểu 6 ký tự!';
    } elseif ($new_pass !== $confirm_pass) {
        $error_pwd = 'Mật khẩu mới và ô xác nhận không trùng khớp!';
    } else {
        $stmt_chk = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_chk, "i", $owner_id);
        mysqli_stmt_execute($stmt_chk);
        $user_row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_chk));

        if (!$user_row || !password_verify($current_pass, $user_row['password'])) {
            $error_pwd = 'Mật khẩu hiện tại không chính xác!';
        } else {
            $hash_new = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt_up_pwd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up_pwd, "si", $hash_new, $owner_id);
            if (mysqli_stmt_execute($stmt_up_pwd)) {
                $success_pwd = 'Đổi mật khẩu tài khoản chủ quán thành công!';
            } else {
                $error_pwd = 'Lỗi cập nhật mật khẩu: ' . mysqli_error($conn);
            }
        }
    }
}

require_once __DIR__ . '/../header.php';
?>

<div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
    <a href="<?php echo BASE_URL; ?>/index.php">Trang chủ</a> &gt; 
    <span style="color: var(--text-main); font-weight: 600;">Cài đặt quán & Hồ sơ</span>
</div>

<div style="max-width: 960px; margin: 0 auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">⚙️ Cài Đặt Hồ Sơ & Địa Chỉ Quán</h2>
            <p style="color: var(--text-muted); margin: 0; font-size: 0.9rem;">
                Quản lý địa chỉ nhận túi, hotline liên hệ và vị trí hiển thị trên bản đồ cứu trợ Đà Nẵng
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="quan_ly_tui.php" class="btn btn-outline">📦 Quản lý túi</a>
            <a href="nhap_ma.php" class="btn btn-primary">⚡ Nhập mã nhận</a>
        </div>
    </div>

    <?php if ($shop): ?>
        <!-- Thẻ trạng thái phê duyệt của quán -->
        <div style="background: var(--nen-2); border: 2px solid var(--vien); border-radius: var(--radius-md); padding: 18px 22px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; box-shadow: var(--shadow-card);">
            <div>
                <span style="font-size: 0.82rem; color: var(--chu-phu); font-weight: bold; text-transform: uppercase;">Trạng thái phê duyệt:</span>
                <div style="margin-top: 4px;">
                    <?php if ($shop['status'] === 'da_duyet'): ?>
                        <span class="status-badge status-success" style="font-size: 0.95rem; padding: 6px 14px;">
                            ✅ ĐÃ DUYỆT (Đang mở bán trên trang chủ)
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-warning" style="font-size: 0.95rem; padding: 6px 14px;">
                            ⏳ CHỜ DUYỆT (Chờ Admin xác minh mặt tiền/giấy phép)
                        </span>
                    <?php endif; ?>
                </div>
            </div>

            <div>
                <span style="font-size: 0.82rem; color: var(--chu-phu); font-weight: bold; text-transform: uppercase;">Mức độ uy tín:</span>
                <div style="margin-top: 4px;">
                    <?php if ($shop['violation_count'] > 0): ?>
                        <span class="status-badge status-danger" style="font-size: 0.95rem; padding: 6px 14px;">
                            ⚠️ <?php echo $shop['violation_count']; ?> lần vi phạm
                        </span>
                    <?php else: ?>
                        <span class="status-badge status-success" style="font-size: 0.95rem; padding: 6px 14px;">
                            ⭐ 0 vi phạm (Quán chuẩn uy tín)
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <?php if ($shop['status'] === 'cho_duyet'): ?>
            <div class="alert alert-warning" style="margin-bottom: 20px;">
                <b>📌 Chú ý:</b> Quán đang chờ Quản trị viên duyệt hồ sơ và ảnh mặt tiền. Khi được duyệt, các túi của quán sẽ lập tức hiển thị công khai trên bản đồ và trang chủ.
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <div style="display: grid; grid-template-columns: 1.2fr 0.8fr; gap: 24px;" class="shop-settings-grid">
        <!-- CỘT 1: FORM CẬP NHẬT THÔNG TIN QUÁN & ĐỊA CHỈ -->
        <div class="content-box">
            <h3 style="color: var(--text-main); font-size: 1.15rem; margin-bottom: 8px;">
                🏪 Thông Tin Quán & Liên Hệ
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                Cập nhật địa chỉ nhận túi hoặc số điện thoại khi có thay đổi.
            </p>

            <?php if ($success !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>

            <?php if ($error !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action_update_shop" value="1">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Tên quán ăn / Tiệm bánh *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($shop['name'] ?? ''); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Địa chỉ nhận túi cụ thể *</label>
                    <input type="text" name="address" class="form-control" required placeholder="Ví dụ: 86 Lê Duẩn, P. Thạch Thang" value="<?php echo htmlspecialchars($shop['address'] ?? ''); ?>">
                    <small style="color: var(--chu-phu); font-size: 0.8rem;">* Khách hàng sẽ xem địa chỉ này và tra bản đồ để đến lấy túi.</small>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Số điện thoại hotline quán *</label>
                    <input type="text" name="phone" class="form-control" required placeholder="Ví dụ: 0905123456" value="<?php echo htmlspecialchars($shop['phone'] ?? ''); ?>">
                    <small style="color: var(--chu-phu); font-size: 0.8rem;">* Số điện thoại để khách gọi khi đến trước cửa quán.</small>
                </div>

                <div style="display: flex; gap: 15px; margin-bottom: 16px; flex-wrap: wrap;">
                    <div class="form-group" style="flex: 1;">
                        <label>Quận tại Đà Nẵng *</label>
                        <select name="district" class="form-control">
                            <?php foreach ($districts as $d): ?>
                                <option value="<?php echo htmlspecialchars($d); ?>" <?php echo (($shop['district'] ?? '') === $d) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($d); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group" style="flex: 1;">
                        <label>Loại hình ẩm thực *</label>
                        <select name="type" class="form-control">
                            <?php foreach ($types as $t): ?>
                                <option value="<?php echo htmlspecialchars($t); ?>" <?php echo (($shop['type'] ?? '') === $t) ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($t); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>📸 Ảnh mặt tiền quán / Giấy phép kinh doanh (JPG, PNG, WEBP)</label>
                    <?php if (!empty($shop['image'])): ?>
                        <div style="margin: 8px 0;">
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($shop['image']); ?>" alt="Ảnh quán" style="max-width: 200px; height: 110px; object-fit: cover; border-radius: var(--radius-sm); border: 1px solid var(--border-color);">
                        </div>
                    <?php endif; ?>
                    <input type="file" name="image" class="form-control" accept="image/png, image/jpeg, image/webp">
                </div>

                <button type="submit" class="btn btn-primary" style="padding: 12px 24px; font-weight: bold;">
                    💾 Lưu Cập Nhật Quán
                </button>
            </form>
        </div>

        <!-- CỘT 2: BẢN ĐỒ VỊ TRÍ QUÁN & ĐỔI MẬT KHẨU -->
        <div>
            <!-- KHỐI BẢN ĐỒ VỊ TRÍ HIỆN TẠI -->
            <?php if ($shop && !empty($shop['address'])): 
                $map_query = urlencode($shop['address'] . ', ' . $shop['district'] . ', Đà Nẵng');
            ?>
                <div class="content-box" style="margin-bottom: 24px;">
                    <h3 style="color: var(--text-main); font-size: 1.05rem; margin-bottom: 8px;">
                        🗺️ Vị Trí Quán Trên Bản Đồ
                    </h3>
                    <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px;">
                        Kiểm tra vị trí khách hàng sẽ nhìn thấy khi đặt túi của quán:
                    </p>
                    <div style="border-radius: var(--radius-sm); overflow: hidden; border: 1px solid var(--border-color); margin-bottom: 10px;">
                        <iframe 
                            width="100%" 
                            height="200" 
                            style="border:0;" 
                            loading="lazy" 
                            allowfullscreen 
                            src="https://maps.google.com/maps?q=<?php echo $map_query; ?>&t=&z=15&ie=UTF8&iwloc=&output=embed">
                        </iframe>
                    </div>
                    <div style="font-size: 0.85rem; color: var(--chu-phu);">
                        📍 <b><?php echo htmlspecialchars($shop['address']); ?>, Q. <?php echo htmlspecialchars($shop['district']); ?></b>
                    </div>
                    <a href="https://www.google.com/maps/search/?api=1&query=<?php echo $map_query; ?>" target="_blank" class="btn btn-sm btn-outline" style="margin-top: 8px; display: inline-block;">
                        ↗ Mở trên Google Maps
                    </a>
                </div>
            <?php endif; ?>

            <!-- KHỐI ĐỔI MẬT KHẨU CHỦ QUÁN -->
            <div class="content-box">
                <h3 style="color: var(--text-main); font-size: 1.05rem; margin-bottom: 8px;">
                    🔒 Đổi Mật Khẩu Đăng Nhập
                </h3>
                <p style="font-size: 0.82rem; color: var(--text-muted); margin-bottom: 12px;">
                    Bảo vệ tài khoản và doanh thu của quán bạn.
                </p>

                <?php if ($success_pwd !== ''): ?>
                    <div class="alert alert-success" style="font-size: 0.85rem;"><?php echo htmlspecialchars($success_pwd); ?></div>
                <?php endif; ?>

                <?php if ($error_pwd !== ''): ?>
                    <div class="alert alert-danger" style="font-size: 0.85rem;"><?php echo htmlspecialchars($error_pwd); ?></div>
                <?php endif; ?>

                <form method="POST" action="">
                    <input type="hidden" name="action_change_password" value="1">

                    <div class="form-group" style="margin-bottom: 10px;">
                        <label style="font-size: 0.85rem;">Mật khẩu hiện tại *</label>
                        <input type="password" name="current_password" class="form-control" required style="height: 38px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 10px;">
                        <label style="font-size: 0.85rem;">Mật khẩu mới (>= 6 ký tự) *</label>
                        <input type="password" name="new_password" class="form-control" required style="height: 38px;">
                    </div>

                    <div class="form-group" style="margin-bottom: 14px;">
                        <label style="font-size: 0.85rem;">Xác nhận mật khẩu mới *</label>
                        <input type="password" name="confirm_password" class="form-control" required style="height: 38px;">
                    </div>

                    <button type="submit" class="btn btn-outline btn-block" style="padding: 10px; font-size: 0.9rem; color: var(--cam-sang); border-color: var(--cam);">
                        🔑 Cập Nhật Mật Khẩu
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<style>
@media (max-width: 800px) {
    .shop-settings-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
