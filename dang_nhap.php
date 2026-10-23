<?php
/**
 * TRANG ĐĂNG NHẬP (dang_nhap.php)
 * Bố cục 2 cột giống ảnh mẫu:
 * - Cột trái: Đăng nhập hệ thống (Email, Mật khẩu, Nút đăng nhập)
 * - Cột phải: Phân quyền (Admin, Chủ quán, Người dùng)
 */

$page_title = "Đăng nhập";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Nếu đã đăng nhập thì chuyển trang tương ứng
if (isset($_SESSION['user_id'])) {
    if (($_SESSION['role'] ?? '') === 'admin') {
        header("Location: " . BASE_URL . "/admin/tong_quan.php");
    } elseif (($_SESSION['role'] ?? '') === 'shop_owner') {
        header("Location: " . BASE_URL . "/shop/quan_ly_tui.php");
    } else {
        header("Location: " . BASE_URL . "/index.php");
    }
    exit;
}

// Khởi tạo các biến
$email = '';
$selected_role = 'user'; // Mặc định là Người dùng
$error = '';
$success = '';

// Nếu vừa đăng ký thành công chuyển sang
if (isset($_GET['registered']) && $_GET['registered'] == 1) {
    $success = 'Đăng ký thành công! Vui lòng chọn đúng vai trò và đăng nhập.';
}

// Xử lý khi người dùng gửi form đăng nhập
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $selected_role = $_POST['role'] ?? 'user';

    // 1. Kiểm tra không để trống
    if ($email === '' || $password === '') {
        $error = 'Vui lòng nhập đầy đủ Email và Mật khẩu!';
    } else {
        // 2. Tìm user theo email bằng Prepared Statement
        $stmt = mysqli_prepare($conn, "SELECT id, name, email, password, role, is_locked FROM users WHERE email = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $email);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $user = mysqli_fetch_assoc($result);

        // 3. Kiểm tra mật khẩu bằng password_verify
        if ($user && password_verify($password, $user['password'])) {
            // Kiểm tra tài khoản có bị khóa không (is_locked = 1)
            if ($user['is_locked'] == 1) {
                $error = 'Tài khoản đã bị khóa';
            } else {
                // Kiểm tra phân quyền: tài khoản phải khớp vai trò đã chọn ở cột bên phải
                $role_db = $user['role'];
                $is_role_matched = false;

                if ($selected_role === 'admin' && $role_db === 'admin') {
                    $is_role_matched = true;
                } elseif ($selected_role === 'shop_owner' && $role_db === 'shop_owner') {
                    $is_role_matched = true;
                } elseif ($selected_role === 'user' && ($role_db === 'user' || $role_db === 'student')) {
                    $is_role_matched = true;
                }

                if (!$is_role_matched) {
                    $role_names = [
                        'admin' => 'Admin (Quản trị viên)',
                        'shop_owner' => 'Chủ quán',
                        'user' => 'Người dùng (Sinh viên)'
                    ];
                    $error = 'Tài khoản này không thuộc vai trò ' . ($role_names[$selected_role] ?? $selected_role) . '! Vui lòng chọn đúng vai trò của bạn ở cột Phân quyền.';
                } else {
                    // Đăng nhập thành công -> lưu session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['name']    = $user['name'];
                    $_SESSION['role']    = $role_db;

                    // Chuyển trang theo vai trò
                    if ($user['role'] === 'admin') {
                        header("Location: " . BASE_URL . "/admin/tong_quan.php");
                    } elseif ($user['role'] === 'shop_owner') {
                        header("Location: " . BASE_URL . "/shop/quan_ly_tui.php");
                    } else {
                        header("Location: " . BASE_URL . "/index.php");
                    }
                    exit;
                }
            }
        } else {
            // Sai email hoặc sai mật khẩu: luôn báo cùng một câu để bảo mật
            $error = 'Email hoặc mật khẩu không đúng';
        }
    }
}

require_once __DIR__ . '/header.php';
?>

<div class="auth-split-box">
    <form method="POST" action="" class="auth-split-grid">
        <!-- CỘT TRÁI: FORM ĐĂNG NHẬP CHÍNH -->
        <div class="auth-main-col">
            <h2 style="color: var(--text-main); font-size: 1.45rem; font-weight: 800; margin-bottom: 6px;">
                Đăng nhập hệ thống
            </h2>
            <p style="color: var(--text-muted); font-size: 0.88rem; margin-bottom: 20px;">
                Chào mừng bạn đến với Giải cứu thực phẩm Đà Nẵng
            </p>

            <!-- Thông báo thành công nếu có -->
            <?php if ($success !== ''): ?>
                <div class="alert alert-success" style="margin-bottom: 16px; font-size: 0.88rem;">
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>

            <!-- Thông báo lỗi màu đỏ -->
            <?php if ($error !== ''): ?>
                <div class="alert alert-danger" style="margin-bottom: 16px; font-size: 0.88rem;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Ô nhập Email / Tên đăng nhập -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; font-size: 0.88rem;">👤 Địa chỉ Email</label>
                <input type="email" name="email" class="form-control" style="height: 44px;" required placeholder="Nhập email của bạn..." value="<?php echo htmlspecialchars($email); ?>">
            </div>

            <!-- Ô nhập Mật khẩu -->
            <div class="form-group" style="margin-bottom: 16px;">
                <label style="font-weight: 600; font-size: 0.88rem;">🔒 Mật khẩu</label>
                <input type="password" name="password" class="form-control" style="height: 44px;" required placeholder="Nhập mật khẩu...">
            </div>

            <!-- Tùy chọn nhớ tài khoản -->
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 0.88rem;">
                <label style="display: inline-flex; align-items: center; gap: 6px; cursor: pointer; color: var(--chu-phu);">
                    <input type="checkbox" name="remember" value="1" checked>
                    Nhớ tài khoản
                </label>
                <a href="#" onclick="alert('Vui lòng liên hệ Admin qua email admin@giaicuu.vn để đặt lại mật khẩu.'); return false;" style="color: var(--cam-sang);">Quên mật khẩu?</a>
            </div>

            <!-- Nút Đăng nhập 3D kiểu game -->
            <button type="submit" class="btn btn-primary btn-block">
                Đăng Nhập
            </button>

            <!-- Link chuyển sang Đăng ký -->
            <div style="margin-top: 20px; text-align: center; font-size: 0.88rem; color: var(--chu-phu);">
                Bạn chưa có tài khoản? <a href="<?php echo BASE_URL; ?>/dang_ky.php" style="color: var(--cam-sang); font-weight: 700;">Đăng ký ngay</a>
            </div>
        </div>

        <!-- CỘT PHẢI: PHÂN QUYỀN (GIỐNG ẢNH MẪU) -->
        <div class="auth-role-col">
            <div class="auth-role-title">Phân quyền</div>
            <div class="auth-role-hint">Chọn vai trò của bạn trước khi bấm đăng nhập:</div>

            <!-- 1. VAI TRÒ ADMIN -->
            <label class="role-select-item">
                <input type="radio" name="role" value="admin" <?php echo ($selected_role === 'admin') ? 'checked' : ''; ?>>
                <div class="role-select-box">
                    <span class="role-select-icon">👤</span>
                    <div>
                        <span class="role-select-label">Admin</span>
                        <span class="role-select-desc">Quản trị viên</span>
                    </div>
                </div>
            </label>

            <!-- 2. VAI TRÒ CHỦ QUÁN -->
            <label class="role-select-item">
                <input type="radio" name="role" value="shop_owner" <?php echo ($selected_role === 'shop_owner') ? 'checked' : ''; ?>>
                <div class="role-select-box">
                    <span class="role-select-icon">🏪</span>
                    <div>
                        <span class="role-select-label">Chủ quán</span>
                        <span class="role-select-desc">Quán ăn / Tiệm bánh</span>
                    </div>
                </div>
            </label>

            <!-- 3. VAI TRÒ NGƯỜI DÙNG / SINH VIÊN -->
            <label class="role-select-item">
                <input type="radio" name="role" value="user" <?php echo ($selected_role === 'user') ? 'checked' : ''; ?>>
                <div class="role-select-box">
                    <span class="role-select-icon">🎓</span>
                    <div>
                        <span class="role-select-label">Người dùng</span>
                        <span class="role-select-desc">Sinh viên mua túi</span>
                    </div>
                </div>
            </label>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
