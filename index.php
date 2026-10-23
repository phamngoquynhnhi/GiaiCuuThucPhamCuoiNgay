<?php
/**
 * TRANG CHỦ (index.php)
 * Giao diện hiện đại tham khảo thiết kế EcoRescue ẩm thực xanh
 * Ngôn từ Marketing thu hút, kích thích vị giác và tò mò mô hình "Túi Mù"
 * Đầy đủ tính năng: Bộ lọc danh mục, tìm kiếm quận Đà Nẵng, danh sách Túi bất ngờ và Bản đồ điểm cứu trợ
 * Tuân thủ 100% PHP thuần, mysqli prepared statements, không dùng thư viện ngoài
 */

$page_title = "Trang chủ - Săn Túi Mù Ẩm Thực Giảm Đến 50%";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Đọc bộ lọc từ tham số $_GET
$filter_district = trim($_GET['district'] ?? '');
$filter_type = trim($_GET['type'] ?? '');
$filter_keyword = trim($_GET['keyword'] ?? '');

$districts = get_danang_districts();
$types = get_shop_types();

// Danh mục ẩm thực dạng thẻ Pills để lọc nhanh (từ ngữ hấp dẫn)
$category_pills = [
    '' => '🔥 Tất cả deal hot',
    'Cơm / Món mặn' => '🍛 Cơm nóng & Món mặn',
    'Bánh mì & Xôi' => '🥖 Bánh mì & Xôi giòn',
    'Bánh ngọt & Tráng miệng' => '🥐 Tiệm bánh & Pastry',
    'Trà sữa & Đồ uống' => '🧋 Trà sữa & Cà phê',
    'Món chay' => '🥗 Chay & Healthy',
    'Đồ ăn vặt' => '🍢 Ăn vặt đêm khuya'
];

// Xây dựng câu truy vấn SQL tìm kiếm túi
// Chỉ lấy túi từ quán ĐÃ DUYỆT và chưa quá giờ đóng cửa (close_at >= NOW())
$sql = "SELECT b.*, s.name as shop_name, s.address as shop_address, s.phone as shop_phone, s.district, s.type as shop_type, s.violation_count 
        FROM bags b 
        JOIN shops s ON b.shop_id = s.id 
        WHERE s.status = 'da_duyet' AND b.close_at >= NOW()";

$params = [];
$types_param = "";

if ($filter_district !== '') {
    $sql .= " AND s.district = ?";
    $params[] = $filter_district;
    $types_param .= "s";
}

if ($filter_type !== '') {
    $sql .= " AND s.type = ?";
    $params[] = $filter_type;
    $types_param .= "s";
}

if ($filter_keyword !== '') {
    $sql .= " AND (b.name LIKE ? OR s.name LIKE ?)";
    $kw = "%" . $filter_keyword . "%";
    $params[] = $kw;
    $params[] = $kw;
    $types_param .= "ss";
}

// Sắp xếp: Túi đang mở bán còn hàng lên đầu -> Túi sắp mở -> Túi hết hàng
$sql .= " ORDER BY 
            CASE 
                WHEN b.open_at <= NOW() AND b.stock > 0 THEN 0
                WHEN b.open_at > NOW() THEN 1
                ELSE 2
            END ASC,
            b.open_at ASC";

$stmt = mysqli_prepare($conn, $sql);
if (!empty($params)) {
    mysqli_stmt_bind_param($stmt, $types_param, ...$params);
}
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

// Lấy danh sách các quán ăn uy tín đã duyệt để hiển thị bên cạnh Bản Đồ
$sql_partner_shops = "SELECT s.*, COUNT(b.id) as active_bags 
                      FROM shops s 
                      LEFT JOIN bags b ON s.id = b.shop_id AND b.close_at >= NOW() AND b.stock > 0
                      WHERE s.status = 'da_duyet' ";
if ($filter_district !== '') {
    $sql_partner_shops .= " AND s.district = '" . mysqli_real_escape_string($conn, $filter_district) . "' ";
}
$sql_partner_shops .= " GROUP BY s.id ORDER BY active_bags DESC, s.name ASC LIMIT 5";
$res_partner_shops = mysqli_query($conn, $sql_partner_shops);

// Chuẩn bị vị trí tìm kiếm trên bản đồ (mặc định Đà Nẵng hoặc quận đã lọc)
$map_search_query = "Đà Nẵng, Việt Nam";
if ($filter_district !== '') {
    $map_search_query = $filter_district . ", Đà Nẵng, Việt Nam";
}

require_once __DIR__ . '/header.php';
?>

<!-- BANNER HERO SHOWCASE (Văn phong Marketing cuốn hút, kích thích vị giác & tò mò) -->
<section class="hero-card">
    <div class="hero-content">
        <span class="hero-badge">⚡ DEAL GIỜ CHÓT • TIẾT KIỆM 50% CHI PHÍ ĂN UỐNG MỖI NGÀY</span>
        <h1 class="hero-title">
            Mở "Túi Mù Ẩm Thực" Đêm Nay,<br>
            <span class="hero-highlight-text">Ăn Cực Ngon • Tiết Kiệm Đến 50%!</span>
        </h1>
        
        <p class="hero-desc">
            Trải nghiệm cảm giác săn <b>Mystery Bag</b> cực cuốn! Thưởng thức các món ngon chuẩn vị từ những nhà hàng, tiệm bánh nổi tiếng tại Đà Nẵng với mức giá sinh viên không tưởng — <b>vừa no căng bụng, vừa chung tay bảo vệ môi trường!</b>
        </p>

        <!-- Dòng cam kết cốt lõi theo quy định đề tài (Diễn đạt kích thích trải nghiệm) -->
        <div class="hero-rule-box">
            🎁 <b>Túi bất ngờ:</b> bạn không chọn được món, đổi lại giá rất tốt! Mỗi chiếc túi là một hộp quà bí mật — bạn nhận về phần ăn có giá trị cao gấp đôi số tiền bỏ ra, 100% tươi mới trong ngày!
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap; margin-top: 15px;">
            <?php if (!$current_user): ?>
                <a href="#danh-sach-tui" class="btn btn-primary" style="padding: 13px 26px; font-weight: 800; font-size: 1rem;">
                    🔥 Săn Túi Mù Ngay (Chỉ Từ 25K)
                </a>
                <a href="<?php echo BASE_URL; ?>/dang_nhap.php" class="btn btn-outline" style="padding: 13px 20px; color: var(--chu); border-color: var(--vien); font-weight: 600;">
                    🔑 Đăng nhập tài khoản
                </a>
                <a href="#ban-do-quan" class="btn btn-outline" style="padding: 13px 18px; color: var(--cam-sang); border-color: var(--cam); font-weight: 600;">
                    📍 Quán gần bạn
                </a>
            <?php elseif ($current_user['role'] === 'student' || $current_user['role'] === 'user'): ?>
                <a href="#danh-sach-tui" class="btn btn-primary" style="padding: 13px 26px; font-weight: 800; font-size: 1rem;">
                    🔥 Săn Túi Ngon Hôm Nay
                </a>
                <a href="<?php echo BASE_URL; ?>/student/vi_tien.php" class="btn btn-outline" style="padding: 13px 20px; color: var(--chu); border-color: var(--vien); font-weight: 600;">
                    💰 Nạp ví tiền ảo (Đang có <?php echo format_money($current_user['balance']); ?>)
                </a>
                <a href="<?php echo BASE_URL; ?>/student/ho_so.php" class="btn btn-outline" style="padding: 13px 18px; color: var(--cam-sang); border-color: var(--cam); font-weight: 600;">
                    ⚙️ Cài đặt hồ sơ
                </a>
            <?php elseif ($current_user['role'] === 'shop_owner'): ?>
                <a href="<?php echo BASE_URL; ?>/shop/them_tui.php" class="btn btn-primary" style="padding: 13px 26px; font-weight: 800; font-size: 1rem;">
                    ➕ Đăng bán túi mới hôm nay
                </a>
                <a href="<?php echo BASE_URL; ?>/shop/nhap_ma.php" class="btn btn-accent" style="padding: 13px 20px; font-weight: 700;">
                    ⚡ Nhập mã nhận hàng
                </a>
                <a href="<?php echo BASE_URL; ?>/shop/ho_so_quan.php" class="btn btn-outline" style="padding: 13px 18px; color: var(--cam-sang); border-color: var(--cam);">
                    ⚙️ Cài đặt quán & Địa chỉ
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Khối thẻ cam kết quyền lợi & kích thích mua hàng bên phải banner -->
    <div class="hero-side-card">
        <div style="font-size: 2.8rem; margin-bottom: 6px;">🎁</div>
        <div style="font-size: 1.25rem; font-weight: 900; color: var(--cam-sang); text-transform: uppercase; letter-spacing: 0.5px; font-family: var(--font-game);">GIẢM SỐC 30% - 50%</div>
        <div style="font-size: 0.82rem; color: var(--chu-phu); margin-bottom: 14px;">Món ngon chuẩn vị • Tươi mới trong ngày</div>
        
        <div style="display: flex; flex-direction: column; gap: 8px; text-align: left;">
            <div style="background: var(--nen-3); padding: 8px 12px; border-radius: 8px; font-size: 0.82rem; color: var(--chu); border: 1px solid var(--vien);">
                🛡️ <b>Thử túi an tâm 45 phút:</b> Bạn hài lòng quán mới nhận tiền!
            </div>
            <div style="background: var(--nen-3); padding: 8px 12px; border-radius: 8px; font-size: 0.82rem; color: var(--chu); border: 1px solid var(--vien);">
                💯 <b>Cam kết 100% hoàn tiền:</b> Nếu đồ ăn không đạt chất lượng
            </div>
            <div style="background: var(--nen-3); padding: 8px 12px; border-radius: 8px; font-size: 0.82rem; color: var(--chu); border: 1px solid var(--vien);">
                ⚡ <b>Số lượng có hạn mỗi tối:</b> Cháy hàng rất nhanh, chốt liền tay!
            </div>
            <div style="background: var(--nen-3); padding: 8px 12px; border-radius: 8px; font-size: 0.82rem; color: var(--chu); border: 1px solid var(--vien);">
                📍 <b>Đà Nẵng:</b> Hải Châu, Sơn Trà, Thanh Khê, Ngũ Hành Sơn...
            </div>
        </div>
    </div>
</section>

<!-- DANH MỤC NHANH (CATEGORY CHIPS PILLS) -->
<section style="margin: 20px 0 10px 0; overflow-x: auto; padding-bottom: 6px;">
    <div class="category-pills-bar">
        <?php foreach ($category_pills as $cat_key => $cat_label): 
            $is_active = ($filter_type === $cat_key);
            $query_params = $_GET;
            if ($cat_key === '') {
                unset($query_params['type']);
            } else {
                $query_params['type'] = $cat_key;
            }
            $cat_url = 'index.php?' . http_build_query($query_params) . '#danh-sach-tui';
        ?>
            <a href="<?php echo htmlspecialchars($cat_url); ?>" class="cat-pill <?php echo $is_active ? 'active' : ''; ?>">
                <?php echo htmlspecialchars($cat_label); ?>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<!-- THANH TÌM KIẾM & BỘ LỌC ĐỊA BÀN ĐÀ NẴNG -->
<section class="filter-bar" id="danh-sach-tui">
    <form method="GET" action="#danh-sach-tui" class="filter-form">
        <div class="form-group" style="flex: 2; min-width: 220px;">
            <label>🔍 Nhập món bạn thèm hoặc tên quán yêu thích</label>
            <input type="text" name="keyword" class="form-control" placeholder="Ví dụ: Bánh mì, Cơm tấm, Trà sữa, Pastry..." value="<?php echo htmlspecialchars($filter_keyword); ?>">
        </div>

        <div class="form-group" style="flex: 1.2; min-width: 170px;">
            <label>📍 Khu vực Đà Nẵng</label>
            <select name="district" class="form-control">
                <option value="">-- Tất cả quận huyện --</option>
                <?php foreach ($districts as $d): ?>
                    <option value="<?php echo htmlspecialchars($d); ?>" <?php echo ($filter_district === $d) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group" style="flex: 1.2; min-width: 170px;">
            <label>🍜 Loại hình món ngon</label>
            <select name="type" class="form-control">
                <option value="">-- Tất cả loại món --</option>
                <?php foreach ($types as $t): ?>
                    <option value="<?php echo htmlspecialchars($t); ?>" <?php echo ($filter_type === $t) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($t); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 8px; align-items: flex-end;">
            <button type="submit" class="btn btn-primary" style="height: 48px; font-weight: 700; padding: 0 22px;">
                🔎 Tìm Deal Ngon
            </button>
            <?php if ($filter_keyword !== '' || $filter_district !== '' || $filter_type !== ''): ?>
                <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-outline" style="height: 48px; display: inline-flex; align-items: center;">Đặt lại</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<!-- DANH SÁCH CÁC TÚI BẤT NGỜ ĐANG MỞ BÁN -->
<section style="margin-bottom: 45px;">
    <div style="display: flex; justify-content: space-between; align-items: baseline; margin-bottom: 18px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="font-size: 1.45rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                🔥 FLASH SALE GIỜ VÀNG: CÁC TÚI MÙ ĐANG MỞ BÁN
            </h2>
            <p style="font-size: 0.88rem; color: var(--text-muted); margin: 3px 0 0 0;">
                Mỗi túi là một suất quà thơm ngon bất ngờ • Số lượng có hạn, chốt ngay trước giờ đóng cửa!
            </p>
        </div>
        <span style="font-size: 0.88rem; color: var(--cam-sang); background: var(--cam-mo); padding: 4px 14px; border-radius: 20px; border: 1px solid var(--cam); font-weight: 700;">
            ⚡ Đang có <b><?php echo mysqli_num_rows($result); ?></b> suất sẵn sàng
        </span>
    </div>

    <?php if (mysqli_num_rows($result) == 0): ?>
        <div class="content-box" style="text-align: center; padding: 50px 20px;">
            <div style="font-size: 3.5rem; margin-bottom: 12px;">🍽️</div>
            <h3 style="color: var(--chu); margin-bottom: 8px;">Đang chuẩn bị lên mâm các túi bất ngờ tiếp theo!</h3>
            <p style="color: var(--chu-phu); font-size: 0.95rem; max-width: 520px; margin: 0 auto 16px auto; line-height: 1.5;">
                Các quán ăn đối tác thường mở bán rộn ràng nhất vào khung giờ <b>13h - 14h</b> (cuối trưa) và <b>19h30 - 22h30</b> (cuối tối). Bạn hãy thử chọn quận khác hoặc quay lại vào khung giờ vàng nhé!
            </p>
            <a href="<?php echo BASE_URL; ?>/index.php" class="btn btn-primary" style="padding: 10px 22px; font-weight: 700;">Xem toàn bộ túi Đà Nẵng</a>
        </div>
    <?php else: ?>
        <div class="bag-grid">
            <?php while ($bag = mysqli_fetch_assoc($result)): 
                $now = time();
                $ts_open = strtotime($bag['open_at']);
                $ts_close = strtotime($bag['close_at']);
                $discount_pct = calculate_discount_percent($bag['original_price'], $bag['web_price']);
                $is_open = ($now >= $ts_open && $now <= $ts_close && $bag['stock'] > 0);
                $is_upcoming = ($now < $ts_open);
                $is_sold_out = ($bag['stock'] <= 0);
            ?>
                <div class="bag-card">
                    <!-- Ảnh túi & Huy hiệu giảm giá -->
                    <div class="bag-thumb-wrap">
                        <?php if (!empty($bag['image'])): ?>
                            <img src="<?php echo BASE_URL . '/' . htmlspecialchars($bag['image']); ?>" alt="<?php echo htmlspecialchars($bag['name']); ?>" class="bag-thumb">
                        <?php else: ?>
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; background: var(--nen-3); font-size: 3.5rem;">
                                🎁
                            </div>
                        <?php endif; ?>

                        <!-- Badge giảm giá nổi bật -->
                        <span class="badge-discount">-<?php echo $discount_pct; ?>% CỰC HỜI</span>

                        <!-- Badge tình trạng còn túi -->
                        <span class="badge-stock <?php echo $bag['stock'] <= 2 ? 'low-stock' : ''; ?>">
                            <?php if ($bag['stock'] > 0): ?>
                                <?php echo $bag['stock'] <= 2 ? '⚡ Chỉ còn ' . $bag['stock'] . ' suất!' : 'Còn ' . $bag['stock'] . ' suất'; ?>
                            <?php else: ?>
                                Hết hàng tối nay
                            <?php endif; ?>
                        </span>
                    </div>

                    <!-- Thông tin túi -->
                    <div class="bag-info">
                        <div class="bag-shop-line">
                            <span class="shop-name-tag">🏪 <b><?php echo htmlspecialchars($bag['shop_name']); ?></b></span>
                            <span class="district-tag">📍 <?php echo htmlspecialchars($bag['district']); ?></span>
                        </div>

                        <h3 class="bag-name">
                            <a href="<?php echo BASE_URL; ?>/tui_chi_tiet.php?id=<?php echo $bag['id']; ?>">
                                <?php echo htmlspecialchars($bag['name']); ?>
                            </a>
                        </h3>

                        <!-- Khung giờ nhận túi -->
                        <div class="bag-time-line">
                            ⏰ Giờ nhận túi: <b><?php echo date('H:i', strtotime($bag['pickup_start'])) . ' - ' . date('H:i', strtotime($bag['pickup_end'])); ?></b>
                        </div>

                        <!-- Giá bán -->
                        <div class="bag-pricing">
                            <div>
                                <span class="price-label">Giá niêm yết tại quán:</span>
                                <span class="orig-price"><?php echo format_money($bag['original_price']); ?></span>
                            </div>
                            <div style="text-align: right;">
                                <span class="price-label" style="color: var(--accent); font-weight: bold;">Giá cứu trợ (-<?php echo $discount_pct; ?>%):</span>
                                <span class="web-price"><?php echo format_money($bag['web_price']); ?></span>
                            </div>
                        </div>

                        <!-- Nút hành động đặt túi -->
                        <?php if ($is_open): ?>
                            <a href="<?php echo BASE_URL; ?>/tui_chi_tiet.php?id=<?php echo $bag['id']; ?>" class="btn btn-primary btn-block btn-buy">
                                ⚡ Chốt Túi Bí Mật Ngay
                            </a>
                        <?php elseif ($is_upcoming): 
                            $diff_mins = ceil(($ts_open - $now) / 60);
                        ?>
                            <a href="<?php echo BASE_URL; ?>/tui_chi_tiet.php?id=<?php echo $bag['id']; ?>" class="btn btn-outline btn-block" style="color: var(--accent); border-color: var(--accent); font-weight: 700;">
                                ⏳ Mở bán lúc <?php echo date('H:i', $ts_open); ?> (F5 canh giờ)
                            </a>
                        <?php else: ?>
                            <button type="button" class="btn btn-block disabled" disabled>
                                Đã hết hàng tối nay
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</section>

<!-- KHỐI BẢN ĐỒ ĐIỂM CỨU TRỢ THỰC PHẨM ĐÀ NẴNG (FOOD RESCUE MAP) -->
<section class="rescue-map-section" id="ban-do-quan">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; flex-wrap: wrap; gap: 10px;">
        <div>
            <h2 style="font-size: 1.35rem; font-weight: 800; color: var(--text-main); margin: 0; display: flex; align-items: center; gap: 8px;">
                🗺️ BẢN ĐỒ "SĂN" MÓN NGON QUANH BẠN (ĐÀ NẴNG)
            </h2>
            <p style="font-size: 0.85rem; color: var(--text-muted); margin: 4px 0 0 0;">
                Định vị ngay các quán ăn, tiệm bánh chuẩn xịn gần trường hoặc nhà bạn để ghé lấy túi nóng hổi!
            </p>
        </div>
        <div style="font-size: 0.85rem; color: var(--cam-sang); background: var(--cam-mo); border: 1px solid var(--cam); padding: 4px 12px; border-radius: 20px; font-weight: 600;">
            📍 Khu vực hiển thị: <?php echo htmlspecialchars($filter_district !== '' ? $filter_district : 'Toàn TP. Đà Nẵng'); ?>
        </div>
    </div>

    <div class="map-layout-grid">
        <!-- Khung bản đồ tương tác (Google Maps Embed Iframe chuẩn HTML) -->
        <div class="map-iframe-container">
            <iframe 
                width="100%" 
                height="100%" 
                style="border:0; min-height: 380px; width: 100%;" 
                loading="lazy" 
                allowfullscreen 
                src="https://maps.google.com/maps?q=<?php echo urlencode($map_search_query); ?>&t=&z=13&ie=UTF8&iwloc=&output=embed">
            </iframe>
        </div>

        <!-- Danh sách các quán ăn đối tác xung quanh -->
        <div class="map-shops-sidebar">
            <h3 style="font-size: 1.05rem; font-weight: 700; color: var(--text-main); margin-bottom: 12px; border-bottom: 1px solid var(--border-color); padding-bottom: 8px;">
                🏪 Quán ruột được săn đón nhiều nhất
            </h3>

            <div style="display: flex; flex-direction: column; gap: 12px;">
                <?php if (mysqli_num_rows($res_partner_shops) > 0): ?>
                    <?php while ($ps = mysqli_fetch_assoc($res_partner_shops)): ?>
                        <div class="partner-shop-card">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 4px;">
                                <h4 style="margin: 0; font-size: 0.95rem; font-weight: 700; color: var(--text-main);">
                                    <?php echo htmlspecialchars($ps['name']); ?>
                                </h4>
                                <span class="district-badge">Q. <?php echo htmlspecialchars($ps['district']); ?></span>
                            </div>
                            <p style="font-size: 0.82rem; color: var(--chu-phu); margin: 2px 0 6px 0;">
                                📍 <?php echo htmlspecialchars($ps['address']); ?>
                            </p>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <?php if (!empty($ps['phone'])): ?>
                                    <a href="tel:<?php echo htmlspecialchars($ps['phone']); ?>" style="font-size: 0.8rem; color: var(--thanh-cong); font-weight: 600;">
                                        📞 <?php echo htmlspecialchars($ps['phone']); ?>
                                    </a>
                                <?php else: ?>
                                    <span style="font-size: 0.8rem; color: var(--chu-phu);">Ẩm thực: <?php echo htmlspecialchars($ps['type']); ?></span>
                                <?php endif; ?>

                                <a href="index.php?keyword=<?php echo urlencode($ps['name']); ?>#danh-sach-tui" class="btn btn-sm btn-outline" style="font-size: 0.78rem; padding: 3px 10px; font-weight: 600;">
                                    Xem túi (<?php echo $ps['active_bags']; ?> suất)
                                </a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="font-size: 0.85rem; color: var(--chu-phu); text-align: center; padding: 20px 0;">
                        Chưa có quán nào trong khu vực này.
                    </p>
                <?php endif; ?>
            </div>

            <div style="margin-top: 14px; padding-top: 10px; border-top: 1px dashed var(--border-color); text-align: center;">
                <a href="https://www.google.com/maps/search/?api=1&query=<?php echo urlencode($map_search_query); ?>" target="_blank" class="btn btn-sm btn-outline btn-block" style="font-size: 0.82rem; font-weight: 600;">
                    🗺️ Mở bản đồ lớn trên Google Maps ↗
                </a>
            </div>
        </div>
    </div>
</section>

<?php require_once __DIR__ . '/footer.php'; ?>
