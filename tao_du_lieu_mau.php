<?php
/**
 * TẬP TIN KHỞI TẠO DỮ LIỆU MẪU DEMO (tao_du_lieu_mau.php)
 * Sinh tự động:
 * - 1 Quản trị viên (admin@giaicuu.vn)
 * - 3 Sinh viên có sẵn tiền ảo trong ví (sinhvien1, sinhvien2, sinhvien3)
 * - 5 Quán ăn thật phong phú tại các quận Đà Nẵng (Hải Châu, Thanh Khê, Sơn Trà, Liên Chiểu, Ngũ Hành Sơn)
 * - Nhiều Túi bất ngờ mẫu (Đang mở bán, Sắp mở bán, Giảm giá 30%-50%)
 * - Đảm bảo nguyên tắc bảo toàn tiền ảo: Tổng balance = Tổng tiền nạp demo trong wallet_logs
 */

$page_title = "Khởi tạo dữ liệu mẫu demo";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$msg = '';

if (isset($_POST['seed_data'])) {
    // Tắt kiểm tra khóa ngoại để làm sạch dữ liệu cũ
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    mysqli_query($conn, "TRUNCATE TABLE notifications");
    mysqli_query($conn, "TRUNCATE TABLE wallet_logs");
    mysqli_query($conn, "TRUNCATE TABLE disputes");
    mysqli_query($conn, "TRUNCATE TABLE orders");
    mysqli_query($conn, "TRUNCATE TABLE bags");
    mysqli_query($conn, "TRUNCATE TABLE shops");
    mysqli_query($conn, "TRUNCATE TABLE users");
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

    // Khởi tạo các cấu hình mặc định nếu chưa có
    $settings = [
        'discount_min' => '30',
        'discount_max' => '50',
        'cancel_before_close_minutes' => '90',
        'hold_minutes' => '45',
        'dispute_response_hours' => '48',
        'max_bags_per_user' => '2',
        'max_disputes_per_month' => '3',
        'commission_percent' => '10'
    ];
    foreach ($settings as $k => $v) {
        $st = mysqli_prepare($conn, "INSERT INTO settings (name, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = ?");
        mysqli_stmt_bind_param($st, "sss", $k, $v, $v);
        mysqli_stmt_execute($st);
    }

    // 1. Tạo Quản trị viên
    $admin_pass = password_hash('admin123', PASSWORD_DEFAULT);
    $st_adm = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, balance, warning_count, is_locked, created_at) VALUES (?, ?, ?, 'admin', 0.00, 0, 0, NOW())");
    $adm_name = "Ban Quản Trị Hệ Thống";
    $adm_email = "admin@giaicuu.vn";
    mysqli_stmt_bind_param($st_adm, "sss", $adm_name, $adm_email, $admin_pass);
    mysqli_stmt_execute($st_adm);

    // 2. Tạo 3 Sinh viên với số dư ban đầu được nạp bằng wallet_logs
    $sv_pass = password_hash('sv123456', PASSWORD_DEFAULT);
    $students = [
        [
            'name' => 'Nguyễn Văn Nam (ĐH Bách Khoa ĐN)',
            'email' => 'sinhvien1@gmail.com',
            'phone' => '0905123456',
            'balance' => 200000.00
        ],
        [
            'name' => 'Trần Thị Mai (ĐH Kinh Tế ĐN)',
            'email' => 'sinhvien2@gmail.com',
            'phone' => '0914234567',
            'balance' => 150000.00
        ],
        [
            'name' => 'Lê Quốc Bảo (ĐH Sư Phạm ĐN)',
            'email' => 'sinhvien3@gmail.com',
            'phone' => '0988345678',
            'balance' => 300000.00
        ]
    ];

    foreach ($students as $sv) {
        $st_sv = mysqli_prepare($conn, "INSERT INTO users (name, email, phone, password, role, balance, warning_count, is_locked, created_at) VALUES (?, ?, ?, ?, 'student', ?, 0, 0, NOW())");
        mysqli_stmt_bind_param($st_sv, "ssssd", $sv['name'], $sv['email'], $sv['phone'], $sv_pass, $sv['balance']);
        mysqli_stmt_execute($st_sv);
        $sv_id = mysqli_insert_id($conn);

        // Ghi log nạp tiền demo tương ứng để bảo toàn dòng tiền
        $reason = "Nạp tiền demo vào ví";
        $st_wl = mysqli_prepare($conn, "INSERT INTO wallet_logs (user_id, amount, reason, order_id, created_at) VALUES (?, ?, ?, NULL, NOW())");
        mysqli_stmt_bind_param($st_wl, "ids", $sv_id, $sv['balance'], $reason);
        mysqli_stmt_execute($st_wl);
    }

    // 3. Tạo 5 Chủ quán & Quán ăn tại các quận Đà Nẵng
    $shop_pass = password_hash('shop123', PASSWORD_DEFAULT);
    $shops_data = [
        [
            'owner_name' => 'Anh Tuấn (Chủ Tiệm Bánh Mì)',
            'owner_email' => 'quan_banhmi@gmail.com',
            'shop_name' => 'Bánh Mì & Xôi Đệ Nhất Đà Nẵng',
            'address' => '86 Lê Duẩn, P. Thạch Thang',
            'phone' => '0905111222',
            'district' => 'Hải Châu',
            'type' => 'Bánh mì & Xôi',
            'status' => 'da_duyet',
            'bags' => [
                [
                    'name' => 'Túi Bất Ngờ Bánh Mì & Bánh Bao Tối',
                    'desc' => 'Túi gồm 2-3 phần bánh mì kẹp thịt xá xíu, pate giòn rụm và bánh bao nóng sốt trong ngày.',
                    'orig' => 70000,
                    'web' => 39000, // giảm 44.3%
                    'stock' => 5,
                    'open_offset' => -60, // Mở bán cách đây 60p
                    'start_offset' => 30, // Nhận sau 30p
                    'end_offset' => 90,   // Nhận đến 90p
                    'close_offset' => 120 // Đóng cửa sau 120p
                ]
            ]
        ],
        [
            'owner_name' => 'Cô Hương (Cơm Niêu Phố Cổ)',
            'owner_email' => 'quan_com@gmail.com',
            'shop_name' => 'Cơm Niêu & Món Mặn Phố Cổ Đà Nẵng',
            'address' => '142 Nguyễn Văn Linh, P. Vĩnh Trung',
            'phone' => '0905333444',
            'district' => 'Thanh Khê',
            'type' => 'Cơm / Món mặn',
            'status' => 'da_duyet',
            'bags' => [
                [
                    'name' => 'Túi Cứu Trợ Cơm & Món Mặn Gia Đình',
                    'desc' => 'Túi ngẫu nhiên gồm 1 phần cơm lớn kèm 2 món mặn (cá kho/thịt rang) và canh nóng chuẩn vị miền Trung.',
                    'orig' => 90000,
                    'web' => 49000, // giảm 45.6%
                    'stock' => 4,
                    'open_offset' => -30,
                    'start_offset' => 45,
                    'end_offset' => 105,
                    'close_offset' => 150
                ]
            ]
        ],
        [
            'owner_name' => 'Chị Thảo (Tiệm Bánh French Bakery)',
            'owner_email' => 'quan_banhngot@gmail.com',
            'shop_name' => 'French Bakery & Pastry Đà Nẵng',
            'address' => '25 Võ Văn Kiệt, P. Phước Mỹ',
            'phone' => '0905555666',
            'district' => 'Sơn Trà',
            'type' => 'Bánh ngọt & Tráng miệng',
            'status' => 'da_duyet',
            'bags' => [
                [
                    'name' => 'Túi Ngẫu Nhiên Bánh Croissant & Mousse Ngọt',
                    'desc' => 'Túi ngẫu nhiên 3 phần bánh nướng cao cấp kiểu Pháp nướng mới trong ngày, thơm bơ sữa tự nhiên.',
                    'orig' => 85000,
                    'web' => 45000, // giảm 47%
                    'stock' => 6,
                    'open_offset' => -10,
                    'start_offset' => 20,
                    'end_offset' => 80,
                    'close_offset' => 120
                ]
            ]
        ],
        [
            'owner_name' => 'Em Hoàng (Trà Sữa Sinh Viên)',
            'owner_email' => 'quan_trasua@gmail.com',
            'shop_name' => 'Trà Sữa & Topping Tươi Nhà Làm',
            'address' => '54 Ngô Thì Nhậm, P. Hòa Khánh Nam',
            'phone' => '0905777888',
            'district' => 'Liên Chiểu',
            'type' => 'Trà sữa & Đồ uống',
            'status' => 'da_duyet',
            'bags' => [
                [
                    'name' => 'Combo 2 Ly Trà Sữa Đậm Vị Kèm Topping (Sắp Mở)',
                    'desc' => 'Túi gồm 2 ly trà sữa truyền thống / oolong nướng đậm đà, full topping thạch và trân châu tươi nấu trong ngày.',
                    'orig' => 60000,
                    'web' => 32000, // giảm 46.7%
                    'stock' => 8,
                    'open_offset' => 25, // Sắp mở sau 25 phút!
                    'start_offset' => 60,
                    'end_offset' => 120,
                    'close_offset' => 180
                ]
            ]
        ],
        [
            'owner_name' => 'Bác Thiện (Quán Chay An Lạc)',
            'owner_email' => 'quan_chay@gmail.com',
            'shop_name' => 'Ẩm Thực Chay An Lạc Đà Nẵng',
            'address' => '12 Lê Văn Hiến, P. Khuê Mỹ',
            'phone' => '0905999000',
            'district' => 'Ngũ Hành Sơn',
            'type' => 'Món chay',
            'status' => 'cho_duyet', // Quán đang chờ Admin phê duyệt demo
            'bags' => [
                [
                    'name' => 'Túi Cơm Chay Dưỡng Sinh & Nem Nấm Rán',
                    'desc' => 'Túi thức ăn chay thanh đạm đầy đặn rau nấm tươi mát cuối ngày.',
                    'orig' => 50000,
                    'web' => 28000, // giảm 44%
                    'stock' => 3,
                    'open_offset' => -15,
                    'start_offset' => 30,
                    'end_offset' => 90,
                    'close_offset' => 120
                ]
            ]
        ]
    ];

    foreach ($shops_data as $sh) {
        // Tạo tài khoản chủ quán
        $st_o = mysqli_prepare($conn, "INSERT INTO users (name, email, password, role, balance, warning_count, is_locked, created_at) VALUES (?, ?, ?, 'shop_owner', 0.00, 0, 0, NOW())");
        mysqli_stmt_bind_param($st_o, "sss", $sh['owner_name'], $sh['owner_email'], $shop_pass);
        mysqli_stmt_execute($st_o);
        $owner_id = mysqli_insert_id($conn);

        // Tạo quán kèm số điện thoại
        $st_s = mysqli_prepare($conn, "INSERT INTO shops (owner_id, name, address, phone, district, type, image, violation_count, status, created_at) VALUES (?, ?, ?, ?, ?, ?, NULL, 0, ?, NOW())");
        mysqli_stmt_bind_param($st_s, "issssss", $owner_id, $sh['shop_name'], $sh['address'], $sh['phone'], $sh['district'], $sh['type'], $sh['status']);
        mysqli_stmt_execute($st_s);
        $shop_id = mysqli_insert_id($conn);

        // Tạo túi của quán
        foreach ($sh['bags'] as $bg) {
            $t_open = date('Y-m-d H:i:s', strtotime("{$bg['open_offset']} minutes"));
            $t_start = date('Y-m-d H:i:s', strtotime("{$bg['start_offset']} minutes"));
            $t_end = date('Y-m-d H:i:s', strtotime("{$bg['end_offset']} minutes"));
            $t_close = date('Y-m-d H:i:s', strtotime("{$bg['close_offset']} minutes"));

            $st_b = mysqli_prepare($conn, "INSERT INTO bags (shop_id, name, description, image, original_price, web_price, stock, open_at, pickup_start, pickup_end, close_at, created_at) VALUES (?, ?, ?, NULL, ?, ?, ?, ?, ?, ?, ?, NOW())");
            mysqli_stmt_bind_param($st_b, "issddissss", $shop_id, $bg['name'], $bg['desc'], $bg['orig'], $bg['web'], $bg['stock'], $t_open, $t_start, $t_end, $t_close);
            mysqli_stmt_execute($st_b);
        }
    }

    $msg = "<div class='alert alert-success' style='font-size: 1.05rem;'>
        🎉 <b>Đã khởi tạo thành công toàn bộ dữ liệu mẫu demo!</b><br>
        Xem bảng tài khoản bên dưới để đăng nhập thử nghiệm ngay lập tức.
    </div>";
}

require_once __DIR__ . '/header.php';
?>

<div class="content-box" style="max-width: 900px; margin: 20px auto;">
    <div style="text-align: center; margin-bottom: 24px;">
        <div style="font-size: 3rem; margin-bottom: 6px;">🌱</div>
        <h2 style="color: var(--primary); margin: 0 0 6px 0;">Khởi Tạo Dữ Liệu Mẫu Thử Nghiệm (Demo)</h2>
        <p style="color: var(--text-muted); font-size: 0.95rem;">
            Tự động sinh các quán ăn tiêu biểu tại các quận Đà Nẵng, tài khoản sinh viên có sẵn tiền ảo và các túi bất ngờ
        </p>
    </div>

    <?php echo $msg; ?>

    <div style="text-align: center; margin: 20px 0 30px 0;">
        <form method="POST" action="" onsubmit="return confirm('Hành động này sẽ làm sạch và nạp mới toàn bộ dữ liệu demo chuẩn. Bạn có chắc chắn không?');">
            <button type="submit" name="seed_data" class="btn btn-primary" style="padding: 14px 32px; font-size: 1.1rem; font-weight: 800;">
                ⚡ Bấm Để Khởi Tạo Dữ Liệu Mẫu Ngay
            </button>
        </form>
    </div>

    <!-- DANH SÁCH TÀI KHOẢN DEMO SẴN CÓ ĐỂ CHẤM BÀI / THỬ NGHIỆM -->
    <h3 style="color: var(--text-main); margin-bottom: 12px;">🔑 Danh Sách Tài Khoản Thử Nghiệm Sẵn Có:</h3>

    <div class="table-responsive">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Vai trò</th>
                    <th>Email đăng nhập</th>
                    <th>Mật khẩu</th>
                    <th>Họ tên & Ghi chú</th>
                    <th>Số dư sẵn có</th>
                </tr>
            </thead>
            <tbody>
                <!-- ADMIN -->
                <tr style="background: rgba(179, 136, 255, 0.1);">
                    <td><span class="role-tag" style="background: var(--tim); color: var(--nen); font-weight: bold;">admin</span></td>
                    <td><b>admin@giaicuu.vn</b></td>
                    <td><code>admin123</code></td>
                    <td>Ban Quản Trị Hệ Thống (Phân xử tranh chấp, duyệt quán, xem thống kê)</td>
                    <td>0 đ</td>
                </tr>

                <!-- SINH VIÊN 1 -->
                <tr>
                    <td><span class="role-tag" style="background: var(--thanh-cong); color: var(--nen); font-weight: bold;">student</span></td>
                    <td><b>sinhvien1@gmail.com</b></td>
                    <td><code>sv123456</code></td>
                    <td>Nguyễn Văn Nam (ĐH Bách Khoa ĐN)</td>
                    <td><b style="color: var(--cam-sang); font-family: var(--font-game);">200.000 đ</b></td>
                </tr>

                <!-- SINH VIÊN 2 -->
                <tr>
                    <td><span class="role-tag" style="background: var(--thanh-cong); color: var(--nen); font-weight: bold;">student</span></td>
                    <td><b>sinhvien2@gmail.com</b></td>
                    <td><code>sv123456</code></td>
                    <td>Trần Thị Mai (ĐH Kinh Tế ĐN)</td>
                    <td><b style="color: var(--cam-sang); font-family: var(--font-game);">150.000 đ</b></td>
                </tr>

                <!-- SINH VIÊN 3 -->
                <tr>
                    <td><span class="role-tag" style="background: var(--thanh-cong); color: var(--nen); font-weight: bold;">student</span></td>
                    <td><b>sinhvien3@gmail.com</b></td>
                    <td><code>sv123456</code></td>
                    <td>Lê Quốc Bảo (ĐH Sư Phạm ĐN)</td>
                    <td><b style="color: var(--cam-sang); font-family: var(--font-game);">300.000 đ</b></td>
                </tr>

                <!-- CHỦ QUÁN 1 -->
                <tr style="background: var(--cam-mo);">
                    <td><span class="role-tag" style="background: var(--cam); color: var(--nen); font-weight: bold;">shop_owner</span></td>
                    <td><b>quan_banhmi@gmail.com</b></td>
                    <td><code>shop123</code></td>
                    <td>Bánh Mì & Xôi Đệ Nhất (Q. Hải Châu - Đã duyệt)</td>
                    <td>0 đ</td>
                </tr>

                <!-- CHỦ QUÁN 2 -->
                <tr style="background: var(--cam-mo);">
                    <td><span class="role-tag" style="background: var(--cam); color: var(--nen); font-weight: bold;">shop_owner</span></td>
                    <td><b>quan_com@gmail.com</b></td>
                    <td><code>shop123</code></td>
                    <td>Cơm Niêu Phố Cổ (Q. Thanh Khê - Đã duyệt)</td>
                    <td>0 đ</td>
                </tr>

                <!-- CHỦ QUÁN 3 -->
                <tr style="background: var(--cam-mo);">
                    <td><span class="role-tag" style="background: var(--cam); color: var(--nen); font-weight: bold;">shop_owner</span></td>
                    <td><b>quan_banhngot@gmail.com</b></td>
                    <td><code>shop123</code></td>
                    <td>French Bakery & Pastry (Q. Sơn Trà - Đã duyệt)</td>
                    <td>0 đ</td>
                </tr>

                <!-- CHỦ QUÁN 4 -->
                <tr style="background: var(--cam-mo);">
                    <td><span class="role-tag" style="background: var(--cam); color: var(--nen); font-weight: bold;">shop_owner</span></td>
                    <td><b>quan_trasua@gmail.com</b></td>
                    <td><code>shop123</code></td>
                    <td>Trà Sữa Nhà Làm (Q. Liên Chiểu - Túi sắp mở bán)</td>
                    <td>0 đ</td>
                </tr>

                <!-- CHỦ QUÁN 5 -->
                <tr style="background: var(--cam-mo);">
                    <td><span class="role-tag" style="background: var(--cam); color: var(--nen); font-weight: bold;">shop_owner</span></td>
                    <td><b>quan_chay@gmail.com</b></td>
                    <td><code>shop123</code></td>
                    <td>Ẩm Thực Chay An Lạc (Q. Ngũ Hành Sơn - <i>Chờ Admin duyệt</i>)</td>
                    <td>0 đ</td>
                </tr>
            </tbody>
        </table>
    </div>

    <div style="margin-top: 24px; display: flex; gap: 12px; justify-content: center;">
        <a href="<?php echo BASE_URL; ?>/dang_nhap.php" class="btn btn-primary" style="padding: 10px 24px;">🔑 Đi Đến Trang Đăng Nhập</a>
        <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline" style="padding: 10px 24px;">🏠 Xem Trang Chủ Túi Bất Ngờ</a>
    </div>
</div>

<?php require_once __DIR__ . '/footer.php'; ?>
