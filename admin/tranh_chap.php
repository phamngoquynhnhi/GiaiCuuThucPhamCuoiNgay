<?php
/**
 * TRANG XỬ LÝ TRANH CHẤP & PHÂN XỬ (admin/tranh_chap.php)
 * Quản trị viên phân xử khiếu nại:
 * - Hướng A: Hoàn 100% cho SV, quán bị ghi 1 vi phạm.
 * - Hướng B: Bác khiếu nại, tiền về quán, SV bị cảnh cáo (>= 3 cảnh cáo thì khóa).
 * Bắt buộc Admin ghi rõ lý do phân xử.
 */

$page_title = "Xử lý tranh chấp";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

yeu_cau_admin();
$admin = require_role($conn, ['admin']);
$error = '';
$success = '';

// Xử lý phân xử tranh chấp
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['dispute_id']) && isset($_POST['decision'])) {
    $dispute_id = (int)$_POST['dispute_id'];
    $decision = $_POST['decision'];
    $admin_note = trim($_POST['admin_note'] ?? '');

    if ($admin_note === '') {
        $error = 'Quản trị viên BẮT BUỘC phải ghi rõ lý do phân xử vào ô bên dưới!';
    } else {
        // Lấy thông tin tranh chấp và đơn hàng
        $sql = "SELECT d.*, o.price, o.user_id as student_id, o.shop_id, s.owner_id as shop_owner_id, s.name as shop_name, u.name as student_name 
                FROM disputes d 
                JOIN orders o ON d.order_id = o.id 
                JOIN shops s ON o.shop_id = s.id 
                JOIN users u ON o.user_id = u.id 
                WHERE d.id = ? AND o.status = 'tranh_chap' LIMIT 1";
        $stmt_find = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt_find, "i", $dispute_id);
        mysqli_stmt_execute($stmt_find);
        $res = mysqli_stmt_get_result($stmt_find);
        $d = mysqli_fetch_assoc($res);

        if (!$d) {
            $error = 'Không tìm thấy hồ sơ tranh chấp hoặc đơn hàng không còn ở trạng thái tranh chấp!';
        } else {
            $order_id = (int)$d['order_id'];
            $price = (float)$d['price'];
            $student_id = (int)$d['student_id'];
            $shop_owner_id = (int)$d['shop_owner_id'];
            $shop_id = (int)$d['shop_id'];

            // THỰC HIỆN PHÂN XỬ TRONG TRANSACTION
            mysqli_begin_transaction($conn);

            try {
                if ($decision === 'approve_student') {
                    // ---------------------------------------------------------
                    // HƯỚNG A: CHẤP THUẬN KHIẾU NẠI - HOÀN 100% TIỀN CHO SINH VIÊN
                    // Quán bị ghi 1 lần vi phạm
                    // ---------------------------------------------------------
                    $stmt_up_order = mysqli_prepare($conn, "UPDATE orders SET status = 'hoan_tien_sv' WHERE id = ? AND status = 'tranh_chap'");
                    mysqli_stmt_bind_param($stmt_up_order, "i", $order_id);
                    mysqli_stmt_execute($stmt_up_order);

                    if (mysqli_affected_rows($conn) <= 0) {
                        throw new Exception("Trạng thái đơn hàng đã bị thay đổi trước đó!");
                    }

                    // Cập nhật kết quả tranh chấp
                    $stmt_up_disp = mysqli_prepare($conn, "UPDATE disputes SET status = 'chap_nhan_khieu_nai', admin_note = ?, resolved_at = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_up_disp, "si", $admin_note, $dispute_id);
                    mysqli_stmt_execute($stmt_up_disp);

                    // Hoàn tiền cho sinh viên
                    $stmt_refund = mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_refund, "di", $price, $student_id);
                    mysqli_stmt_execute($stmt_refund);

                    // Ghi lịch sử ví sinh viên
                    $reason_sv = "Hoàn tiền 100% do khiếu nại được Admin chấp thuận đơn #" . $order_id;
                    $stmt_log = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, ?, NOW())");
                    mysqli_stmt_bind_param($stmt_log, "idsi", $student_id, $price, $reason_sv, $order_id);
                    mysqli_stmt_execute($stmt_log);

                    // Cộng 1 lần vi phạm cho quán
                    $stmt_vio = mysqli_prepare($conn, "UPDATE shops SET violation_count = violation_count + 1 WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_vio, "i", $shop_id);
                    mysqli_stmt_execute($stmt_vio);

                    // Gửi thông báo
                    add_notification($conn, $student_id, "Khiếu nại đơn #{$order_id} của bạn đã được Admin chấp thuận. Bạn nhận lại " . format_money($price) . " vào ví. Lý do: {$admin_note}");
                    add_notification($conn, $shop_owner_id, "Admin đã phân xử hoàn tiền cho sinh viên đơn #{$order_id}. Quán bị ghi nhận 1 lần vi phạm chất lượng. Lý do: {$admin_note}");

                    mysqli_commit($conn);
                    $success = "✅ Đã phân xử: HOÀN 100% TIỀN cho sinh viên và GHI 1 LẦN VI PHẠM cho quán {$d['shop_name']}!";

                } elseif ($decision === 'reject_student') {
                    // ---------------------------------------------------------
                    // HƯỚNG B: BÁC KHIẾU NẠI - TIỀN VỀ VÍ QUÁN
                    // Sinh viên bị cảnh cáo (>= 3 lần thì khóa)
                    // ---------------------------------------------------------
                    $stmt_up_order = mysqli_prepare($conn, "UPDATE orders SET status = 'chuyen_tien_quan' WHERE id = ? AND status = 'tranh_chap'");
                    mysqli_stmt_bind_param($stmt_up_order, "i", $order_id);
                    mysqli_stmt_execute($stmt_up_order);

                    if (mysqli_affected_rows($conn) <= 0) {
                        throw new Exception("Trạng thái đơn hàng đã bị thay đổi trước đó!");
                    }

                    // Cập nhật kết quả tranh chấp
                    $stmt_up_disp = mysqli_prepare($conn, "UPDATE disputes SET status = 'bac_khieu_nai', admin_note = ?, resolved_at = NOW() WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_up_disp, "si", $admin_note, $dispute_id);
                    mysqli_stmt_execute($stmt_up_disp);

                    // Chuyển tiền cho quán và trích hoa hồng nền tảng cho Admin
                    complete_order_payment($conn, $order_id, $price, $shop_owner_id, $d['shop_name'] ?? '', 'Admin bác bỏ khiếu nại của sinh viên');

                    // Tăng số lần cảnh cáo của sinh viên
                    $stmt_warn = mysqli_prepare($conn, "UPDATE users SET warning_count = warning_count + 1 WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_warn, "i", $student_id);
                    mysqli_stmt_execute($stmt_warn);

                    // Kiểm tra xem số cảnh cáo đã đạt >= 3 chưa
                    $stmt_chk_warn = mysqli_prepare($conn, "SELECT warning_count FROM users WHERE id = ?");
                    mysqli_stmt_bind_param($stmt_chk_warn, "i", $student_id);
                    mysqli_stmt_execute($stmt_chk_warn);
                    $res_chk = mysqli_stmt_get_result($stmt_chk_warn);
                    $user_chk = mysqli_fetch_assoc($res_chk);
                    $new_warn_count = (int)$user_chk['warning_count'];

                    if ($new_warn_count >= 3) {
                        // Khóa tài khoản
                        $stmt_lock = mysqli_prepare($conn, "UPDATE users SET is_locked = 1 WHERE id = ?");
                        mysqli_stmt_bind_param($stmt_lock, "i", $student_id);
                        mysqli_stmt_execute($stmt_lock);

                        add_notification($conn, $student_id, "Tài khoản của bạn đã BỊ KHÓA do nhận đủ 3 lần cảnh cáo khiếu nại sai quy định! Lý do phán quyết: {$admin_note}");
                    } else {
                        add_notification($conn, $student_id, "Khiếu nại đơn #{$order_id} đã bị Admin bác bỏ. Bạn bị ghi nhận 1 cảnh cáo ({$new_warn_count}/3). Lý do: {$admin_note}");
                    }

                    // Thông báo cho chủ quán
                    add_notification($conn, $shop_owner_id, "Admin đã bác khiếu nại đơn #{$order_id}. Bạn nhận được " . format_money($price) . " vào ví.");

                    mysqli_commit($conn);
                    $success = "✅ Đã phân xử: BÁC KHIẾU NẠI, CHUYỂN TIỀN CHO QUÁN và CẢNH CÁO sinh viên {$d['student_name']} (Lần {$new_warn_count}/3)!";
                }
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $error = "Lỗi xử lý: " . htmlspecialchars($e->getMessage());
            }
        }
    }
}

// Lọc tranh chấp
$filter = trim($_GET['status'] ?? '');
$sql = "SELECT d.*, o.price, o.status as order_status, b.name as bag_name, s.name as shop_name, s.violation_count, 
               u.name as student_name, u.email as student_email, u.warning_count as student_warnings 
        FROM disputes d 
        JOIN orders o ON d.order_id = o.id 
        JOIN bags b ON o.bag_id = b.id 
        JOIN shops s ON o.shop_id = s.id 
        JOIN users u ON o.user_id = u.id";

if ($filter !== '') {
    $sql .= " WHERE d.status = '" . mysqli_real_escape_string($conn, $filter) . "'";
}
$sql .= " ORDER BY CASE WHEN d.status IN ('cho_quan_phan_hoi', 'cho_admin_xu_ly') THEN 0 ELSE 1 END, d.id DESC";
$res_disp = mysqli_query($conn, $sql);

require_once __DIR__ . '/../header.php';
?>

<div class="content-box">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <div>
            <h2 style="color: var(--accent); margin: 0 0 4px 0;">⚖️ Phân Xử Tranh Chấp & Khiếu Nại</h2>
            <p style="color: var(--text-muted); margin: 0;">Trọng tài độc lập bảo vệ quyền lợi sinh viên và bảo vệ uy tín các quán ăn</p>
        </div>
        <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <a href="tranh_chap.php" class="btn btn-sm <?php echo $filter === '' ? 'btn-primary' : 'btn-outline'; ?>">Tất cả</a>
            <a href="tranh_chap.php?status=cho_admin_xu_ly" class="btn btn-sm <?php echo $filter === 'cho_admin_xu_ly' ? 'btn-accent' : 'btn-outline'; ?>">Chờ Admin xử lý</a>
            <a href="tranh_chap.php?status=cho_quan_phan_hoi" class="btn btn-sm <?php echo $filter === 'cho_quan_phan_hoi' ? 'btn-primary' : 'btn-outline'; ?>">Chờ quán phản hồi</a>
            <a href="tranh_chap.php?status=chap_nhan_khieu_nai" class="btn btn-sm <?php echo $filter === 'chap_nhan_khieu_nai' ? 'btn-success' : 'btn-outline'; ?>">Đã hoàn tiền SV</a>
            <a href="tranh_chap.php?status=bac_khieu_nai" class="btn btn-sm <?php echo $filter === 'bac_khieu_nai' ? 'btn-primary' : 'btn-outline'; ?>">Đã bác khiếu nại</a>
        </div>
    </div>

    <?php if ($success !== ''): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
    <?php endif; ?>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>

    <?php if (mysqli_num_rows($res_disp) == 0): ?>
        <p style="color: var(--chu-phu); font-style: italic;">Không có hồ sơ tranh chấp nào.</p>
    <?php else: ?>
        <div style="display: flex; flex-direction: column; gap: 24px;">
            <?php while ($d = mysqli_fetch_assoc($res_disp)): 
                $is_pending_admin = ($d['status'] === 'cho_admin_xu_ly' || $d['status'] === 'cho_quan_phan_hoi');
            ?>
                <div style="background: var(--nen-2); border: 2px solid <?php echo $is_pending_admin ? 'var(--loi)' : 'var(--vien)'; ?>; border-radius: var(--radius-md); padding: 22px; box-shadow: var(--shadow-card);">
                    <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border-color); padding-bottom: 14px; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
                        <div>
                            <span style="font-weight: 800; font-size: 1.1rem; color: var(--text-main);">
                                Hồ sơ khiếu nại đơn #<?php echo $d['order_id']; ?>
                            </span>
                            <span style="color: var(--chu-phu); font-size: 0.85rem; margin-left: 8px;">
                                Ngày gửi: <?php echo date('H:i d/m/Y', strtotime($d['created_at'])); ?>
                            </span>
                        </div>
                        <div>
                            <span style="font-size: 1.15rem; font-weight: 900; color: var(--cam-sang); font-family: var(--font-game); margin-right: 12px;">
                                <?php echo format_money($d['price']); ?>
                            </span>
                            <span class="status-badge <?php echo $is_pending_admin ? 'status-danger' : 'status-secondary'; ?>">
                                <?php echo htmlspecialchars($d['status']); ?>
                            </span>
                        </div>
                    </div>

                    <!-- THÔNG TIN ĐỐI CHIẾU 2 BÊN -->
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 18px;" class="dispute-grid">
                        <!-- BÊN SINH VIÊN KHIẾU NẠI -->
                        <div style="background: var(--nen-3); border: 1px solid var(--loi); border-radius: var(--radius-sm); padding: 14px;">
                            <h4 style="color: var(--loi); margin: 0 0 8px 0; font-family: var(--font-game);">🎓 Sinh viên: <?php echo htmlspecialchars($d['student_name']); ?></h4>
                            <p style="font-size: 0.85rem; margin-bottom: 4px;">• Cảnh cáo hiện tại: <b><?php echo $d['student_warnings']; ?>/3</b></p>
                            <p style="font-size: 0.88rem; margin-bottom: 6px;"><b>Lý do:</b> <span style="color: var(--loi); font-weight: bold;"><?php echo htmlspecialchars($d['reason']); ?></span></p>
                            <p style="font-size: 0.85rem; margin-bottom: 8px;"><b>Mô tả:</b> <?php echo nl2br(htmlspecialchars($d['student_note'] ?? '')); ?></p>
                            
                            <div>
                                <b>Ảnh bằng chứng của sinh viên:</b><br>
                                <a href="<?php echo BASE_URL . '/' . htmlspecialchars($d['student_image']); ?>" target="_blank">
                                    <img src="<?php echo BASE_URL . '/' . htmlspecialchars($d['student_image']); ?>" alt="Ảnh SV" style="max-width: 180px; max-height: 140px; object-fit: cover; border-radius: 6px; margin-top: 4px; border: 1px solid var(--vien);">
                                </a>
                            </div>
                        </div>

                        <!-- BÊN CHỦ QUÁN PHẢN HỒI -->
                        <div style="background: var(--nen-3); border: 1px solid var(--vien); border-radius: var(--radius-sm); padding: 14px;">
                            <h4 style="color: var(--chu); margin: 0 0 8px 0; font-family: var(--font-game);">🏪 Quán: <?php echo htmlspecialchars($d['shop_name']); ?></h4>
                            <p style="font-size: 0.85rem; margin-bottom: 4px;">• Vi phạm hiện tại: <b><?php echo $d['violation_count']; ?> lần</b></p>
                            <p style="font-size: 0.85rem; margin-bottom: 4px;">• Món đặt: <b><?php echo htmlspecialchars($d['bag_name']); ?></b></p>
                            
                            <?php if (!empty($d['shop_note'])): ?>
                                <p style="font-size: 0.88rem; margin-bottom: 6px;"><b>Quán giải trình:</b> <?php echo nl2br(htmlspecialchars($d['shop_note'])); ?></p>
                                <?php if (!empty($d['shop_image'])): ?>
                                    <b>Ảnh quán đối chứng:</b><br>
                                    <a href="<?php echo BASE_URL . '/' . htmlspecialchars($d['shop_image']); ?>" target="_blank">
                                        <img src="<?php echo BASE_URL . '/' . htmlspecialchars($d['shop_image']); ?>" alt="Ảnh quán" style="max-width: 180px; max-height: 140px; object-fit: cover; border-radius: 6px; margin-top: 4px; border: 1px solid var(--border-color);">
                                    </a>
                                <?php endif; ?>
                            <?php else: ?>
                                <div style="color: var(--chu-phu); font-style: italic; font-size: 0.88rem; margin-top: 10px;">
                                    ⏳ Quán chưa gửi phản đối (thời hạn 48 giờ). Admin có thể phân xử ngay nếu bằng chứng sinh viên đã quá rõ ràng.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- NẾU ĐÃ PHÂN XỬ XONG -->
                    <?php if (!empty($d['admin_note'])): ?>
                        <div style="background: var(--nen-3); border: 1px solid var(--thanh-cong); border-radius: 6px; padding: 12px 16px;">
                            <b style="color: var(--thanh-cong);">⚖️ Lý do phán quyết của Admin:</b>
                            <p style="margin: 4px 0 0 0; color: var(--chu); font-size: 0.92rem;">
                                <?php echo nl2br(htmlspecialchars($d['admin_note'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>

                    <!-- FORM PHÂN XỬ DÀNH CHO ADMIN (NẾU ĐƠN CHƯA PHÂN XỬ) -->
                    <?php if ($d['order_status'] === 'tranh_chap'): ?>
                        <div style="margin-top: 18px; padding-top: 16px; border-top: 1px dashed var(--vien);">
                            <form method="POST" action="">
                                <input type="hidden" name="dispute_id" value="<?php echo $d['id']; ?>">
                                
                                <div class="form-group" style="margin-bottom: 12px;">
                                    <label><b>Lý do phân xử của Admin * (Bắt buộc ghi rõ lý do để gửi cho hai bên):</b></label>
                                    <textarea name="admin_note" class="form-control" rows="2" required placeholder="Nhập căn cứ phân xử, ví dụ: Ảnh chụp rõ đồ ăn mốc hỏng, hoặc Bằng chứng sinh viên không đủ căn cứ..."></textarea>
                                </div>

                                <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                                    <!-- HƯỚNG A -->
                                    <button type="submit" name="decision" value="approve_student" class="btn btn-success" onclick="return confirm('XÁC NHẬN HƯỚNG A: Hoàn 100% tiền cho sinh viên và Ghi nhận 1 lần vi phạm cho quán?');">
                                        ✅ Hướng A: Hoàn 100% cho SV (+1 Vi phạm quán)
                                    </button>

                                    <!-- HƯỚNG B -->
                                    <button type="submit" name="decision" value="reject_student" class="btn btn-danger" onclick="return confirm('XÁC NHẬN HƯỚNG B: Bác khiếu nại, Chuyển tiền cho quán và Cảnh cáo sinh viên (+1 Cảnh cáo)?');">
                                        ❌ Hướng B: Bác khiếu nại (Chuyển tiền quán + Cảnh cáo SV)
                                    </button>
                                </div>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</div>

<style>
@media (max-width: 768px) {
    .dispute-grid {
        grid-template-columns: 1fr !important;
    }
}
</style>

<?php require_once __DIR__ . '/../footer.php'; ?>
