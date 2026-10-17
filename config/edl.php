<?php

/*
 * EDL — Equipment Document Library (Thư viện tài liệu thiết bị)
 *
 * Cấu hình nghiệp vụ dùng chung: chính sách bảo mật tài khoản (theo WMS), định dạng file
 * được phép lưu, cách xem trước từng định dạng và giới hạn tải lên.
 */

return [

    'name'      => 'EDL',
    'full_name' => 'Equipment Document Library',
    'vi_name'   => 'Thư viện tài liệu thiết bị',
    'version'   => '1.1.8',

    /*
    |--------------------------------------------------------------------------
    | Bảo mật tài khoản (tham khảo WMS — chuẩn 21 CFR Part 11 §11.300, §11.10(d))
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Khoá tài khoản sau số lần nhập sai liên tiếp này
        'max_failed_attempts'    => 5,
        // Thời gian khoá (phút), hết hạn thì tự mở
        'lock_minutes'           => 15,
        // Mật khẩu có hạn dùng bấy nhiêu ngày kể từ lần đổi
        'password_validity_days' => 90,
        // Không cho trùng với bấy nhiêu mật khẩu gần nhất (kể cả mật khẩu hiện tại)
        'password_history_depth' => 5,
        // Độ dài tối thiểu + bắt buộc chữ hoa, chữ thường, số, ký tự đặc biệt
        'password_min_length'    => 8,
        // Tự đăng xuất sau bấy nhiêu phút không thao tác
        'idle_timeout_minutes'   => 15,
    ],

    /*
    |--------------------------------------------------------------------------
    | Lưu trữ file
    |--------------------------------------------------------------------------
    | File tài liệu nằm trên disk `local` (storage/app/private) — KHÔNG public ra web.
    | Mọi lượt xem / tải đều đi qua controller để kiểm tra quyền và ghi nhật ký.
    */
    'storage' => [
        'disk'            => env('EDL_STORAGE_DISK', 'local'),
        'root'            => 'documents',
        'tmp'             => 'tmp-uploads',
        // Dung lượng tối đa mỗi file (KB) — nhớ chỉnh upload_max_filesize/post_max_size của PHP tương ứng
        'max_file_kb'     => (int) env('EDL_MAX_FILE_KB', 204800),
        // Số file tối đa trong một lượt tải lên hàng loạt
        'max_batch_files' => 100,
        // File tạm tải lên mà không lưu thì dọn sau bấy nhiêu giờ
        'tmp_ttl_hours'   => 24,
        // Giới hạn độ dài nội dung trích xuất để tìm kiếm toàn văn (ký tự)
        'max_index_chars' => 300000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Định dạng file được phép
    |--------------------------------------------------------------------------
    | group   : nhóm hiển thị / lọc
    | preview : pdf | image | video | audio | text | docx | xlsx | none
    | Định dạng có thể chứa mã chạy trên trình duyệt (html, svg…) chỉ cho TẢI XUỐNG.
    */
    'file_groups' => [
        'pdf'     => ['label' => 'PDF',              'color' => '#dc2626'],
        'word'    => ['label' => 'Word / Văn bản',   'color' => '#2563eb'],
        'excel'   => ['label' => 'Excel / Bảng tính', 'color' => '#16a34a'],
        'slide'   => ['label' => 'Trình chiếu',      'color' => '#ea580c'],
        'image'   => ['label' => 'Hình ảnh',         'color' => '#9333ea'],
        'cad'     => ['label' => 'Bản vẽ CAD / 3D',  'color' => '#0891b2'],
        'video'   => ['label' => 'Video',            'color' => '#db2777'],
        'audio'   => ['label' => 'Âm thanh',         'color' => '#ca8a04'],
        'text'    => ['label' => 'Văn bản thô / dữ liệu', 'color' => '#475569'],
        'archive' => ['label' => 'File nén',         'color' => '#78716c'],
        'other'   => ['label' => 'Khác',             'color' => '#64748b'],
    ],

    'file_types' => [
        // PDF
        'pdf'  => ['group' => 'pdf',   'preview' => 'pdf'],
        // Word / văn bản
        'doc'  => ['group' => 'word',  'preview' => 'none'],
        'docx' => ['group' => 'word',  'preview' => 'docx'],
        'docm' => ['group' => 'word',  'preview' => 'docx'],
        'odt'  => ['group' => 'word',  'preview' => 'docx'],
        'rtf'  => ['group' => 'word',  'preview' => 'none'],
        // Excel
        'xls'  => ['group' => 'excel', 'preview' => 'none'],
        'xlsx' => ['group' => 'excel', 'preview' => 'xlsx'],
        'xlsm' => ['group' => 'excel', 'preview' => 'xlsx'],
        'ods'  => ['group' => 'excel', 'preview' => 'none'],
        'csv'  => ['group' => 'excel', 'preview' => 'text'],
        // Trình chiếu
        'ppt'  => ['group' => 'slide', 'preview' => 'none'],
        'pptx' => ['group' => 'slide', 'preview' => 'docx'],
        'odp'  => ['group' => 'slide', 'preview' => 'none'],
        // Hình ảnh
        'jpg'  => ['group' => 'image', 'preview' => 'image'],
        'jpeg' => ['group' => 'image', 'preview' => 'image'],
        'png'  => ['group' => 'image', 'preview' => 'image'],
        'gif'  => ['group' => 'image', 'preview' => 'image'],
        'bmp'  => ['group' => 'image', 'preview' => 'image'],
        'webp' => ['group' => 'image', 'preview' => 'image'],
        'tif'  => ['group' => 'image', 'preview' => 'none'],
        'tiff' => ['group' => 'image', 'preview' => 'none'],
        'svg'  => ['group' => 'image', 'preview' => 'none'],
        'heic' => ['group' => 'image', 'preview' => 'none'],
        // CAD / 3D
        'dwg'  => ['group' => 'cad',   'preview' => 'none'],
        'dxf'  => ['group' => 'cad',   'preview' => 'none'],
        'dwf'  => ['group' => 'cad',   'preview' => 'none'],
        'step' => ['group' => 'cad',   'preview' => 'none'],
        'stp'  => ['group' => 'cad',   'preview' => 'none'],
        'igs'  => ['group' => 'cad',   'preview' => 'none'],
        'iges' => ['group' => 'cad',   'preview' => 'none'],
        'stl'  => ['group' => 'cad',   'preview' => 'none'],
        'sldprt' => ['group' => 'cad', 'preview' => 'none'],
        'sldasm' => ['group' => 'cad', 'preview' => 'none'],
        // Video
        'mp4'  => ['group' => 'video', 'preview' => 'video'],
        'webm' => ['group' => 'video', 'preview' => 'video'],
        'ogv'  => ['group' => 'video', 'preview' => 'video'],
        'mov'  => ['group' => 'video', 'preview' => 'none'],
        'avi'  => ['group' => 'video', 'preview' => 'none'],
        'mkv'  => ['group' => 'video', 'preview' => 'none'],
        'wmv'  => ['group' => 'video', 'preview' => 'none'],
        // Âm thanh
        'mp3'  => ['group' => 'audio', 'preview' => 'audio'],
        'wav'  => ['group' => 'audio', 'preview' => 'audio'],
        'ogg'  => ['group' => 'audio', 'preview' => 'audio'],
        'm4a'  => ['group' => 'audio', 'preview' => 'audio'],
        // Văn bản thô / dữ liệu
        'txt'  => ['group' => 'text',  'preview' => 'text'],
        'log'  => ['group' => 'text',  'preview' => 'text'],
        'md'   => ['group' => 'text',  'preview' => 'text'],
        'json' => ['group' => 'text',  'preview' => 'text'],
        'xml'  => ['group' => 'text',  'preview' => 'text'],
        'ini'  => ['group' => 'text',  'preview' => 'text'],
        'cfg'  => ['group' => 'text',  'preview' => 'text'],
        'html' => ['group' => 'text',  'preview' => 'none'],
        'htm'  => ['group' => 'text',  'preview' => 'none'],
        // Nén
        'zip'  => ['group' => 'archive', 'preview' => 'none'],
        'rar'  => ['group' => 'archive', 'preview' => 'none'],
        '7z'   => ['group' => 'archive', 'preview' => 'none'],
        'tar'  => ['group' => 'archive', 'preview' => 'none'],
        'gz'   => ['group' => 'archive', 'preview' => 'none'],
        // Khác: chương trình PLC, backup cấu hình thiết bị…
        'bak'  => ['group' => 'other', 'preview' => 'none'],
        'bin'  => ['group' => 'other', 'preview' => 'none'],
        'hex'  => ['group' => 'other', 'preview' => 'text'],
        'ap11' => ['group' => 'other', 'preview' => 'none'],
        'acd'  => ['group' => 'other', 'preview' => 'none'],
        'gsd'  => ['group' => 'other', 'preview' => 'text'],
        'eds'  => ['group' => 'other', 'preview' => 'text'],
    ],

    // Không bao giờ nhận các đuôi này dù có khai trong file_types (chặn mã thực thi)
    'blocked_extensions' => [
        'exe', 'msi', 'bat', 'cmd', 'com', 'scr', 'pif', 'vbs', 'vbe', 'js', 'jse', 'wsf', 'wsh',
        'ps1', 'psm1', 'sh', 'php', 'phtml', 'phar', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py', 'dll', 'jar', 'hta',
    ],

    // Tình trạng tài liệu
    'document_statuses' => [
        'active'   => ['label' => 'Hiệu lực',     'badge' => 'badge-success'],
        'draft'    => ['label' => 'Bản nháp',     'badge' => 'badge-warning'],
    ],

    // Tình trạng thiết bị
    'device_statuses' => [
        'active'      => ['label' => 'Đang sử dụng', 'badge' => 'badge-success'],
        'maintenance' => ['label' => 'Bảo trì / sửa chữa', 'badge' => 'badge-warning'],
        'standby'     => ['label' => 'Dự phòng',     'badge' => 'badge-info'],
        'inactive'    => ['label' => 'Ngừng sử dụng', 'badge' => 'badge-secondary'],
        'liquidated'  => ['label' => 'Đã thanh lý',  'badge' => 'badge-danger'],
    ],
];
