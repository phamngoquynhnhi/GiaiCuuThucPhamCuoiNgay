<?php
/**
 * TRANG ĐĂNG KÝ (dang_ky.php)
 * Có mục chọn vai trò để đăng ký:
 * 1. Người dùng (Sinh viên) - Mua túi thực phẩm cứu trợ
 * 2. Chủ quán - Mở bán túi đồ dư cuối ngày
 */

$page_title = "Đăng ký tài khoản";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Nếu đã đăng nhập thì chuyển về trang chủ
if (isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/index.php");
    exit;
}

// Khởi tạo các biến để giữ lại dữ liệu khi có lỗi
$name = '';
$email = '';
$role = 'user'; // Mặc định là Người dùng
$shop_name = '';
$shop_address = '';
$shop_phone = '';
$shop_district = 'Hải Châu';
$shop_type = 'Cơm / Món mặn';
$error = '';

$districts = get_danang_districts();
$types = get_shop_types();

// Xử lý khi người dùng gửi form
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $repassword = $_POST['repassword'] ?? '';
    $role = $_POST['role'] ?? 'user';

    // 1. Kiểm tra không để trống các trường chung
    if ($name === '' || $email === '' || $password === '' || $repassword === '') {
        $error = 'Vui lòng điền đầy đủ họ tên, email và mật khẩu!';
    }
    // 2. Kiểm tra email đúng định dạng
    elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Địa chỉ email không đúng định dạng!';
    }
    // 3. Mật khẩu tối thiểu 6 ký tự
    elseif (strlen($password) < 6) {
        $error = 'Mật khẩu phải có tối thiểu 6 ký tự!';
    }
    // 4. Mật khẩu nhập lại phải khớp
    elseif ($password !== $repassword) {
        $error = 'Mật khẩu nhập lại không khớp!';
    }
    // 5. Kiểm tra vai trò hợp lệ
    elseif (!in_array($role, ['user', 'shop_owner'])) {
        $error = 'Vai trò đăng ký không hợp lệ!';
    }
    else {
        $shop_image_path = null;

        // Nếu chọn vai trò Chủ quán, kiểm tra thông tin quán
        if ($role === 'shop_owner') {
            $shop_name = trim($_POST['shop_name'] ?? '');
            $shop_address = trim($_POST['shop_address'] ?? '');
            $shop_phone = trim($_POST['shop_phone'] ?? '');
            $shop_district = $_POST['shop_district'] ?? 'Hải Châu';
            $shop_type = $_POST['shop_type'] ?? 'Cơm / Món mặn';

            if ($shop_name === '' || $shop_address === '' || $shop_phone === '') {
                $error = 'Chủ quán vui lòng điền đầy đủ tên quán ăn, địa chỉ và số điện thoại liên hệ!';
            } elseif (!preg_match('/^[0-9]{9,11}$/', $shop_phone)) {
                $error = 'Số điện thoại quán không hợp lệ (phải có từ 9 đến 11 chữ số)!';
            } else {
                // Xử lý tải lên ảnh mặt tiền quán hoặc giấy phép kinh doanh
                if (isset($_FILES['shop_image']) && $_FILES['shop_image']['error'] === UPLOAD_ERR_OK) {
                    $up = upload_image($_FILES['shop_image']);
                    if ($up['success']) {
                        $shop_image_path = $up['filename'];
                    } else {
                        $error = 'Lỗi ảnh mặt tiền/GPKD: ' . $up['error'];
                    }
                }
            }
        }

        if ($error === '') {
            // Kiểm tra email đã có trong database chưa
            $stmt_check = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? LIMIT 1");
            mysqli_stmt_bind_param($stmt_check, "s", $email);
            mysqli_stmt_execute($stmt_check);
            $result_check = mysqli_stmt_get_result($stmt_check);

            if (mysqli_num_rows($result_check) > 0) {
                $error = 'Email này đã tồn tại trong hệ thống! Vui lòng chọn email khác.';
            } else {
                // Mã hóa mật khẩu bằng password_hash
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);

                // Sử dụng Transaction để lưu an toàn
                mysqli_begin_transaction($conn);

                // Thêm người dùng mới vào bảng users
                $stmt_insert = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, is_locked) VALUES (?, ?, ?, ?, 0)");
                mysqli_stmt_bind_param($stmt_insert, "ssss", $name, $email, $hashed_password, $role);
                $ok_user = mysqli_stmt_execute($stmt_insert);
                $user_id = mysqli_insert_id($conn);

                if ($ok_user && $role === 'shop_owner') {
                    // Nếu là Chủ quán: lưu thông tin quán vào bảng shops (trạng thái cho_duyet kèm SĐT và ảnh mặt tiền)
                    $stmt_shop = mysqli_prepare($conn, "INSERT INTO shops (owner_id, name, address, phone, district, type, image, violation_count, status, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, 0, 'cho_duyet', NOW())");
                    mysqli_stmt_bind_param($stmt_shop, "issssss", $user_id, $shop_name, $shop_address, $shop_phone, $shop_district, $shop_type, $shop_image_path);
                    $ok_shop = mysqli_stmt_execute($stmt_shop);

                    if ($ok_shop) {
                        mysqli_commit($conn);
                        header("Location: " . BASE_URL . "/dang_nhap.php?registered=1");
                        exit;
                    } else {
                        mysqli_rollback($conn);
                        $error = 'Lỗi khi lưu thông tin quán ăn. Vui lòng thử lại!';
                    }
                } elseif ($ok_user) {
                    // Người dùng thường (sinh viên)
                    mysqli_commit($conn);
                    header("Location: " . BASE_URL . "/dang_nhap.php?registered=1");
                    exit;
                } else {
                    mysqli_rollback($conn);
                    $error = 'Đã có lỗi xảy ra khi tạo tài khoản. Vui lòng thử lại!';
                }
            }
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<!-- Thẻ form đăng ký -->
<div class="content-box" style="max-width: 520px; margin: 30px auto; padding: 30px; border-radius: 18px; box-shadow: var(--shadow-card);">
    <h2 style="color: var(--primary); text-align: center; margin-bottom: 6px;">📝 Đăng Ký Tài Khoản</h2>
    <p style="color: var(--text-muted); text-align: center; margin-bottom: 22px; font-size: 0.9rem;">
        Tham gia mạng lưới Giải cứu thực phẩm tại TP. Đà Nẵng
    </p>

    <!-- Hiển thị thông báo lỗi màu đỏ nếu có -->
    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" style="margin-bottom: 18px; font-size: 0.9rem;">
            <?php echo htmlspecialchars($error); ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="" enctype="multipart/form-data">
        <!-- MỤC CHỌN VAI TRÒ ĐĂNG KÝ -->
        <div style="margin-bottom: 20px;">
            <label style="font-weight: 700; font-size: 0.92rem; display: block; margin-bottom: 10px; color: var(--text-main);">
                🎯 Bạn muốn đăng ký với vai trò gì? *
            </label>
            
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px;">
                <!-- VAI TRÒ 1: NGƯỜI DÙNG / SINH VIÊN -->
                <label class="role-select-item" style="margin: 0;">
                    <input type="radio" name="role" value="user" <?php echo ($role === 'user') ? 'checked' : ''; ?>>
                    <div class="role-select-box" style="padding: 10px 12px;">
                        <span class="role-select-icon" style="font-size: 1.3rem;">🎓</span>
                        <div>
                            <span class="role-select-label" style="font-size: 0.9rem;">Người dùng</span>
                            <span class="role-select-desc">Sinh viên mua túi</span>
                        </div>
                    </div>
                </label>

                <!-- VAI TRÒ 2: CHỦ QUÁN ĂN -->
                <label class="role-select-item" style="margin: 0;">
                    <input type="radio" name="role" value="shop_owner" <?php echo ($role === 'shop_owner') ? 'checked' : ''; ?>>
                    <div class="role-select-box" style="padding: 10px 12px;">
                        <span class="role-select-icon" style="font-size: 1.3rem;">🏪</span>
                        <div>
                            <span class="role-select-label" style="font-size: 0.9rem;">Chủ quán</span>
                            <span class="role-select-desc">Bán đồ ăn dư</span>
                        </div>
                    </div>
                </label>
            </div>
        </div>

        <!-- THÔNG TIN CHUNG TÀI KHOẢN -->
        <div class="form-group" style="margin-bottom: 14px;">
            <label style="font-weight: 600; font-size: 0.88rem;">Họ và tên *</label>
            <input type="text" name="name" class="form-control" style="height: 44px;" placeholder="Ví dụ: Nguyễn Văn An" value="<?php echo htmlspecialchars($name); ?>" required>
        </div>

        <div class="form-group" style="margin-bottom: 14px;">
            <label style="font-weight: 600; font-size: 0.88rem;">Địa chỉ Email *</label>
            <input type="email" name="email" class="form-control" style="height: 44px;" placeholder="email@gmail.com" value="<?php echo htmlspecialchars($email); ?>" required>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.88rem;">Mật khẩu ($\ge$ 6 ký tự) *</label>
                <input type="password" name="password" class="form-control" style="height: 44px;" placeholder="••••••" required>
            </div>

            <div class="form-group">
                <label style="font-weight: 600; font-size: 0.88rem;">Nhập lại mật khẩu *</label>
                <input type="password" name="repassword" class="form-control" style="height: 44px;" placeholder="••••••" required>
            </div>
        </div>

        <!-- THÔNG TIN BỔ SUNG CHO CHỦ QUÁN (HIỆN NẾU ĐĂNG KÝ LÀM CHỦ QUÁN) -->
        <div style="background: var(--nen-3); border: 2px dashed var(--cam); border-radius: var(--radius-sm); padding: 16px; margin-bottom: 20px;">
            <div style="font-weight: 700; color: var(--cam-sang); font-size: 0.9rem; margin-bottom: 8px; font-family: var(--font-heading);">
                🏪 Thông tin xác minh quán ăn tại Đà Nẵng (Dành cho Chủ quán):
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
                <label style="font-size: 0.85rem; color: var(--chu); font-weight: 600;">Tên quán ăn / Tiệm bánh: *</label>
                <input type="text" name="shop_name" class="form-control" placeholder="Ví dụ: Bánh Mì Đệ Nhất Đà Nẵng" value="<?php echo htmlspecialchars($shop_name); ?>">
            </div>

            <!-- Ô SỐ ĐIỆN THOẠI QUÁN -->
            <div class="form-group" style="margin-bottom: 10px;">
                <label style="font-size: 0.85rem; color: var(--chu); font-weight: 600;">Số điện thoại quán (để Admin liên hệ xác minh): *</label>
                <input type="text" name="shop_phone" class="form-control" placeholder="Ví dụ: 0905123456 (9 - 11 chữ số)" value="<?php echo htmlspecialchars($shop_phone); ?>">
            </div>

            <div class="form-group" style="margin-bottom: 10px;">
                <label style="font-size: 0.85rem; color: var(--chu); font-weight: 600;">Địa chỉ cụ thể: *</label>
                <input type="text" name="shop_address" class="form-control" placeholder="Ví dụ: 86 Lê Duẩn" value="<?php echo htmlspecialchars($shop_address); ?>">
            </div>

            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 10px;">
                <div class="form-group">
                    <label style="font-size: 0.85rem; color: var(--chu); font-weight: 600;">Quận tại Đà Nẵng:</label>
                    <select name="shop_district" class="form-control">
                        <?php foreach ($districts as $d): ?>
                            <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($shop_district === $d) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($d); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label style="font-size: 0.85rem; color: var(--chu); font-weight: 600;">Loại ẩm thực:</label>
                    <select name="shop_type" class="form-control">
                        <?php foreach ($types as $t): ?>
                            <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($shop_type === $t) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($t); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Ô TẢI LÊN ẢNH MẶT TIỀN QUÁN / GIẤY PHÉP KINH DOANH -->
            <div class="form-group" style="margin-bottom: 8px;">
                <label style="font-size: 0.85rem; color: var(--chu); font-weight: 600;">
                    📸 Tải lên ảnh mặt tiền quán / Giấy phép kinh doanh:
                </label>
                <input type="file" name="shop_image" class="form-control" accept="image/png, image/jpeg, image/webp">
                <small style="color: var(--chu-phu); font-size: 0.8rem; display: block; margin-top: 4px;">
                    * Hỗ trợ ảnh JPG, PNG, WEBP ($\le$ 2MB). Giúp Quản trị viên đối chiếu và phê duyệt quán của bạn nhanh chóng.
                </small>
            </div>

            <small style="color: var(--cam-sang); font-size: 0.8rem; display: block; margin-top: 6px;">
                * Nếu bạn chọn vai trò <b>Người dùng</b> ở trên, bạn có thể bỏ qua phần thông tin quán này.
            </small>
        </div>

        <!-- Nút Đăng ký 3D kiểu game -->
        <button type="submit" class="btn btn-primary btn-block">
            Hoàn Tất Đăng Ký
        </button>
    </form>

    <div style="margin-top: 18px; text-align: center; font-size: 0.9rem; color: var(--chu-phu);">
        Đã có tài khoản? <a href="<?php echo BASE_URL; ?>/dang_nhap.php" style="color: var(--cam-sang); font-weight: 700;">Đăng nhập ngay</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
