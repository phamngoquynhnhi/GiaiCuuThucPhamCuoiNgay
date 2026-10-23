<?php
/**
 * TRANG CÀI ĐẶT HỒ SƠ NGƯỜI DÙNG (student/ho_so.php)
 * Cho phép Sinh viên / Khách hàng cập nhật họ tên, số điện thoại và đổi mật khẩu
 * Viết bằng PHP thuần, mysqli prepared statements, dễ hiểu cho sinh viên năm 2
 */

$page_title = "Cài đặt hồ sơ cá nhân";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

// Bắt buộc đăng nhập
yeu_cau_dang_nhap();
$current_user = get_current_user_data($conn);
$user_id = $current_user['id'];

$error_info = '';
$success_info = '';
$error_pwd = '';
$success_pwd = '';

// 1. Xử lý cập nhật Họ tên & Số điện thoại
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_update_info'])) {
    $new_name = trim($_POST['name'] ?? '');
    $new_phone = trim($_POST['phone'] ?? '');

    if ($new_name === '') {
        $error_info = 'Họ và tên không được để trống!';
    } elseif ($new_phone !== '' && !preg_match('/^[0-9]{9,11}$/', $new_phone)) {
        $error_info = 'Số điện thoại không hợp lệ (phải từ 9 đến 11 chữ số)!';
    } else {
        $stmt_up = mysqli_prepare($conn, "UPDATE users SET name = ?, phone = ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_up, "ssi", $new_name, $new_phone, $user_id);
        if (mysqli_stmt_execute($stmt_up)) {
            $success_info = 'Đã cập nhật thông tin cá nhân thành công!';
            $_SESSION['name'] = $new_name;
            // Tải lại dữ liệu người dùng mới
            $current_user = get_current_user_data($conn);
        } else {
            $error_info = 'Lỗi cập nhật CSDL: ' . mysqli_error($conn);
        }
    }
}

// 2. Xử lý Đổi mật khẩu
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
        // Lấy mật khẩu mã hóa hiện tại trong CSDL
        $stmt_chk = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt_chk, "i", $user_id);
        mysqli_stmt_execute($stmt_chk);
        $res_chk = mysqli_stmt_get_result($stmt_chk);
        $user_row = mysqli_fetch_assoc($res_chk);

        if (!$user_row || !password_verify($current_pass, $user_row['password'])) {
            $error_pwd = 'Mật khẩu hiện tại không chính xác!';
        } else {
            $hash_new = password_hash($new_pass, PASSWORD_DEFAULT);
            $stmt_up_pwd = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt_up_pwd, "si", $hash_new, $user_id);
            if (mysqli_stmt_execute($stmt_up_pwd)) {
                $success_pwd = 'Đổi mật khẩu thành công! Vui lòng ghi nhớ mật khẩu mới của bạn.';
            } else {
                $error_pwd = 'Lỗi cập nhật mật khẩu: ' . mysqli_error($conn);
            }
        }
    }
}

// Đếm tổng số đơn hàng đã đặt
$stmt_orders = mysqli_prepare($conn, "SELECT COUNT(*) as total_orders FROM orders WHERE user_id = ?");
mysqli_stmt_bind_param($stmt_orders, "i", $user_id);
mysqli_stmt_execute($stmt_orders);
$total_orders = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_orders))['total_orders'] ?? 0;

require_once __DIR__ . '/../header.php';
?>

<div style="font-size: 0.88rem; color: var(--text-muted); margin-bottom: 16px;">
    <a href="<?php echo BASE_URL; ?>/index.php">Trang chủ</a> &gt; 
    <span style="color: var(--text-main); font-weight: 600;">Cài đặt hồ sơ cá nhân</span>
</div>

<div style="max-width: 900px; margin: 0 auto;">
    <!-- Khối tóm tắt thông tin tài khoản -->
    <div class="content-box" style="margin-bottom: 24px; background: var(--nen-2); border: 2px solid var(--vien); color: var(--chu); padding: 24px;">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 20px;">
            <div style="display: flex; align-items: center; gap: 16px;">
                <div style="width: 64px; height: 64px; border-radius: 50%; background: var(--nen-3); display: flex; align-items: center; justify-content: center; font-size: 2rem; border: 2px solid var(--cam);">
                    👤
                </div>
                <div>
                    <h2 style="margin: 0; font-size: 1.4rem; color: var(--chu); font-weight: 800; font-family: var(--font-game);">
                        <?php echo htmlspecialchars($current_user['name']); ?>
                    </h2>
                    <p style="margin: 4px 0 0 0; color: var(--chu-phu); font-size: 0.9rem;">
                        ✉️ <?php echo htmlspecialchars($current_user['email']); ?> • 
                        <span style="background: var(--cam-mo); color: var(--cam-sang); border: 1px solid var(--cam); padding: 2px 8px; border-radius: 12px; font-size: 0.8rem;">
                            Vai trò: <?php echo htmlspecialchars($current_user['role']); ?>
                        </span>
                    </p>
                </div>
            </div>

            <!-- Các chỉ số nhanh -->
            <div style="display: flex; gap: 20px; flex-wrap: wrap;">
                <div style="background: var(--nen-3); padding: 12px 18px; border-radius: 12px; border: 1px solid var(--vien); text-align: center;">
                    <div style="font-size: 0.78rem; color: var(--chu-phu); text-transform: uppercase;">Số dư ví ảo</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--cam-sang); font-family: var(--font-game);">
                        <?php echo format_money($current_user['balance']); ?>
                    </div>
                </div>

                <div style="background: var(--nen-3); padding: 12px 18px; border-radius: 12px; border: 1px solid var(--vien); text-align: center;">
                    <div style="font-size: 0.78rem; color: var(--chu-phu); text-transform: uppercase;">Đã cứu trợ</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: var(--cam-sang); font-family: var(--font-game);">
                        <?php echo $total_orders; ?> đơn
                    </div>
                </div>

                <div style="background: var(--nen-3); padding: 12px 18px; border-radius: 12px; border: 1px solid var(--vien); text-align: center;">
                    <div style="font-size: 0.78rem; color: var(--chu-phu); text-transform: uppercase;">Cảnh cáo</div>
                    <div style="font-size: 1.25rem; font-weight: 800; color: <?php echo $current_user['warning_count'] > 0 ? 'var(--loi)' : 'var(--chu)'; ?>; font-family: var(--font-game);">
                        <?php echo $current_user['warning_count']; ?>/3
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px;" class="profile-grid">
        <!-- KHỐI 1: CẬP NHẬT THÔNG TIN LIÊN HỆ -->
        <div class="content-box">
            <h3 style="color: var(--text-main); font-size: 1.15rem; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                📝 Cập Nhật Thông Tin Cá Nhân
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                Số điện thoại giúp chủ quán có thể gọi xác nhận đơn hàng khi cần thiết.
            </p>

            <?php if ($success_info !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_info); ?></div>
            <?php endif; ?>

            <?php if ($error_info !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_info); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action_update_info" value="1">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Địa chỉ Email (Định danh tài khoản)</label>
                    <input type="text" class="form-control" value="<?php echo htmlspecialchars($current_user['email']); ?>" readonly style="background: var(--nen-toi); cursor: not-allowed; color: var(--chu-phu); opacity: 0.7;">
                    <small style="color: var(--chu-phu); font-size: 0.8rem;">* Email dùng để đăng nhập và không thể thay đổi.</small>
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Họ và tên của bạn *</label>
                    <input type="text" name="name" class="form-control" required value="<?php echo htmlspecialchars($current_user['name']); ?>">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Số điện thoại liên lạc</label>
                    <input type="text" name="phone" class="form-control" placeholder="Ví dụ: 0905123456" value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>">
                    <small style="color: var(--chu-phu); font-size: 0.8rem;">* Nhập từ 9 đến 11 chữ số.</small>
                </div>

                <button type="submit" class="btn btn-primary btn-block" style="padding: 12px;">
                    💾 Lưu Thay Đổi
                </button>
            </form>
        </div>

        <!-- KHỐI 2: ĐỔI MẬT KHẨU -->
        <div class="content-box">
            <h3 style="color: var(--text-main); font-size: 1.15rem; margin-bottom: 8px; display: flex; align-items: center; gap: 8px;">
                🔒 Đổi Mật Khẩu
            </h3>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin-bottom: 16px;">
                Để bảo vệ tài khoản ví và dữ liệu đơn hàng của bạn.
            </p>

            <?php if ($success_pwd !== ''): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_pwd); ?></div>
            <?php endif; ?>

            <?php if ($error_pwd !== ''): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_pwd); ?></div>
            <?php endif; ?>

            <form method="POST" action="">
                <input type="hidden" name="action_change_password" value="1">

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Mật khẩu hiện tại *</label>
                    <input type="password" name="current_password" class="form-control" required placeholder="Nhập mật khẩu đang dùng">
                </div>

                <div class="form-group" style="margin-bottom: 14px;">
                    <label>Mật khẩu mới (tối thiểu 6 ký tự) *</label>
                    <input type="password" name="new_password" class="form-control" required placeholder="Nhập mật khẩu mới">
                </div>

                <div class="form-group" style="margin-bottom: 20px;">
                    <label>Xác nhận mật khẩu mới *</label>
                    <input type="password" name="confirm_password" class="form-control" required placeholder="Nhập lại mật khẩu mới">
                </div>

                <button type="submit" class="btn btn-outline btn-block" style="padding: 12px; border-color: var(--accent); color: var(--accent);">
                    🔑 Cập Nhật Mật Khẩu
                </button>
            </form>
        </div>
    </div>
</div>

<style>
@media (max-width: 768px) {
    .profile-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
