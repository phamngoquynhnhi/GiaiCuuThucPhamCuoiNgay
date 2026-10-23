<?php
/**
 * ====================================================================
 * TẬP TIN HÀM DÙNG CHUNG (functions.php)
 * Dành cho sinh viên năm 2 - Đơn giản, dễ hiểu, bảo mật cơ bản
 * ====================================================================
 */

// Đảm bảo đã có kết nối database
require_once __DIR__ . '/config.php';

/**
 * 1. Hàm đọc giá trị cấu hình từ bảng settings
 */
function get_setting($conn, $name, $default = '') {
    $stmt = mysqli_prepare($conn, "SELECT value FROM settings WHERE name = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "s", $name);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        return $row['value'];
    }
    return $default;
}

// Tự động kiểm tra và thêm cột phone vào bảng users nếu chưa có
@mysqli_query($conn, "ALTER TABLE users ADD COLUMN phone VARCHAR(20) NULL AFTER email");

/**
 * 2. Hàm lấy thông tin mới nhất của người dùng đang đăng nhập
 */
function get_current_user_data($conn) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    $user_id = $_SESSION['user_id'];
    $stmt = mysqli_prepare($conn, "SELECT id, name, email, phone, role, balance, warning_count, is_locked FROM users WHERE id = ? LIMIT 1");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    return mysqli_fetch_assoc($result);
}

/**
 * Yêu cầu đăng nhập: chưa đăng nhập thì chuyển về dang_nhap.php
 */
function yeu_cau_dang_nhap() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/dang_nhap.php");
        exit;
    }
}

/**
 * Yêu cầu quyền admin:
 * - Chưa đăng nhập -> chuyển về dang_nhap.php
 * - Không phải admin -> báo "Bạn không có quyền truy cập"
 */
function yeu_cau_admin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/dang_nhap.php");
        exit;
    }
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        die("<div style='font-family: \"Be Vietnam Pro\", sans-serif; max-width: 500px; margin: 60px auto; padding: 28px; background: #10272F; border: 2px solid #FF5A5F; border-radius: 16px; text-align: center; color: #EAF2F4; box-shadow: 0 4px 0 rgba(0,0,0,0.45);'>
            <h2 style='font-family: \"Baloo 2\", cursive; color: #FF5A5F; margin-top: 0;'>⚠️ Bạn không có quyền truy cập</h2>
            <p style='color: #9FB5BC;'>Trang này chỉ dành riêng cho Quản trị viên (admin).</p>
            <p><a href='" . BASE_URL . "/index.php' style='display:inline-block; margin-top:12px; padding:10px 22px; background:#FF7A1A; color:#0B1B21; font-weight:800; text-decoration:none; border-radius:10px; box-shadow: 0 4px 0 #E85D04;'>Quay về Trang chủ</a></p>
        </div>");
    }
}

/**
 * 3. Hàm kiểm tra quyền đăng nhập và vai trò (Role Check)
 * Nếu chưa đăng nhập hoặc sai vai trò -> Chuyển hướng
 */
function require_role($conn, $allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: " . BASE_URL . "/dang_nhap.php");
        exit;
    }

    $user = get_current_user_data($conn);
    if (!$user) {
        // Tài khoản không tồn tại trong database
        session_destroy();
        header("Location: " . BASE_URL . "/dang_nhap.php");
        exit;
    }

    // Kiểm tra tài khoản có bị khóa không
    if ($user['is_locked'] == 1) {
        die("<div style='font-family: \"Be Vietnam Pro\", sans-serif; max-width: 500px; margin: 60px auto; padding: 28px; background: #10272F; border: 2px solid #FF5A5F; border-radius: 16px; text-align: center; color: #EAF2F4; box-shadow: 0 4px 0 rgba(0,0,0,0.45);'>
            <h2 style='font-family: \"Baloo 2\", cursive; color: #FF5A5F; margin-top: 0;'>🚫 Tài khoản đã bị khóa</h2>
            <p style='color: #9FB5BC;'>Tài khoản của bạn đã bị khóa do nhận quá nhiều cảnh cáo vi phạm quy định cứu thực phẩm.</p>
            <p><a href='" . BASE_URL . "/dang_xuat.php' style='display:inline-block; margin-top:12px; padding:10px 22px; background:#FF5A5F; color:#0B1B21; font-weight:800; text-decoration:none; border-radius:10px; box-shadow: 0 4px 0 #b91c1c;'>Đăng xuất</a></p>
        </div>");
    }

    // Chuẩn hóa kiểm tra vai trò: 'user' và 'student' coi như tương đương
    $matched = in_array($user['role'], $allowed_roles);
    if (!$matched && in_array('student', $allowed_roles) && $user['role'] === 'user') {
        $matched = true;
    }
    if (!$matched && in_array('user', $allowed_roles) && $user['role'] === 'student') {
        $matched = true;
    }

    // Nếu truyền danh sách vai trò cho phép, kiểm tra xem có khớp không
    if (!empty($allowed_roles) && !$matched) {
        die("<div style='font-family: \"Be Vietnam Pro\", sans-serif; max-width: 500px; margin: 60px auto; padding: 28px; background: #10272F; border: 2px solid #FFC53D; border-radius: 16px; text-align: center; color: #EAF2F4; box-shadow: 0 4px 0 rgba(0,0,0,0.45);'>
            <h2 style='font-family: \"Baloo 2\", cursive; color: #FFC53D; margin-top: 0;'>⚠️ Không có quyền truy cập</h2>
            <p style='color: #9FB5BC;'>Bạn không có quyền truy cập trang này.</p>
            <p><a href='" . BASE_URL . "/index.php' style='display:inline-block; margin-top:12px; padding:10px 22px; background:#FF7A1A; color:#0B1B21; font-weight:800; text-decoration:none; border-radius:10px; box-shadow: 0 4px 0 #E85D04;'>Quay về Trang chủ</a></p>
        </div>");
    }

    return $user;
}

/**
 * 4. Hàm định dạng tiền tệ Việt Nam (VNĐ)
 */
function format_money($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' đ';
}

/**
 * 5. Hàm gửi thông báo hệ thống cho người dùng
 */
function add_notification($conn, $user_id, $message) {
    $stmt = mysqli_prepare($conn, "INSERT INTO notifications (user_id, message, is_read, created_at) VALUES (?, ?, 0, NOW())");
    mysqli_stmt_bind_param($stmt, "is", $user_id, $message);
    return mysqli_stmt_execute($stmt);
}

/**
 * 6. Hàm đếm số thông báo chưa đọc của người dùng
 */
function get_unread_notifications_count($conn, $user_id) {
    $stmt = mysqli_prepare($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE user_id = ? AND is_read = 0");
    mysqli_stmt_bind_param($stmt, "i", $user_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    if ($row = mysqli_fetch_assoc($result)) {
        return (int)$row['cnt'];
    }
    return 0;
}

/**
 * 7. Hàm sinh mã nhận hàng ngẫu nhiên 6 ký tự không trùng lặp
 */
function generate_pickup_code($conn) {
    // Ký tự dễ nhìn, bỏ O, 0, I, 1 để tránh nhầm lẫn khi đọc
    $characters = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
    $max_attempts = 20;

    for ($i = 0; $i < $max_attempts; $i++) {
        $code = '';
        for ($j = 0; $j < 6; $j++) {
            $code .= $characters[random_int(0, strlen($characters) - 1)];
        }

        // Kiểm tra xem mã đã tồn tại chưa
        $stmt = mysqli_prepare($conn, "SELECT id FROM orders WHERE pickup_code = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $code);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if (mysqli_num_rows($res) == 0) {
            return $code; // Mã duy nhất hợp lệ
        }
    }

    // Dự phòng cực hiếm
    return strtoupper(substr(uniqid(), -6));
}

/**
 * 8. Hàm upload hình ảnh an toàn (chỉ jpg, jpeg, png, webp, tối đa 2MB)
 */
function upload_image($file, $sub_dir = '') {
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Chưa chọn file hoặc có lỗi khi tải lên!'];
    }

    // Kiểm tra dung lượng (tối đa 2MB = 2 * 1024 * 1024 bytes)
    if ($file['size'] > 2 * 1024 * 1024) {
        return ['success' => false, 'error' => 'Ảnh quá lớn! Dung lượng tối đa là 2MB.'];
    }

    // Kiểm tra đuôi file hợp lệ
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $file_info = pathinfo($file['name']);
    $extension = strtolower($file_info['extension'] ?? '');

    if (!in_array($extension, $allowed_extensions)) {
        return ['success' => false, 'error' => 'Chỉ chấp nhận file ảnh định dạng JPG, PNG, WEBP!'];
    }

    // Tạo tên file ngẫu nhiên tránh trùng lặp và an toàn bảo mật
    $new_filename = 'img_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
    $target_path = UPLOAD_DIR . $new_filename;

    if (!is_dir(UPLOAD_DIR)) {
        mkdir(UPLOAD_DIR, 0777, true);
    }

    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'filename' => 'uploads/' . $new_filename];
    }

    return ['success' => false, 'error' => 'Không thể lưu file lên máy chủ!'];
}

/**
 * 9. Hàm tính phần trăm giảm giá (%)
 */
function calculate_discount_percent($original_price, $web_price) {
    if ($original_price <= 0) return 0;
    $percent = round((($original_price - $web_price) / $original_price) * 100);
    return max(0, min(100, $percent));
}

/**
 * 10. Hàm hiển thị tên và nhãn màu trạng thái đơn hàng
 */
function get_order_status_info($status) {
    $map = [
        'cho_nhan' => [
            'name' => 'Chờ nhận hàng',
            'class' => 'status-badge status-warning',
            'desc' => 'Đang chờ sinh viên đến quán nhận túi'
        ],
        'da_nhan' => [
            'name' => 'Đã nhận (Đang giữ tiền)',
            'class' => 'status-badge status-info',
            'desc' => 'Sinh viên đã nhận hàng, tiền đang tạm giữ để bảo vệ quyền lợi'
        ],
        'hoan_thanh' => [
            'name' => 'Hoàn thành',
            'class' => 'status-badge status-success',
            'desc' => 'Giao dịch hoàn tất, tiền đã cộng cho quán'
        ],
        'tranh_chap' => [
            'name' => 'Đang tranh chấp',
            'class' => 'status-badge status-danger',
            'desc' => 'Sinh viên khiếu nại, tiền đang đóng băng chờ xử lý'
        ],
        'hoan_tien_sv' => [
            'name' => 'Hoàn tiền cho sinh viên',
            'class' => 'status-badge status-purple',
            'desc' => 'Khiếu nại được chấp thuận, 100% tiền đã hoàn về ví sinh viên'
        ],
        'chuyen_tien_quan' => [
            'name' => 'Chuyển tiền cho quán',
            'class' => 'status-badge status-brown',
            'desc' => 'Admin bác khiếu nại, tiền đã cộng cho chủ quán'
        ],
        'huy_boi_khach' => [
            'name' => 'Khách hủy đơn',
            'class' => 'status-badge status-secondary',
            'desc' => 'Sinh viên hủy sớm trước giờ quy định, đã hoàn tiền và hoàn kho'
        ],
        'huy_vang_mat' => [
            'name' => 'Hủy do vắng mặt',
            'class' => 'status-badge status-dark-red',
            'desc' => 'Quá giờ lấy hàng, tiền bồi thường cho quán, không hoàn kho'
        ]
    ];

    return $map[$status] ?? [
        'name' => $status,
        'class' => 'status-badge status-secondary',
        'desc' => ''
    ];
}

/**
 * 11. HÀM PHÂN CHIA DOANH THU ĐƠN HÀNG VÀ TRÍCH HOA HỒNG NỀN TẢNG ADMIN
 * - Tự động trích tỷ lệ hoa hồng nền tảng (mặc định 10% từ bảng settings).
 * - Ví Admin nhận 10% hoa hồng nền tảng (lợi nhuận sàn).
 * - Ví Chủ quán nhận 90% doanh thu thực nhận.
 * - Ghi nhận lịch sử wallet_logs cho cả Quán và Admin.
 * - Gửi thông báo notifications cho cả 2 bên.
 * Lưu ý: Hàm này được gọi BÊN TRONG Transaction đang chạy của đơn hàng.
 */
function complete_order_payment($conn, $order_id, $price, $shop_owner_id, $shop_name = '', $context = '') {
    $price = (float)$price;
    $order_id = (int)$order_id;
    $shop_owner_id = (int)$shop_owner_id;

    if ($price <= 0 || $shop_owner_id <= 0) {
        return false;
    }

    // 1. Đọc tỷ lệ phần trăm hoa hồng nền tảng (mặc định 10%)
    $commission_pct = (float)get_setting($conn, 'commission_percent', 10);
    $admin_fee = round(($price * $commission_pct) / 100);
    $shop_net = $price - $admin_fee;

    // 2. Tìm tài khoản Admin hệ thống để chuyển hoa hồng
    $res_adm = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' ORDER BY id ASC LIMIT 1");
    $admin_row = mysqli_fetch_assoc($res_adm);
    $admin_id = $admin_row ? (int)$admin_row['id'] : 0;

    // 3. Cộng tiền thực nhận (90%) vào ví Chủ quán
    $stmt_pay_shop = mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt_pay_shop, "di", $shop_net, $shop_owner_id);
    mysqli_stmt_execute($stmt_pay_shop);

    // 4. Ghi lịch sử ví của Chủ quán
    $reason_shop = "Cộng tiền đơn #" . $order_id . " (" . ($context ?: "Đơn hoàn thành") . " - Thực nhận sau trừ " . $commission_pct . "% phí sàn)";
    $stmt_log_shop = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, ?, NOW())");
    mysqli_stmt_bind_param($stmt_log_shop, "idsi", $shop_owner_id, $shop_net, $reason_shop, $order_id);
    mysqli_stmt_execute($stmt_log_shop);

    // 5. Nếu có hoa hồng và có tài khoản Admin: cộng tiền hoa hồng vào ví Admin
    if ($admin_fee > 0 && $admin_id > 0) {
        $stmt_pay_adm = mysqli_prepare($conn, "UPDATE users SET balance = balance + ? WHERE id = ?");
        mysqli_stmt_bind_param($stmt_pay_adm, "di", $admin_fee, $admin_id);
        mysqli_stmt_execute($stmt_pay_adm);

        $reason_adm = "Thu phí hoa hồng " . $commission_pct . "% từ đơn #" . $order_id . ($shop_name ? " (Quán: " . $shop_name . ")" : "");
        $stmt_log_adm = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, ?, NOW())");
        mysqli_stmt_bind_param($stmt_log_adm, "idsi", $admin_id, $admin_fee, $reason_adm, $order_id);
        mysqli_stmt_execute($stmt_log_adm);

        // Gửi thông báo cho Admin
        $msg_adm = "💰 Hệ thống vừa nhận " . format_money($admin_fee) . " hoa hồng (" . $commission_pct . "%) từ đơn hàng #" . $order_id . ".";
        add_notification($conn, $admin_id, $msg_adm);
    }

    // 6. Gửi thông báo cho Chủ quán
    $msg_shop = "Đơn hàng #" . $order_id . " đã hoàn tất! Bạn nhận được " . format_money($shop_net) . " vào ví (đã trừ " . $commission_pct . "% phí dịch vụ sàn " . format_money($admin_fee) . ").";
    add_notification($conn, $shop_owner_id, $msg_shop);

    return [
        'shop_net' => $shop_net,
        'admin_fee' => $admin_fee,
        'commission_pct' => $commission_pct
    ];
}

/**
 * 12. HÀM TỰ ĐỘNG KIỂM TRA VÀ CẬP NHẬT TRẠNG THÁI ĐƠN HÀNG (check_orders)
 * Không dùng Cron job: Hàm này được gọi tự động ở đầu các trang quan trọng.
 * - Đơn da_nhan hết thời gian hold_until -> hoan_thanh + chia tiền (90% quán, 10% hoa hồng admin).
 * - Đơn cho_nhan quá giờ close_at -> huy_vang_mat + chia tiền bồi thường, không hoàn kho.
 * Mỗi đơn xử lý trong một transaction độc lập kèm kiểm tra trạng thái cũ.
 */
function check_orders($conn) {
    // -------------------------------------------------------------
    // Bước A: Xử lý các đơn "da_nhan" đã hết thời gian tạm giữ tiền
    // -------------------------------------------------------------
    $sql_hold = "SELECT o.id, o.price, o.user_id, o.shop_id, s.owner_id, s.name as shop_name 
                 FROM orders o 
                 JOIN shops s ON o.shop_id = s.id 
                 WHERE o.status = 'da_nhan' AND o.hold_until IS NOT NULL AND o.hold_until <= NOW()";
    $res_hold = mysqli_query($conn, $sql_hold);

    if ($res_hold && mysqli_num_rows($res_hold) > 0) {
        while ($order = mysqli_fetch_assoc($res_hold)) {
            $order_id = (int)$order['id'];
            $price = (float)$order['price'];
            $owner_id = (int)$order['owner_id'];
            $shop_name = $order['shop_name'] ?? '';

            // Khởi tạo Transaction riêng cho đơn hàng này
            mysqli_begin_transaction($conn);

            // Cập nhật trạng thái kèm điều kiện trạng thái cũ
            $stmt_update = mysqli_prepare($conn, "UPDATE orders SET status = 'hoan_thanh' WHERE id = ? AND status = 'da_nhan'");
            mysqli_stmt_bind_param($stmt_update, "i", $order_id);
            mysqli_stmt_execute($stmt_update);

            if (mysqli_affected_rows($conn) > 0) {
                // Chia tiền cho Quán và trích 10% hoa hồng cho Admin
                complete_order_payment($conn, $order_id, $price, $owner_id, $shop_name, 'Hết thời gian giữ tiền, đơn hoàn thành');

                // Xác nhận giao dịch
                mysqli_commit($conn);
            } else {
                // Thất bại hoặc trạng thái đã bị đổi bởi thao tác khác
                mysqli_rollback($conn);
            }
        }
    }

    // -------------------------------------------------------------
    // Bước B: Xử lý các đơn "cho_nhan" quá giờ đóng cửa (khách vắng mặt)
    // -------------------------------------------------------------
    $sql_close = "SELECT o.id, o.price, o.user_id, o.shop_id, s.owner_id, s.name as shop_name 
                  FROM orders o 
                  JOIN shops s ON o.shop_id = s.id 
                  WHERE o.status = 'cho_nhan' AND o.close_at < NOW()";
    $res_close = mysqli_query($conn, $sql_close);

    if ($res_close && mysqli_num_rows($res_close) > 0) {
        while ($order = mysqli_fetch_assoc($res_close)) {
            $order_id = (int)$order['id'];
            $price = (float)$order['price'];
            $student_id = (int)$order['user_id'];
            $owner_id = (int)$order['owner_id'];
            $shop_name = $order['shop_name'] ?? '';

            // Khởi tạo Transaction riêng cho đơn hàng này
            mysqli_begin_transaction($conn);

            // Cập nhật trạng thái kèm điều kiện trạng thái cũ
            $stmt_update = mysqli_prepare($conn, "UPDATE orders SET status = 'huy_vang_mat' WHERE id = ? AND status = 'cho_nhan'");
            mysqli_stmt_bind_param($stmt_update, "i", $order_id);
            mysqli_stmt_execute($stmt_update);

            if (mysqli_affected_rows($conn) > 0) {
                // Chuyển tiền bồi thường cho chủ quán (sau trừ phí hoa hồng sàn)
                complete_order_payment($conn, $order_id, $price, $owner_id, $shop_name, 'Khách vắng mặt không đến nhận, bồi thường');

                // Không cộng lại kho!
                // Thông báo cho sinh viên
                $msg_student = "Đơn hàng #" . $order_id . " đã bị hủy do quá giờ nhận hàng. Tiền được bồi thường cho quán ăn.";
                add_notification($conn, $student_id, $msg_student);

                // Xác nhận giao dịch
                mysqli_commit($conn);
            } else {
                mysqli_rollback($conn);
            }
        }
    }
}

/**
 * Danh sách quận/huyện tại Đà Nẵng
 */
function get_danang_districts() {
    return [
        'Hải Châu',
        'Thanh Khê',
        'Sơn Trà',
        'Ngũ Hành Sơn',
        'Liên Chiểu',
        'Cẩm Lệ',
        'Hòa Vang'
    ];
}

/**
 * Danh sách loại hình ẩm thực
 */
function get_shop_types() {
    return [
        'Cơm / Món mặn',
        'Bánh mì & Xôi',
        'Bánh ngọt & Tráng miệng',
        'Trà sữa & Đồ uống',
        'Đồ ăn vặt',
        'Món chay'
    ];
}
