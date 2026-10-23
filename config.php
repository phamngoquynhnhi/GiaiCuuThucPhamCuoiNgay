<?php
/**
 * ====================================================================
 * TẬP TIN CẤU HÌNH HỆ THỐNG (config.php)
 * Dành cho sinh viên năm 2 - Dễ hiểu, kết nối hướng thủ tục
 * ====================================================================
 */

// 1. Bắt đầu phiên làm việc Session (nếu chưa có)
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. Thiết lập múi giờ Việt Nam
date_default_timezone_set('Asia/Ho_Chi_Minh');

// 3. Thông tin kết nối Cơ sở dữ liệu MySQL
$db_host = '127.0.0.1';
$db_user = 'root';
$db_pass = '';
$db_name = 'giaicuu';
$db_port = 3306;

// 4. Kết nối database bằng mysqli hướng thủ tục
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name, $db_port);

// Kiểm tra kết nối
if (!$conn) {
    // Nếu chưa tạo database hoặc lỗi kết nối, thông báo rõ ràng
    die("<div style='font-family: Arial, sans-serif; padding: 20px; background: #fee2e2; border: 1px solid #ef4444; border-radius: 8px; margin: 20px; color: #991b1b;'>
        <h3 style='margin-top:0;'>⚠️ Lỗi kết nối cơ sở dữ liệu MySQL!</h3>
        <p>Chi tiết lỗi: " . htmlspecialchars(mysqli_connect_error()) . "</p>
        <p><b>Hướng dẫn:</b> Bạn hãy mở phpMyAdmin hoặc MySQL CLI và import file <code>sql/database.sql</code> để tạo database <code>giaicuu</code>.</p>
        <p><a href='tao_du_lieu_mau.php' style='color: #2563eb; text-decoration: underline;'>Bấm vào đây để khởi tạo dữ liệu mẫu và kiểm tra hệ thống</a></p>
    </div>");
}

// 5. Thiết lập bảng mã UTF-8 để hiển thị tiếng Việt chính xác
mysqli_set_charset($conn, 'utf8mb4');

// 6. Tự động xác định đường dẫn gốc website (BASE_URL)
// Giúp liên kết hoạt động tốt cả trên http://localhost/giaicuu/ hay http://localhost:8000/
if (!defined('BASE_URL')) {
    $doc_root = isset($_SERVER['DOCUMENT_ROOT']) ? str_replace('\\', '/', realpath($_SERVER['DOCUMENT_ROOT'])) : '';
    $app_dir  = str_replace('\\', '/', __DIR__);
    
    if ($doc_root && strpos($app_dir, $doc_root) === 0) {
        $rel_path = substr($app_dir, strlen($doc_root));
        $base = rtrim(str_replace('\\', '/', $rel_path), '/');
        define('BASE_URL', $base);
    } else {
        // Mặc định nếu không phát hiện được thì dùng đường dẫn tương đối
        define('BASE_URL', '/giaicuu');
    }
}

// Đường dẫn thư mục uploads trên máy chủ
if (!defined('UPLOAD_DIR')) {
    define('UPLOAD_DIR', __DIR__ . '/uploads/');
}
