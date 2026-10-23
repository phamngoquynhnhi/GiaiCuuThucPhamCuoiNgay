-- ====================================================================
-- CƠ SỞ DỮ LIỆU DỰ ÁN: GIẢI CỨU THỰC PHẨM CUỐI NGÀY (TÚI BẤT NGỜ)
-- Khu vực thử nghiệm: Thành phố Đà Nẵng
-- Hệ quản trị: MySQL / MariaDB (InnoDB, utf8mb4)
-- ====================================================================

-- 1. Tạo cơ sở dữ liệu nếu chưa có
CREATE DATABASE IF NOT EXISTS `giaicuu` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `giaicuu`;

-- 2. Tắt kiểm tra khóa ngoại tạm thời để dễ dàng khởi tạo lại
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `notifications`;
DROP TABLE IF EXISTS `wallet_logs`;
DROP TABLE IF EXISTS `disputes`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `bags`;
DROP TABLE IF EXISTS `shops`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `settings`;
SET FOREIGN_KEY_CHECKS = 1;

-- 3. Bảng users: Lưu thông tin người dùng (Sinh viên, Chủ quán, Quản trị viên)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `phone` VARCHAR(20) NULL,
  `password` VARCHAR(255) NOT NULL,
  `role` VARCHAR(20) NOT NULL DEFAULT 'user',
  `balance` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `warning_count` INT NOT NULL DEFAULT 0,
  `is_locked` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Bảng shops: Lưu thông tin quán ăn tại Đà Nẵng
CREATE TABLE `shops` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `owner_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `address` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) NULL,
  `district` VARCHAR(50) NOT NULL,
  `type` VARCHAR(50) NOT NULL,
  `image` VARCHAR(255) NULL,
  `violation_count` INT NOT NULL DEFAULT 0,
  `status` ENUM('cho_duyet', 'da_duyet') NOT NULL DEFAULT 'cho_duyet',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`owner_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Bảng bags: Lưu thông tin Túi bất ngờ do quán mở bán
CREATE TABLE `bags` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `shop_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `original_price` DECIMAL(10,2) NOT NULL,
  `web_price` DECIMAL(10,2) NOT NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `open_at` DATETIME NOT NULL,
  `pickup_start` DATETIME NOT NULL,
  `pickup_end` DATETIME NOT NULL,
  `close_at` DATETIME NOT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Bảng orders: Lưu đơn hàng đặt túi bất ngờ
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `bag_id` INT NOT NULL,
  `shop_id` INT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `status` ENUM('cho_nhan', 'da_nhan', 'hoan_thanh', 'tranh_chap', 'hoan_tien_sv', 'chuyen_tien_quan', 'huy_boi_khach', 'huy_vang_mat') NOT NULL DEFAULT 'cho_nhan',
  `pickup_code` VARCHAR(10) NOT NULL UNIQUE,
  `close_at` DATETIME NOT NULL,
  `picked_up_at` DATETIME NULL,
  `hold_until` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`bag_id`) REFERENCES `bags`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`shop_id`) REFERENCES `shops`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Bảng disputes: Lưu báo cáo gian lận / tranh chấp đơn hàng
CREATE TABLE `disputes` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL UNIQUE,
  `reason` VARCHAR(255) NOT NULL,
  `student_note` TEXT NULL,
  `student_image` VARCHAR(255) NOT NULL,
  `shop_note` TEXT NULL,
  `shop_image` VARCHAR(255) NULL,
  `status` ENUM('cho_quan_phan_hoi', 'cho_admin_xu_ly', 'chap_nhan_khieu_nai', 'bac_khieu_nai') NOT NULL DEFAULT 'cho_quan_phan_hoi',
  `admin_note` TEXT NULL,
  `resolved_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Bảng wallet_logs: Lịch sử biến động số dư tiền ảo
CREATE TABLE `wallet_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `amount` DECIMAL(12,2) NOT NULL,
  `reason` VARCHAR(255) NOT NULL,
  `order_id` INT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Bảng notifications: Lưu thông báo hệ thống gửi cho người dùng
CREATE TABLE `notifications` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `is_read` TINYINT(1) NOT NULL DEFAULT 0,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Bảng settings: Cấu hình hệ thống linh hoạt
CREATE TABLE `settings` (
  `name` VARCHAR(50) PRIMARY KEY,
  `value` VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Chèn dữ liệu cấu hình mặc định
INSERT INTO `settings` (`name`, `value`) VALUES
('discount_min', '30'),
('discount_max', '50'),
('cancel_before_close_minutes', '90'),
('hold_minutes', '45'),
('dispute_response_hours', '48'),
('max_bags_per_user', '2'),
('max_disputes_per_month', '3'),
('commission_percent', '10');

-- 12. Tài khoản quản trị viên mặc định (Mật khẩu: admin123)
-- Hash được tạo từ hàm password_hash('admin123', PASSWORD_DEFAULT)
INSERT INTO `users` (`name`, `email`, `password`, `role`, `balance`, `warning_count`, `is_locked`, `created_at`) VALUES
('Ban Quản Trị Hệ Thống', 'admin@giaicuu.vn', '$2y$10$O/RVj7z2Ji.KOPfkCnkB6.c7xkIeb9Q40teD2FqFknl.3SReeUJ/2', 'admin', 0.00, 0, 0, NOW());
