<?php
/**
 * QUẢN LÝ NGƯỜI DÙNG (admin/nguoi_dung.php)
 * Cho phép xem danh sách, khóa/mở khóa tài khoản và đặt lại số lần cảnh cáo
 */

$page_title = "Quản lý người dùng";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

yeu_cau_admin();
$admin = require_role($conn, ['admin']);

$msg = '';

// Xử lý các thao tác quản trị
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $target_id = (int)$_GET['user_id'];

    if ($target_id === (int)$admin['id']) {
        $msg = "<div class='alert alert-danger'>❌ Bạn không thể tự thao tác trên chính tài khoản Admin đang đăng nhập!</div>";
    } else {
        if ($action === 'lock') {
            $stmt = mysqli_prepare($conn, "UPDATE users SET is_locked = 1 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            mysqli_stmt_execute($stmt);
            $msg = "<div class='alert alert-warning'>🔒 Đã khóa tài khoản #{$target_id}!</div>";
        } elseif ($action === 'unlock') {
            $stmt = mysqli_prepare($conn, "UPDATE users SET is_locked = 0 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            mysqli_stmt_execute($stmt);
            $msg = "<div class='alert alert-success'>🔓 Đã mở khóa tài khoản #{$target_id}!</div>";
        } elseif ($action === 'reset_warn') {
            $stmt = mysqli_prepare($conn, "UPDATE users SET warning_count = 0 WHERE id = ?");
            mysqli_stmt_bind_param($stmt, "i", $target_id);
            mysqli_stmt_execute($stmt);
            $msg = "<div class='alert alert-success'>🔄 Đã đặt lại số cảnh cáo của tài khoản #{$target_id} về 0!</div>";
        }
    }
}

// Lọc người dùng
$role_filter = trim($_GET['role'] ?? '');
$sql = "SELECT * FROM users";
if ($role_filter !== '') {
    $sql .= " WHERE role = '" . mysqli_real_escape_string($conn, $role_filter) . "'";
}
$sql .= " ORDER BY id DESC";
$res_users = mysqli_query($conn, $sql);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">👥 Quản Lý Người Dùng & Tài Khoản</h2>
            <p style="color: var(--text-muted); margin: 0;">Theo dõi sinh viên, chủ quán, số dư ví và xử lý kỷ luật tài khoản</p>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="nguoi_dung.php" class="btn btn-sm <?php echo $role_filter === '' ? 'btn-primary' : 'btn-outline'; ?>">Tất cả</a>
            <a href="nguoi_dung.php?role=student" class="btn btn-sm <?php echo $role_filter === 'student' ? 'btn-primary' : 'btn-outline'; ?>">Sinh viên</a>
            <a href="nguoi_dung.php?role=shop_owner" class="btn btn-sm <?php echo $role_filter === 'shop_owner' ? 'btn-primary' : 'btn-outline'; ?>">Chủ quán</a>
            <a href="nguoi_dung.php?role=admin" class="btn btn-sm <?php echo $role_filter === 'admin' ? 'btn-primary' : 'btn-outline'; ?>">Admin</a>
        </div>
    </div>

    <?php echo $msg; ?>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Họ và tên</th>
                    <th>Email</th>
                    <th>Vai trò</th>
                    <th>Số dư ví</th>
                    <th>Cảnh cáo</th>
                    <th>Trạng thái</th>
                    <th>Ngày tạo</th>
                    <th>Thao tác</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($u = mysqli_fetch_assoc($res_users)): ?>
                    <tr>
                        <td>#<?php echo $u['id']; ?></td>
                        <td><b><?php echo htmlspecialchars($u['name']); ?></b></td>
                        <td><?php echo htmlspecialchars($u['email']); ?></td>
                        <td><span class="role-tag"><?php echo htmlspecialchars($u['role']); ?></span></td>
                        <td><b style="color: var(--cam-sang); font-family: var(--font-game);"><?php echo format_money($u['balance']); ?></b></td>
                        <td>
                            <?php if ($u['warning_count'] > 0): ?>
                                <span class="status-badge status-warning">⚠️ <?php echo $u['warning_count']; ?> lần</span>
                            <?php else: ?>
                                <span style="color: var(--chu-phu);">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if ($u['is_locked'] == 1): ?>
                                <span class="status-badge status-danger">🚫 Đã khóa</span>
                            <?php else: ?>
                                <span class="status-badge status-success">🟢 Hoạt động</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('d/m/Y', strtotime($u['created_at'])); ?></td>
                        <td>
                            <?php if ($u['role'] !== 'admin'): ?>
                                <div style="display: flex; gap: 6px;">
                                    <?php if ($u['is_locked'] == 1): ?>
                                        <a href="nguoi_dung.php?action=unlock&user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-success" onclick="return confirm('Mở khóa tài khoản này?');">Mở khóa</a>
                                    <?php else: ?>
                                        <a href="nguoi_dung.php?action=lock&user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline" style="color: var(--loi);" onclick="return confirm('Khóa tài khoản này?');">Khóa</a>
                                    <?php endif; ?>

                                    <?php if ($u['warning_count'] > 0): ?>
                                        <a href="nguoi_dung.php?action=reset_warn&user_id=<?php echo $u['id']; ?>" class="btn btn-sm btn-outline" title="Đặt lại cảnh cáo về 0">Xóa cảnh cáo</a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: var(--chu-phu); font-size: 0.8rem;">Admin</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
