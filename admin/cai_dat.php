<?php
/**
 * TRANG CÀI ĐẶT THAM SỐ HỆ THỐNG (admin/cai_dat.php)
 * Cho phép Quản trị viên thay đổi các biến số nghiệp vụ
 * Hỗ trợ chỉnh thời gian giữ tiền / hủy đơn xuống 1-2 phút để test demo nhanh
 */

$page_title = "Cài đặt hệ thống";
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

yeu_cau_admin();
$admin = require_role($conn, ['admin']);

$msg = '';

// Danh sách các cấu hình và mô tả tiếng Việt
$setting_meta = [
    'discount_min' => [
        'title' => 'Tỷ lệ giảm giá tối thiểu (%)',
        'desc' => 'Mức giảm tối thiểu bắt buộc khi quán tạo túi bất ngờ (mặc định 30%).',
        'type' => 'number'
    ],
    'discount_max' => [
        'title' => 'Tỷ lệ giảm giá tối đa (%)',
        'desc' => 'Mức giảm tối đa cho phép khi quán tạo túi bất ngờ (mặc định 50%).',
        'type' => 'number'
    ],
    'cancel_before_close_minutes' => [
        'title' => 'Thời hạn hủy đơn trước giờ đóng cửa (Phút)',
        'desc' => 'Sinh viên được hủy đơn hoàn tiền nếu cách giờ đóng cửa ít nhất số phút này (mặc định 90 phút). Gợi ý test nhanh: chỉnh xuống 2 phút.',
        'type' => 'number'
    ],
    'hold_minutes' => [
        'title' => 'Thời gian giữ tiền bảo vệ sinh viên (Phút)',
        'desc' => 'Thời gian tiền bị giữ sau khi sinh viên nhận túi trước khi tự động cộng cho quán (mặc định 45 phút). Gợi ý test nhanh: chỉnh xuống 1-2 phút.',
        'type' => 'number'
    ],
    'dispute_response_hours' => [
        'title' => 'Thời gian quán phản đối khiếu nại (Giờ)',
        'desc' => 'Thời gian cho phép chủ quán gửi văn bản phản hồi và ảnh đối chứng (mặc định 48 giờ).',
        'type' => 'number'
    ],
    'max_bags_per_user' => [
        'title' => 'Giới hạn túi đặt đồng thời mỗi sinh viên',
        'desc' => 'Số túi tối đa mà một sinh viên được giữ ở trạng thái chờ nhận / đã nhận cùng lúc (mặc định 2 túi).',
        'type' => 'number'
    ],
    'max_disputes_per_month' => [
        'title' => 'Giới hạn khiếu nại tối đa mỗi tháng của sinh viên',
        'desc' => 'Số lần tối đa sinh viên được báo cáo tranh chấp trong 1 tháng (mặc định 3 lần).',
        'type' => 'number'
    ],
    'commission_percent' => [
        'title' => 'Phí hoa hồng nền tảng / Lợi nhuận Admin (%)',
        'desc' => 'Tỷ lệ phần trăm doanh thu Admin nhận được trên mỗi túi bán thành công (mặc định 10%). Số tiền này tự động chuyển vào ví Admin khi đơn hoàn tất.',
        'type' => 'number'
    ],
];

// Xử lý cập nhật
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        foreach ($setting_meta as $key => $meta) {
            if (isset($_POST[$key])) {
                $val = trim($_POST[$key]);
                $stmt_up = mysqli_prepare($conn, "UPDATE settings SET value = ? WHERE name = ?");
                mysqli_stmt_bind_param($stmt_up, "ss", $val, $key);
                mysqli_stmt_execute($stmt_up);
            }
        }
        $msg = "<div class='alert alert-success'>✅ Đã cập nhật toàn bộ cấu hình hệ thống thành công!</div>";
    } elseif (isset($_POST['reset_default'])) {
        // Đặt lại mặc định
        $defaults = [
            'discount_min' => '30',
            'discount_max' => '50',
            'cancel_before_close_minutes' => '90',
            'hold_minutes' => '45',
            'dispute_response_hours' => '48',
            'max_bags_per_user' => '2',
            'max_disputes_per_month' => '3',
            'commission_percent' => '10'
        ];
        foreach ($defaults as $k => $v) {
            $stmt_rst = mysqli_prepare($conn, "UPDATE settings SET value = ? WHERE name = ?");
            mysqli_stmt_bind_param($stmt_rst, "ss", $v, $k);
            mysqli_stmt_execute($stmt_rst);
        }
        $msg = "<div class='alert alert-info'>🔄 Đã khôi phục các giá trị cấu hình về mặc định ban đầu!</div>";
    }
}

// Đọc lại tất cả cài đặt hiện tại
$current_settings = [];
$res_set = mysqli_query($conn, "SELECT name, value FROM settings");
while ($row = mysqli_fetch_assoc($res_set)) {
    $current_settings[$row['name']] = $row['value'];
}

require_once __DIR__ . '/../header.php';
?>

<div class="content-box" style="max-width: 800px; margin: 20px auto;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="color: var(--primary); margin: 0 0 4px 0;">⚙️ Cấu Hình Hệ Thống</h2>
            <p style="color: var(--text-muted); margin: 0;">Điều chỉnh tỷ lệ giảm giá, thời gian giữ tiền và hạn ngạch khiếu nại</p>
        </div>
        
        <form method="POST" action="" style="margin: 0;">
            <button type="submit" name="reset_default" class="btn btn-sm btn-outline" onclick="return confirm('Khôi phục toàn bộ cài đặt về mặc định?');">
                🔄 Đặt lại mặc định
            </button>
        </form>
    </div>

    <?php echo $msg; ?>

    <div style="background: var(--cam-mo); border-left: 4px solid var(--cam); border: 1px solid var(--cam); border-left-width: 4px; padding: 14px 18px; border-radius: 4px; margin-bottom: 24px; font-size: 0.88rem; color: var(--chu);">
        <b style="color: var(--cam-sang);">💡 MẸO DEMO BÁO CÁO THUYẾT TRÌNH:</b><br>
        Để thầy cô và người xem không phải chờ 45 phút, bạn hãy chỉnh <b>"Thời gian giữ tiền"</b> xuống <b>1 hoặc 2 phút</b>. Sau khi chủ quán nhập mã nhận hàng, chỉ cần chờ 1 phút và F5 lại trang thì đơn sẽ tự động hoàn thành và cộng tiền vào ví của quán!
    </div>

    <form method="POST" action="">
        <div style="display: flex; flex-direction: column; gap: 16px;">
            <?php foreach ($setting_meta as $key => $meta): 
                $val = $current_settings[$key] ?? '';
            ?>
                <div style="background: var(--nen-2); border: 1px solid var(--vien); border-radius: var(--radius-sm); padding: 16px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; flex-wrap: wrap; gap: 8px;">
                        <label style="font-weight: 700; color: var(--text-main); font-size: 0.95rem;">
                            <?php echo htmlspecialchars($meta['title']); ?>
                        </label>
                        <code style="background: var(--nen-3); padding: 2px 6px; border-radius: 4px; font-size: 0.8rem; color: var(--cam-sang); border: 1px solid var(--vien);">
                            <?php echo htmlspecialchars($key); ?>
                        </code>
                    </div>
                    <p style="font-size: 0.82rem; color: var(--chu-phu); margin-bottom: 10px;">
                        <?php echo htmlspecialchars($meta['desc']); ?>
                    </p>
                    <input type="<?php echo $meta['type']; ?>" name="<?php echo htmlspecialchars($key); ?>" class="form-control" style="max-width: 250px;" required value="<?php echo htmlspecialchars($val); ?>">
                </div>
            <?php endforeach; ?>
        </div>

        <div style="margin-top: 24px;">
            <button type="submit" name="update_settings" class="btn btn-primary" style="padding: 12px 28px; font-size: 1rem;">
                💾 Lưu Thay Đổi Cài Đặt
            </button>
        </div>
    </form>
</div>

<?php require_once __DIR__ . '/../footer.php'; ?>
