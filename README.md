pp# 🌱 HỆ THỐNG GIẢI CỨU THỰC PHẨM CUỐI NGÀY (MÔ HÌNH "TÚI BẤT NGỜ" - ĐÀ NẴNG)

Dự án website hoàn chỉnh mô phỏng mô hình "Túi bất ngờ" (Surprise Bag / Too Good To Go) tại Thành phố Đà Nẵng, giúp các quán ăn giải cứu thực phẩm cuối ngày còn thơm ngon với giá giảm **30% - 50%**, đồng thời hỗ trợ sinh viên tiếp cận bữa ăn chất lượng với chi phí tiết kiệm.

---

## 🎯 MÔ TẢ CÔNG NGHỆ VÀ RÀNG BUỘC KỸ THUẬT
- **Mức độ:** Dành cho sinh viên năm 2 mới học PHP và MySQL.
- **Ngôn ngữ:** PHP 8+ thuần (Hướng thủ tục - Procedural, không OOP, không Composer, không Framework).
- **Cơ sở dữ liệu:** MySQL / MariaDB (InnoDB, bảng mã utf8mb4) kết nối bằng thư viện `mysqli` thủ tục + Prepared Statements.
- **Giao diện:** HTML5 + CSS thuần (responsive trên điện thoại và máy tính, tone màu Eco Forest `#091a12` + Cam ấm `#ea580c`, lấy cảm hứng từ giao diện ẩm thực cao cấp, thẻ bo tròn mềm mại).
- **Tích hợp Bản đồ (Map):** Tích hợp bản đồ trực quan bằng Google Maps Embed iframe (chuẩn HTML, không cần JavaScript/API key) tại Trang chủ (Bản đồ cứu trợ Đà Nẵng), Trang chi tiết túi (`tui_chi_tiet.php`), Chi tiết đơn hàng (`student/chi_tiet_don.php`) và Cài đặt quán (`shop/ho_so_quan.php`).
- **Cài đặt hồ sơ cá nhân:**
  - **Người dùng / Sinh viên (`student/ho_so.php`):** Cập nhật họ tên, số điện thoại liên lạc, đổi mật khẩu.
  - **Chủ quán (`shop/ho_so_quan.php`):** Cập nhật tên quán, địa chỉ nhận túi, số điện thoại hotline, quận, loại ẩm thực, ảnh mặt tiền/giấy phép và xem vị trí quán trên bản đồ.
- **Ràng buộc:** Không sử dụng JavaScript, AJAX, QR code, camera, Composer hay thư viện bên ngoài. Mọi tính toán thời gian, trừ kho, kiểm tra tài khoản thực hiện an toàn trên server PHP.

---

## 🚀 HƯỚNG DẪN CÀI ĐẶT VÀ KHỞI CHẠY (XAMPP WINDOWS)

### 1. Chuẩn bị thư mục và máy chủ
1. Mở phần mềm **XAMPP Control Panel**, bấm **Start** cả hai dịch vụ **Apache** và **MySQL**.
2. Đặt toàn bộ mã nguồn của dự án vào thư mục web:
   - Đường dẫn chuẩn: `C:\xampp\htdocs\giaicuu\` (hoặc thư mục hiện tại `D:\DACS2`).
   - Nếu chạy trực tiếp từ `D:\DACS2`, bạn có thể dùng lệnh PHP Server tích hợp sẵn:
     ```powershell
     cd D:\DACS2
     php -S localhost:8000
     ```
     Sau đó truy cập: `http://localhost:8000/`

### 2. Khởi tạo Cơ sở dữ liệu và Dữ liệu Mẫu Demo
Có 2 cách rất dễ dàng:
- **Cách 1 (Tự động 1 click - Khuyên dùng):** Mở trình duyệt vào link `http://localhost/giaicuu/tao_du_lieu_mau.php`, bấm nút **"Khởi Tạo Dữ Liệu Mẫu Ngay"**. Hệ thống sẽ tự động tạo database `giaicuu`, nạp các bảng và tạo sẵn đầy đủ tài khoản Admin, Sinh viên (có sẵn tiền ảo), Quán ăn và các Túi bất ngờ.
- **Cách 2 (Thủ công qua phpMyAdmin):**
  1. Truy cập `http://localhost/phpmyadmin/`.
  2. Bấm vào tab **Import** (Nhập).
  3. Chọn tập tin `sql/database.sql` trong thư mục dự án và bấm **Import / Thực hiện**.
  4. Sau đó vào `http://localhost/giaicuu/tao_du_lieu_mau.php` để nạp dữ liệu mẫu demo.
Hệ thống sẽ tự động tạo:
- 1 Quản trị viên (Admin).
- 3 Sinh viên mẫu có sẵn tiền ảo trong ví (200k, 150k, 300k).
- 5 Quán ăn thật phong phú tại các quận Hải Châu, Thanh Khê, Sơn Trà, Liên Chiểu, Ngũ Hành Sơn.
- Nhiều Túi bất ngờ với đầy đủ trạng thái: đang mở bán, sắp mở bán, giảm giá đúng quy định 30%-50%.

---

## 🔑 DANH SÁCH TÀI KHOẢN DÙNG THỬ (DEMO ACCOUNTS)

| Vai trò | Email đăng nhập | Mật khẩu | Ghi chú & Mục đích demo |
| :--- | :--- | :--- | :--- |
| **Quản trị viên** | `admin@giaicuu.vn` | `admin123` | Phân xử tranh chấp (Option A / B), duyệt quán ăn, cấu hình tham số, kiểm tra ví tiền |
| **Sinh viên 1** | `sinhvien1@gmail.com` | `sv123456` | Nguyễn Văn Nam (ĐH Bách Khoa) - Ví có sẵn **200.000 đ** |
| **Sinh viên 2** | `sinhvien2@gmail.com` | `sv123456` | Trần Thị Mai (ĐH Kinh Tế) - Ví có sẵn **150.000 đ** |
| **Sinh viên 3** | `sinhvien3@gmail.com` | `sv123456` | Lê Quốc Bảo (ĐH Sư Phạm) - Ví có sẵn **300.000 đ** |
| **Chủ quán 1** | `quan_banhmi@gmail.com` | `shop123` | Bánh Mì & Xôi Đệ Nhất (Q. Hải Châu) - Đã duyệt, có túi đang mở bán |
| **Chủ quán 2** | `quan_com@gmail.com` | `shop123` | Cơm Niêu Phố Cổ (Q. Thanh Khê) - Đã duyệt, có túi đang mở bán |
| **Chủ quán 3** | `quan_banhngot@gmail.com` | `shop123` | French Bakery (Q. Sơn Trà) - Đã duyệt, có túi bánh ngọt nướng |
| **Chủ quán 4** | `quan_trasua@gmail.com` | `shop123` | Trà Sữa Nhà Làm (Q. Liên Chiểu) - Túi sắp mở bán (countdown) |
| **Chủ quán 5** | `quan_chay@gmail.com` | `shop123` | Quán Chay An Lạc (Q. Ngũ Hành Sơn) - Trạng thái **Chờ Admin duyệt** |

---

## ⚡ MẸO DEMO BÁO CÁO NHANH (RÚT NGẮN THỜI GIAN GIỮ TIỀN & HỦY ĐƠN)

Trong thực tế, tiền được giữ **45 phút** sau khi nhận hàng và được hủy đơn trước giờ đóng cửa **90 phút**. Khi thuyết trình bài tập lớn, để không phải chờ đợi lâu:
1. Đăng nhập bằng tài khoản Quản trị viên: `admin@giaicuu.vn` / `admin123`.
2. Vào menu **Cài đặt** (`/admin/cai_dat.php`).
3. Chỉnh 2 thông số sau:
   - **"Thời gian giữ tiền bảo vệ sinh viên (Phút)":** đổi từ `45` thành `1` hoặc `2` phút.
   - **"Thời hạn hủy đơn trước giờ đóng cửa (Phút)":** đổi từ `90` thành `2` phút.
4. Bấm **"Lưu Thay Đổi Cài Đặt"**.
5. Bây giờ, sau khi chủ quán nhập mã nhận hàng, bạn chỉ cần đợi 1 phút rồi F5 (tải lại trang), hàm `check_orders()` sẽ tự động chuyển đơn sang `hoan_thanh` và cộng tiền ngay vào ví cho chủ quán!

---

## 🧪 KỊCH BẢN KIỂM TRA BẰNG TAY (MANUAL TEST CASES)

Thầy cô và các bạn sinh viên có thể kiểm tra toàn diện 10 kịch bản nghiệp vụ cốt lõi sau:

### 1. Đặt chiếc túi cuối cùng (Trừ kho nguyên tử)
- Đăng nhập tài khoản sinh viên `sinhvien1@gmail.com`.
- Vào chi tiết một túi còn đúng 1 sản phẩm và bấm "Xác nhận đặt túi".
- Kiểm tra: Đơn hàng tạo thành công, số lượng túi trong kho giảm về 0, quay lại trang chủ túi hiển thị nhãn "Hết hàng" và nút mua bị khóa.
- Người thứ 2 cố tình đặt cùng lúc sẽ nhận thông báo lỗi "Túi vừa được người khác mua hết" do lệnh `UPDATE bags SET stock = stock - 1 WHERE id=? AND stock >= 1` đảm bảo an toàn.

### 2. Mua trước giờ mở bán
- Vào trang chủ xem túi của quán `Trà Sữa & Topping` (Q. Liên Chiểu). Túi được thiết lập mở bán sau 25 phút.
- Kiểm tra: Nút mua bị khóa, hiển thị dòng "Mở bán sau X phút".
- Vào trang chi tiết túi: Nút xác nhận bị vô hiệu hóa, thông báo rõ ràng "Chưa đến giờ mở bán".

### 3. Số dư ví không đủ
- Tạo một sinh viên mới bằng trang Đăng ký (số dư ban đầu 0 đ) hoặc đặt mua cho tới khi số dư ví nhỏ hơn giá web của túi.
- Bấm vào chi tiết túi: Hệ thống hiển thị cảnh báo màu đỏ "Số dư ví không đủ", đồng thời cung cấp nút chuyển hướng nhanh sang trang nạp tiền demo (`/student/vi_tien.php`).

### 4. Hủy đơn sớm vs Hủy đơn muộn
- **Hủy sớm (Hợp lệ):** Đặt một đơn hàng có giờ đóng cửa còn xa (cách hơn 90 phút). Vào trang "Đơn của tôi" -> "Chi tiết đơn", bấm nút "Tôi muốn hủy đơn này". Kết quả: Đơn chuyển sang `huy_boi_khach`, sinh viên được hoàn lại 100% tiền vào ví, túi tự động cộng trả lại vào kho của quán.
- **Hủy muộn (Bị chặn):** Đối với đơn hàng có giờ đóng cửa cách hiện tại dưới 90 phút, nút hủy đơn tự động bị ẩn và thay bằng thông báo đỏ: Quán đã chuẩn bị thức ăn, không thể tự ý hủy đơn.

### 5. Chủ quán nhập mã nhận hàng: Nhập mã 2 lần
- Sinh viên đưa mã nhận hàng 6 ký tự (ví dụ: `7A9K2M`).
- Chủ quán đăng nhập, vào "Nhập mã nhận hàng", nhập mã lần 1: Đơn chuyển sang `da_nhan`, ghi nhận thời điểm nhận và thời hạn giữ tiền 45 phút.
- Chủ quán gõ lại chính mã đó lần 2: Hệ thống báo lỗi tiếng Việt: *"⚠️ Đơn hàng này đã được nhận hàng trước đó lúc HH:mm! Không thể nhận lại lần 2."*

### 6. Nhập mã của quán khác
- Lấy mã nhận hàng của một đơn đặt tại quán A.
- Đăng nhập tài khoản chủ quán B, vào trang "Nhập mã nhận hàng" và gõ mã trên.
- Kết quả: Hệ thống báo lỗi: *"❌ Mã nhận hàng này thuộc về quán khác! Không phải đơn đặt tại quán của bạn."*

### 7. Khách vắng mặt không đến nhận túi
- Một đơn hàng ở trạng thái `cho_nhan` nhưng sinh viên không đến lấy và để quá giờ đóng cửa (`close_at`).
- Khi bất kỳ ai truy cập website, hàm `check_orders()` tự động quét đơn quá hạn này:
  - Đơn chuyển sang `huy_vang_mat`.
  - Toàn bộ tiền đơn hàng được chuyển cho chủ quán làm phí bồi thường.
  - **Không** cộng lại kho (vì thức ăn cuối ngày đã hết hạn dùng).

### 8. Báo cáo gian lận trong thời gian giữ tiền
- Sau khi quán nhập mã nhận hàng, đơn ở trạng thái `da_nhan` (đang giữ tiền).
- Sinh viên vào chi tiết đơn, bấm nút "Báo cáo gian lận / Khiếu nại chất lượng".
- Chọn lý do: "Đồ ăn bị ôi thiu", nhập mô tả và tải lên 1 ảnh bằng chứng.
- Bấm gửi: Đơn chuyển ngay sang `tranh_chap`. Tiền tiếp tục bị đóng băng an toàn trong hệ thống, không tự động chuyển cho quán.

### 9. Quản trị viên phân xử 2 hướng (Option A & Option B)
Đăng nhập tài khoản Admin (`admin@giaicuu.vn` / `admin123`), vào mục **Xử lý tranh chấp**:
- **Hướng A (Bảo vệ sinh viên):** Admin nhập lý do phân xử, bấm "Hướng A: Hoàn 100% cho SV (+1 Vi phạm quán)". Kết quả: Sinh viên nhận lại 100% tiền vào ví, quán bị tăng 1 lần vi phạm, đơn thành `hoan_tien_sv`.
- **Hướng B (Bảo vệ quán ăn):** Admin nhập lý do phân xử, bấm "Hướng B: Bác khiếu nại (Chuyển tiền quán + Cảnh cáo SV)". Kết quả: Tiền được chuyển cho chủ quán, sinh viên nhận 1 cảnh cáo. Nếu sinh viên tích lũy đủ 3 lần cảnh cáo, tài khoản sẽ tự động bị khóa!

### 10. Kiểm tra cân bằng dòng tiền (Toàn vẹn số dư)
- Vào menu **Kiểm tra ví** (`/admin/kiem_tra_tien.php`).
- Xem công thức: `Tổng số dư ví (A) + Tiền đang tạm giữ trong đơn (B) = Tổng tiền đã nạp demo (C)`.
- Dù bạn thực hiện nạp tiền, đặt hàng, hủy đơn hay phân xử tranh chấp bao nhiêu lần, hệ thống luôn duy trì trạng thái **"✅ DÒNG TIỀN CÂN BẰNG TUYỆT ĐỐI"** không lệch một xu.

---

## 📁 CẤU TRÚC THƯ MỤC DỰ ÁN

```text
d:\DACS2\
├── config.php                # Cấu hình session, múi giờ Việt Nam, kết nối mysqli
├── functions.php             # Các hàm nghiệp vụ dùng chung, check_orders()
├── header.php                # Menu điều hướng theo vai trò (Guest, Student, Shop, Admin)
├── footer.php                # Chân trang và thông tin bản quyền
├── index.php                 # Trang chủ: Danh sách túi, bộ lọc quận & loại quán, hero card
├── tui_chi_tiet.php          # Chi tiết túi bất ngờ, đếm ngược giờ mở bán, đặt túi
├── dang_ky.php               # Đăng ký tài khoản Sinh viên / Chủ quán (kèm hồ sơ quán)
├── dang_nhap.php             # Đăng nhập an toàn bằng password_verify
├── dang_xuat.php             # Đăng xuất an toàn khỏi hệ thống
├── tao_du_lieu_mau.php       # Khởi tạo dữ liệu mẫu phong phú tại Đà Nẵng
├── README.md                 # Tài liệu hướng dẫn sử dụng và kiểm thử chi tiết
├── css/
│   └── style.css             # Giao diện hiện đại, tone xanh lá + cam ấm, responsive
├── sql/
│   └── database.sql          # Toàn bộ cấu trúc bảng InnoDB và dữ liệu cấu hình mặc định
├── uploads/                  # Thư mục lưu ảnh tải lên an toàn (jpg, png, webp <= 2MB)
├── student/                  # Phân hệ dành cho Sinh viên
│   ├── vi_tien.php           # Xem số dư, nạp tiền demo, lịch sử biến động số dư
│   ├── dat_tui.php           # Xử lý đặt túi: Trừ kho nguyên tử + trừ tiền trong transaction
│   ├── don_hang.php          # Danh sách đơn đã đặt kèm mã nhận hàng to rõ
│   ├── chi_tiet_don.php      # Chi tiết đơn, countdown giữ tiền, nút Hủy / Tôi hài lòng
│   ├── huy_don.php           # Xử lý hủy đơn hoàn tiền và cộng kho trước giờ đóng cửa
│   ├── hai_long.php          # Xác nhận "Tôi hài lòng" để nhả tiền sớm cho quán
│   ├── khieu_nai.php         # Báo cáo gian lận (thực phẩm hỏng, sai mô tả) kèm ảnh
│   └── thong_bao.php         # Xem thông báo và đánh dấu đã đọc
├── shop/                     # Phân hệ dành cho Chủ quán
│   ├── ho_so_quan.php        # Hồ sơ quán, cập nhật SĐT, ảnh mặt tiền/GPKD, theo dõi duyệt
│   ├── quan_ly_tui.php       # Danh sách túi đã tạo của quán
│   ├── them_tui.php          # Thêm túi mới: Validate giảm giá 30%-50% và mốc thời gian
│   ├── sua_tui.php           # Sửa túi: Khóa sửa giá nếu túi đã có người đặt
│   ├── xoa_tui.php           # Xóa túi: Chặn xóa nếu túi đã có đơn hàng
│   ├── nhap_ma.php           # Nhập mã nhận hàng 6 ký tự để giao túi, chuyển da_nhan
│   ├── don_hang.php          # Danh sách đơn hàng theo trạng thái của quán
│   ├── phan_hoi_khieu_nai.php# Gửi giải trình phản đối khiếu nại kèm ảnh đối chứng
│   └── doanh_thu.php         # Báo cáo doanh thu: Đang giữ, Đã nhận sau trừ 10% phí sàn, Tranh chấp
└── admin/                    # Phân hệ Quản trị viên
    ├── tong_quan.php         # Dashboard quản trị chính: Túi giải cứu, Tiền tiết kiệm, Hoa hồng sàn 10%
    ├── index.php             # Tự động điều hướng về tong_quan.php
    ├── duyet_quan.php        # Phê duyệt quán mới (xem ảnh GPKD/mặt tiền, gọi SĐT)
    ├── nguoi_dung.php        # Quản lý tài khoản, khóa / mở khóa, xóa cảnh cáo
    ├── tranh_chap.php        # Trọng tài phân xử tranh chấp theo 2 hướng (Option A & B)
    ├── cai_dat.php           # Quản lý bảng settings (hoa hồng sàn, thời gian giữ tiền, hủy đơn)
    └── kiem_tra_tien.php     # Kiểm toán bảo toàn dòng tiền ảo tự động
```

---

## 👨‍🎓 TÁC GIẢ & BẢN QUYỀN
- Đề tài: Giải cứu thực phẩm cuối ngày (Mô hình Túi bất ngờ tại TP. Đà Nẵng).
- Sinh viên thực hiện: Sinh viên năm 2 - Đồ án cơ sở 2 (DACS2).
- Sử dụng công nghệ PHP & MySQL căn bản, tuân thủ 100% chuẩn mực bảo mật và nghiệp vụ.
