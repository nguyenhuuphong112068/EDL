<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/** Dữ liệu gốc ban đầu — chỉ thêm mục còn thiếu, không ghi đè chỉnh sửa của người dùng. */
class MasterDataSeeder extends Seeder
{
    public function run(): void
    {
        $now = ['created_at' => now(), 'updated_at' => now()];

        $departments = [
            ['CD', 'Phòng Cơ điện', 1], ['QA', 'Phòng Đảm bảo chất lượng', 2], ['QC', 'Phòng Kiểm nghiệm', 3],
            ['SX', 'Xưởng Sản xuất', 4], ['KHO', 'Kho vận', 5],
        ];
        foreach ($departments as [$code, $name, $order]) {
            DB::table('departments')->insertOrIgnore(['code' => $code, 'name' => $name, 'sort_order' => $order, 'is_active' => true] + $now);
        }

        // [mã, tên, màu, từ khoá nhận diện]
        $documentTypes = [
            ['HDSD', 'Hướng dẫn sử dụng / Manual', '#2563eb', 'manual, user manual, huong dan su dung, operation manual, hdvh, huong dan van hanh'],
            ['SOP', 'Quy trình thao tác chuẩn (SOP)', '#7c3aed', 'sop, quy trinh, quy trinh thao tac'],
            ['BV', 'Bản vẽ kỹ thuật', '#0891b2', 'ban ve, drawing, dwg, layout, so do, p&id, schematic'],
            ['HC', 'Giấy chứng nhận hiệu chuẩn', '#dc2626', 'hieu chuan, calibration, certificate of calibration, cal cert'],
            ['KD', 'Giấy chứng nhận kiểm định', '#ea580c', 'kiem dinh, inspection certificate, kiem dinh an toan'],
            ['TD', 'Hồ sơ thẩm định (IQ/OQ/PQ)', '#16a34a', 'tham dinh, validation, qualification, iq, oq, pq, dq, fat, sat'],
            ['BT', 'Hồ sơ bảo trì / bảo dưỡng', '#ca8a04', 'bao tri, bao duong, maintenance, pm, sua chua, repair'],
            ['CO', 'Chứng từ CO / CQ / mua hàng', '#64748b', 'co, cq, invoice, hoa don, packing list, hop dong, contract'],
            ['TS', 'Thông số kỹ thuật / Datasheet', '#0d9488', 'datasheet, specification, spec, thong so, catalogue, catalog, brochure'],
            ['PL', 'Chương trình PLC / phần mềm / backup', '#9333ea', 'plc, program, backup, firmware, software, hmi, scada'],
            ['AT', 'Hướng dẫn an toàn', '#be123c', 'an toan, safety, msds, sds'],
        ];
        $order = 0;
        foreach ($documentTypes as [$code, $name, $color, $keywords]) {
            DB::table('document_types')->insertOrIgnore([
                'code' => $code, 'name' => $name, 'color' => $color, 'keywords' => $keywords, 'sort_order' => ++$order, 'is_active' => true,
            ] + $now);
        }
    }
}
