# EDL — Equipment Document Library (Thư viện tài liệu thiết bị)

Phần mềm quản lý tập trung tài liệu kỹ thuật theo **thiết bị**: hướng dẫn vận hành, SOP, bản vẽ, hồ sơ
hiệu chuẩn / kiểm định, thẩm định IQ/OQ/PQ, bảo trì, chương trình PLC… — phân quyền theo **bộ phận**,
tìm kiếm toàn văn trong nội dung file, tải lên hàng loạt.

## Kiến trúc

| Hạng mục | Lựa chọn | Tham chiếu |
|---|---|---|
| Ngôn ngữ / framework | PHP 8.2 + Laravel 12, Blade, MySQL/MariaDB | REMS |
| Truy cập dữ liệu | **Chỉ Query Builder (`DB::table`)** — không dùng Eloquent | WMS |
| Giao diện | CSS thuần `public/css` (font Arimo offline), JS thuần `public/js`; xem PDF bằng PDF.js 3.11.174 (`public/vendor/pdfjs`), xem Word bằng docx-preview 0.3.7 + JSZip 3.10.1 (`public/vendor/docx-preview`) — **không dùng CDN, máy trạm không cần internet** | REMS |
| Đăng nhập | Laravel Auth, provider `database` (GenericUser) | — |
| Phân quyền | Vai trò gán **theo bộ phận** + quyền riêng allow/deny từng user + chọn bộ phận làm việc | WMS |
| Bảo mật tài khoản | Khoá 15' sau 5 lần sai, mật khẩu hết hạn 90 ngày, chống dùng lại 5 mật khẩu, buộc đổi lần đầu, tự đăng xuất 15' | WMS (21 CFR Part 11) |
| Nhật ký | `audit_logs` chỉ ghi thêm | WMS |
| Lưu file | Theo đối tượng: `storage/app/private/documents/device_{id}/{MÃ_LOẠI}/{uuid}.{ext}` — disk **riêng tư**, mọi lượt xem/tải đi qua controller | REMS (folder theo đối tượng) |

```
app/
├── Authorization/PermissionHelper.php   user_can(), user_can_any(), current_department_id()… (như WMS)
├── Http/Controllers/
│   ├── Auth/            đăng nhập, đổi mật khẩu, chuyển bộ phận
│   ├── Admin/           người dùng, vai trò, nhật ký
│   ├── MasterData/      dữ liệu gốc — 1 controller khung + 6 danh mục
│   ├── Devices/         thiết bị, nhãn QR
│   └── Documents/       thư viện, tải lên hàng loạt, phiên bản, thùng rác, tìm nhanh
├── Http/Middleware/     CheckPermission, SessionIdleTimeout, EnsurePasswordIsValid, SecurityHeaders
├── Services/
│   ├── Security/        AccessControl, AuditLogger, PasswordPolicy
│   └── Documents/       DocumentStorage, DocumentService, TextExtractor, FilePreview, SmartFill
└── Support/             DocumentScope, DocumentSearch, Lookup, Labels, FileType, QrCode (từ WMS)
resources/views/
├── layouts/  auth/  home/  errors/
├── master-data/ (index + form dùng chung cho mọi danh mục)
├── devices/  documents/ (+ partials)  admin/
config/edl.php           chính sách bảo mật, định dạng file, giới hạn tải lên
```

## Chức năng

1. **Quản lý tài liệu theo thiết bị** — mỗi thiết bị là một hồ sơ, tài liệu xếp theo thư mục loại tài liệu.
2. **Tìm kiếm nhanh** — ô tìm trên cùng (`Ctrl+K` hoặc `/`) gợi ý tức thì; trang tra cứu lọc theo tên, mã,
   loại tài liệu, thiết bị, loại thiết bị, bộ phận, hãng, định dạng, tình trạng, người tải, ngày tải.
3. **Dữ liệu gốc** — bộ phận, loại thiết bị, loại tài liệu (mã tiền tố, màu, từ khoá nhận diện), nhà sản xuất.
4. **Chia theo bộ phận** — thiết bị thuộc bộ phận; người dùng chọn bộ phận làm việc trên topbar;
   tài liệu "Công khai" bộ phận khác xem được nhưng không sửa được.
5. **Giao diện thống nhất** — một bộ CSS / biểu tượng / bố cục cho mọi màn hình.
6. **Tải lên hàng loạt** — kéo thả nhiều file, tiến trình từng file, lưới khai báo, "Áp dụng cho tất cả / dòng đã chọn",
   **Lưu tất cả** một lần (dòng lỗi báo riêng, dòng đúng vẫn lưu).
7. **Nhiều định dạng** — PDF, Word, Excel, PowerPoint, ảnh, CAD (DWG/DXF/STEP/STL…), video, âm thanh, nén, văn bản,
   file PLC… (danh sách ở `config/edl.php`); chặn file thực thi.

### Tính năng nâng cao
- **Tìm kiếm toàn văn** trong nội dung PDF / DOCX / XLSX / PPTX / ODT / TXT / CSV, hiện đoạn trích + tô sáng từ khoá.
- **Tự điền từ tên file**: `MDV-01_SOP_Ve sinh may.pdf` → thiết bị, loại tài liệu, tiêu đề.
- **Phát hiện file trùng** bằng SHA-256 ngay khi tải lên.
- **Lịch sử cập nhật file**: thay file giữ lại bản cũ, tải / khôi phục bản bất kỳ.
- **Xem trước trực tuyến**: PDF, ảnh, video, âm thanh, văn bản, Word/PowerPoint (dạng văn bản), Excel (dạng bảng).
- **Xem gần đây, Tôi tải lên, Xem nhiều nhất**, **Bộ lọc đã lưu**, dạng danh sách / lưới.
- **Thao tác hàng loạt**: tải ZIP (giữ cấu trúc thiết bị/loại), đổi tình trạng, xoá.
- **Lịch sử đăng nhập**: toàn hệ thống (quản trị) và của chính mình — phiên đang mở, nhập sai bất thường.
- **Tài liệu hết hiệu lực** (khôi phục / xoá vĩnh viễn), **xuất Excel (CSV UTF-8)** danh sách tài liệu & nhật ký.
- **Nhãn QR thiết bị** để quét mở hồ sơ tại hiện trường; **nhân bản thiết bị**.
- **Mức bảo mật tài liệu**: Công khai / Nội bộ bộ phận / Hạn chế.

## Cài đặt

```bash
composer install
cp .env.example .env        # sửa DB_DATABASE=edl, DB_USERNAME, DB_PASSWORD
php artisan key:generate
php artisan migrate --seed  # quyền, vai trò, bộ phận, dữ liệu gốc, tài khoản admin
php artisan db:seed --class=DemoSeeder   # (tuỳ chọn) thiết bị + tài liệu + tài khoản mẫu
php artisan serve
```

Tài khoản ban đầu (đổi ngay khi đưa vào sử dụng thật):

| Tài khoản | Mật khẩu mặc định | Vai trò |
|---|---|---|
| `admin` | `EDL_ADMIN_PASSWORD` trong `.env`, mặc định `Admin@123` | Quản trị hệ thống (mọi bộ phận) |
| `truongcd` | `EDL_DEMO_PASSWORD`, mặc định `Demo@123` | Trưởng bộ phận @ CD |
| `nvcd` | như trên | Nhân viên quản lý tài liệu @ CD |
| `nvqc` | như trên | Nhân viên @ QC + Người xem @ SX |
| `xem` | như trên | Người xem @ mọi bộ phận |

Đặt `EDL_ADMIN_FORCE_CHANGE=true` để buộc admin đổi mật khẩu ở lần đăng nhập đầu.

### Giới hạn tải lên (server LEMP)
- `config/edl.php` → `max_file_kb` (mặc định 200 MB/file, chỉnh qua `EDL_MAX_FILE_KB`).
- `php.ini`: `upload_max_filesize` và `post_max_size` ≥ giới hạn trên.
- Nginx: `client_max_body_size 210M;`

### Lệnh bảo trì
```bash
php artisan edl:clean-temp     # dọn file tạm tải lên quá 24h (đã lên lịch 02:00 hằng ngày)
php artisan edl:reindex        # đánh chỉ mục toàn văn cho tài liệu chưa có (--all: đánh lại tất cả)
php artisan schedule:work      # hoặc cron: * * * * * php artisan schedule:run
```
