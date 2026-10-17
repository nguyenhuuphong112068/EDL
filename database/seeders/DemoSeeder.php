<?php

namespace Database\Seeders;

use App\Services\Documents\DocumentService;
use App\Services\Documents\DocumentStorage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use ZipArchive;

/**
 * DỮ LIỆU DÙNG THỬ — thiết bị, tài khoản mẫu theo từng vai trò / bộ phận, tài liệu mẫu
 * (PDF, Word, Excel, CSV, TXT, ảnh PNG) sinh ngay trong seeder để thử xem trước và tìm toàn văn.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Tài khoản mẫu (mật khẩu: biến môi trường EDL_DEMO_PASSWORD, mặc định ghi trong README):
 *   truongcd  — Trưởng bộ phận @ Cơ điện
 *   nvcd      — Nhân viên quản lý tài liệu @ Cơ điện
 *   nvqc      — Nhân viên quản lý tài liệu @ Kiểm nghiệm, Người xem @ Sản xuất
 *   xem       — Người xem @ mọi bộ phận
 */
class DemoSeeder extends Seeder
{
    public function run(DocumentStorage $storage, DocumentService $service): void
    {
        $dept = fn ($code) => DB::table('departments')->where('code', $code)->value('id');
        $role = fn ($slug) => DB::table('roles')->where('slug', $slug)->value('id');

        /* ---------- Tài khoản mẫu ---------- */
        $password = Hash::make(env('EDL_DEMO_PASSWORD') ?: 'Demo@123');
        $users = [
            ['truongcd', 'Nguyễn Văn Cường', 'Trưởng phòng Cơ điện', 'CD', [['manager', 'CD']]],
            ['nvcd', 'Trần Thị Duyên', 'Kỹ sư cơ điện', 'CD', [['editor', 'CD']]],
            ['nvqc', 'Lê Minh Quân', 'Kiểm nghiệm viên', 'QC', [['editor', 'QC'], ['viewer', 'SX']]],
            ['xem', 'Phạm Thu Hà', 'Nhân viên', 'SX', [['viewer', null]]],
        ];
        foreach ($users as [$userName, $name, $position, $deptCode, $roles]) {
            if (DB::table('users')->where('userName', $userName)->exists()) {
                continue;
            }
            $uid = DB::table('users')->insertGetId([
                'name' => $name, 'userName' => $userName, 'position' => $position, 'department_id' => $dept($deptCode),
                'password' => $password, 'is_active' => true, 'must_change_password' => false,
                'password_expires_at' => today()->addDays(90), 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('password_histories')->insert(['user_id' => $uid, 'password_hash' => $password, 'created_by' => 'seeder', 'created_at' => now()]);
            foreach ($roles as [$slug, $d]) {
                DB::table('user_role')->insert(['user_id' => $uid, 'role_id' => $role($slug), 'department_id' => $d ? $dept($d) : null]);
            }
        }

        /* ---------- Thiết bị ---------- */
        // [mã, tên, bộ phận lắp đặt]
        $devices = [
            ['MDV-01', 'Máy dập viên quay tròn Fette 1200i', 'SX'],
            ['MEV-01', 'Máy ép vỉ Bosch TPU 250', 'SX'],
            ['TBT-02', 'Thiết bị trộn cao tốc 300L', 'SX'],
            ['HPLC-01', 'Hệ thống HPLC Agilent 1260 Infinity II', 'QC'],
            ['UV-01', 'Máy quang phổ UV-Vis Shimadzu UV-1900i', 'QC'],
            ['CAN-05', 'Cân phân tích Mettler XPR205', 'QC'],
            ['AHU-01', 'Tủ xử lý không khí AHU-01 khu sản xuất', 'CD'],
            ['MNK-01', 'Máy nén khí trục vít Atlas Copco GA37', 'CD'],
            ['PLC-AHU', 'Tủ điều khiển PLC hệ thống HVAC', 'CD'],
        ];
        $admin = DB::table('users')->where('userName', 'admin')->value('id');
        foreach ($devices as [$code, $name, $d]) {
            DB::table('devices')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'department_id' => $dept($d), 'status' => 'active',
                'created_by' => $admin, 'updated_by' => $admin, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        if (DB::table('documents')->exists()) {
            return;
        }

        /* ---------- Tài liệu mẫu ---------- */
        // [thiết bị, loại, tiêu đề, định dạng, nội dung]
        $docs = [
            ['MDV-01', 'HDSD', 'Hướng dẫn vận hành máy dập viên Fette 1200i', 'pdf',
                "Fette 1200i Operating Manual\nChapter 1 Safety instructions\nChapter 2 Machine start-up and punch installation\nChapter 3 Tablet weight adjustment and compression force"],
            ['MDV-01', 'SOP', 'SOP vệ sinh máy dập viên', 'docx',
                "QUY TRÌNH VỆ SINH MÁY DẬP VIÊN\n1. Mục đích: đảm bảo máy sạch, không nhiễm chéo giữa các lô.\n2. Phạm vi: áp dụng cho máy dập viên Fette 1200i.\n3. Các bước: tháo chày cối, rửa bằng nước tinh khiết, lau cồn 70%."],
            ['MDV-01', 'TD', 'Báo cáo thẩm định vận hành OQ máy dập viên', 'pdf',
                "Operational Qualification Report MDV-01\nTest 1 speed range verification PASSED\nTest 2 compression force accuracy PASSED\nConclusion: equipment qualified"],
            ['MEV-01', 'BT', 'Nhật ký bảo trì máy ép vỉ 2025', 'xlsx',
                [['Ngày', 'Hạng mục', 'Người thực hiện', 'Kết quả'], ['05/01/2025', 'Thay dao cắt vỉ', 'Trần Thị Duyên', 'Đạt'], ['12/03/2025', 'Kiểm tra nhiệt độ khuôn hàn', 'Nguyễn Văn Cường', 'Đạt'], ['20/06/2025', 'Bôi trơn xích tải', 'Trần Thị Duyên', 'Đạt']]],
            ['MEV-01', 'BV', 'Sơ đồ bố trí máy ép vỉ', 'png', null],
            ['HPLC-01', 'HC', 'Giấy chứng nhận hiệu chuẩn HPLC 1260', 'pdf',
                "Certificate of Calibration\nInstrument: Agilent 1260 Infinity II\nSerial: DEAB912345\nFlow rate accuracy within 1 percent\nWavelength accuracy within 1 nm"],
            ['HPLC-01', 'SOP', 'SOP vận hành hệ thống HPLC', 'docx',
                "QUY TRÌNH VẬN HÀNH HỆ THỐNG HPLC\nChuẩn bị pha động, khử khí bằng siêu âm 15 phút.\nCân bằng cột tối thiểu 30 phút trước khi tiêm mẫu.\nKiểm tra tính phù hợp hệ thống (system suitability)."],
            ['UV-01', 'HC', 'Giấy chứng nhận hiệu chuẩn UV-1900i', 'pdf',
                "Calibration certificate UV-1900i\nPhotometric accuracy verified with potassium dichromate\nStray light test passed"],
            ['CAN-05', 'KD', 'Giấy chứng nhận kiểm định cân XPR205', 'pdf',
                "Inspection certificate analytical balance XPR205\nCapacity 220 g readability 0.01 mg\nResult: meets requirements"],
            ['AHU-01', 'BV', 'Bản vẽ sơ đồ đường ống gió AHU-01', 'txt',
                "SO DO DUONG ONG GIO AHU-01\nCấp gió: phòng pha chế, phòng dập viên, phòng ép vỉ\nHồi gió: hành lang sạch cấp D\nLọc HEPA H14 tại miệng thổi"],
            ['AHU-01', 'TS', 'Thông số kỹ thuật AHU Daikin', 'csv',
                "Thong so,Gia tri,Don vi\nLuu luong gio,15000,m3/h\nCong suat lanh,120,kW\nCap loc,G4/F8/H14,\nAp suat tinh,800,Pa"],
            ['MNK-01', 'BT', 'Kế hoạch bảo dưỡng máy nén khí GA37', 'txt',
                "KẾ HOẠCH BẢO DƯỠNG MÁY NÉN KHÍ GA37\n2000 giờ: thay lọc gió, lọc dầu\n4000 giờ: thay dầu Roto-Inject, kiểm tra van xả\n8000 giờ: đại tu cụm đầu nén"],
            ['PLC-AHU', 'PL', 'Backup chương trình PLC HVAC', 'txt',
                "PLC BACKUP S7-1500 HVAC\nOB1 main cycle\nFB10 AHU control PID temperature humidity\nDB20 setpoints: T=22C RH=55%"],
        ];

        foreach ($docs as $i => [$devCode, $typeCode, $title, $ext, $content]) {
            $device = DB::table('devices')->where('code', $devCode)->first();
            $type   = DB::table('document_types')->where('code', $typeCode)->first();
            $bytes  = $this->sampleFile($ext, $title, $content);
            $path   = $storage->deviceDirectory($device->id, $type->code) . '/' . Str::uuid() . ".{$ext}";
            $storage->disk()->put($path, $bytes);

            $uploader = DB::table('users')->where('department_id', $device->department_id)->value('id') ?? $admin;
            $docId = DB::table('documents')->insertGetId([
                'code' => $service->generateCode($device, $type), 'title' => $title, 'device_id' => $device->id,
                'document_type_id' => $type->id, 'revision_count' => 1,
                'status' => 'active',
                'file_path' => $path, 'original_filename' => Str::slug($devCode . ' ' . $title, '_') . ".{$ext}",
                'extension' => $ext, 'mime_type' => null, 'file_size' => strlen($bytes), 'checksum' => hash('sha256', $bytes),
                'view_count' => random_int(0, 40), 'download_count' => random_int(0, 15),
                'uploaded_by' => $uploader, 'updated_by' => $uploader,
                'created_at' => now()->subDays(30 - $i * 2), 'updated_at' => now()->subDays(30 - $i * 2),
            ]);

            DB::table('document_versions')->insert([
                'document_id' => $docId, 'revision' => 1, 'file_path' => $path,
                'original_filename' => Str::slug($devCode . ' ' . $title, '_') . ".{$ext}", 'extension' => $ext,
                'file_size' => strlen($bytes), 'checksum' => hash('sha256', $bytes), 'change_note' => 'Dữ liệu mẫu',
                'uploaded_by' => $uploader, 'created_at' => now(),
            ]);

            $service->index($docId);
        }
    }

    /* ---------- Sinh file mẫu ---------- */

    private function sampleFile(string $ext, string $title, $content): string
    {
        return match ($ext) {
            'pdf'  => $this->pdf($title, (string) $content),
            'docx' => $this->docx($title, (string) $content),
            'xlsx' => $this->xlsx($content),
            'png'  => $this->png($title),
            default => "\xEF\xBB\xBF" . $content,
        };
    }

    /** PDF tối giản hợp lệ (font Helvetica — nội dung không dấu). */
    private function pdf(string $title, string $content): string
    {
        $lines  = array_merge([Str::ascii($title), ''], explode("\n", Str::ascii($content)));
        $stream = "BT /F1 13 Tf 60 780 Td 16 TL\n";
        foreach ($lines as $line) {
            $stream .= '(' . str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $line) . ") Tj T*\n";
        }
        $stream .= 'ET';
        $compressed = gzcompress($stream);

        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            "<< /Length " . strlen($compressed) . " /Filter /FlateDecode >>\nstream\n{$compressed}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objects as $n => $obj) {
            $offsets[] = strlen($pdf);
            $pdf .= ($n + 1) . " 0 obj\n{$obj}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $o) {
            $pdf .= sprintf("%010d 00000 n \n", $o);
        }

        return $pdf . "trailer\n<< /Size " . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n{$xref}\n%%EOF";
    }

    private function docx(string $title, string $content): string
    {
        $p = fn ($text, $bold = false) => '<w:p>' . ($bold ? '<w:pPr><w:pStyle w:val="Heading1"/></w:pPr>' : '')
            . '<w:r>' . ($bold ? '<w:rPr><w:b/></w:rPr>' : '') . '<w:t xml:space="preserve">' . htmlspecialchars($text, ENT_XML1) . '</w:t></w:r></w:p>';

        $body = $p($title, true) . implode('', array_map(fn ($l) => $p($l), explode("\n", $content)));

        return $this->zip([
            '[Content_Types].xml' => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>',
            '_rels/.rels'         => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>',
            'word/document.xml'   => '<?xml version="1.0" encoding="UTF-8"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body>' . $body . '</w:body></w:document>',
        ]);
    }

    private function xlsx(array $rows): string
    {
        $strings = [];
        $sheet   = '';
        foreach ($rows as $r => $row) {
            $sheet .= '<row r="' . ($r + 1) . '">';
            foreach ($row as $c => $value) {
                $idx = array_search($value, $strings, true);
                if ($idx === false) {
                    $strings[] = $value;
                    $idx = count($strings) - 1;
                }
                $sheet .= '<c r="' . chr(65 + $c) . ($r + 1) . '" t="s"><v>' . $idx . '</v></c>';
            }
            $sheet .= '</row>';
        }

        $ss = implode('', array_map(fn ($s) => '<si><t>' . htmlspecialchars($s, ENT_XML1) . '</t></si>', $strings));

        return $this->zip([
            '[Content_Types].xml'        => '<?xml version="1.0" encoding="UTF-8"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/><Override PartName="/xl/sharedStrings.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sharedStrings+xml"/></Types>',
            '_rels/.rels'                => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>',
            'xl/workbook.xml'            => '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="Nhat ky" sheetId="1" r:id="rId1"/></sheets></workbook>',
            'xl/_rels/workbook.xml.rels' => '<?xml version="1.0" encoding="UTF-8"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/><Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/></Relationships>',
            'xl/worksheets/sheet1.xml'   => '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>' . $sheet . '</sheetData></worksheet>',
            'xl/sharedStrings.xml'       => '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">' . $ss . '</sst>',
        ]);
    }

    private function png(string $title): string
    {
        $img = imagecreatetruecolor(800, 480);
        imagefill($img, 0, 0, imagecolorallocate($img, 241, 245, 249));
        $blue = imagecolorallocate($img, 37, 99, 235);
        $dark = imagecolorallocate($img, 30, 41, 59);
        imagerectangle($img, 40, 80, 760, 440, $dark);
        foreach ([[80, 140, 300, 260], [340, 140, 520, 380], [560, 140, 720, 260]] as [$x1, $y1, $x2, $y2]) {
            imagefilledrectangle($img, $x1, $y1, $x2, $y2, $blue);
        }
        imagestring($img, 5, 40, 30, Str::ascii($title), $dark);
        ob_start();
        imagepng($img);
        imagedestroy($img);

        return (string) ob_get_clean();
    }

    private function zip(array $files): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'edl');
        $zip = new ZipArchive();
        $zip->open($tmp, ZipArchive::OVERWRITE);
        foreach ($files as $name => $content) {
            $zip->addFromString($name, $content);
        }
        $zip->close();
        $bytes = file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
