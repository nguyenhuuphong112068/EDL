<?php

namespace App\Services\Documents;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * TỰ ĐIỀN THÔNG TIN TỪ TÊN FILE — để tải hàng chục file mà không phải gõ tay từng dòng.
 *
 * Từ "MAY-001_HDSD_Huong dan van hanh.pdf" đoán được:
 *  - thiết bị: tên file chứa mã thiết bị (MAY-001) — chỉ trong phạm vi bộ phận đang làm việc;
 *  - loại tài liệu: mã loại hoặc từ khoá khai trong Dữ liệu gốc (HDSD, "huong dan"…);
 *  - tiêu đề: tên file đã bỏ mã thiết bị, mã loại tài liệu, ký tự nối.
 * Người dùng vẫn sửa lại được mọi giá trị trước khi lưu.
 */
class SmartFill
{
    private ?array $devices = null;
    private ?array $types   = null;

    public function suggest(string $filename, ?int $preferDeviceId = null): array
    {
        $base  = pathinfo($filename, PATHINFO_FILENAME);
        $ascii = Str::of($base)->ascii()->lower()->toString();
        $norm  = preg_replace('/[^a-z0-9]+/', ' ', $ascii) ?? $ascii;

        $device  = $preferDeviceId ? null : $this->detectDevice($ascii);
        $type    = $this->detectType($norm);

        $title = $base;
        if ($device) {
            $title = preg_replace('/' . preg_quote($device['code'], '/') . '/i', ' ', $title) ?? $title;
        }
        if ($type && preg_match('/(^|[\s_\-.])' . preg_quote($type['code'], '/') . '([\s_\-.]|$)/i', $title)) {
            $title = preg_replace('/(^|[\s_\-.])' . preg_quote($type['code'], '/') . '(?=[\s_\-.]|$)/i', ' ', $title) ?? $title;
        }

        $title = trim(preg_replace('/[\s_\-.]+/u', ' ', $title) ?? $title);
        $title = $title === '' ? $base : $title;

        return [
            'title'            => Str::ucfirst($title),
            'device_id'        => $preferDeviceId ?? ($device['id'] ?? null),
            'device_detected'  => $device !== null,
            'document_type_id' => $type['id'] ?? null,
            'type_detected'    => $type !== null,
        ];
    }

    private function detectDevice(string $ascii): ?array
    {
        $this->devices ??= DB::table('devices')
            ->whereNull('deleted_at')
            ->when(scope_department_ids() !== null, fn ($q) => $q->whereIn('department_id', scope_department_ids()))
            ->get(['id', 'code'])
            ->map(fn ($d) => ['id' => (int) $d->id, 'code' => $d->code, 'key' => Str::of($d->code)->ascii()->lower()->toString()])
            // Mã dài khớp trước để "MAY-0012" không bị nhận nhầm thành "MAY-001"
            ->sortByDesc(fn ($d) => mb_strlen($d['key']))
            ->values()
            ->all();

        foreach ($this->devices as $device) {
            if ($device['key'] !== '' && preg_match('/(^|[^a-z0-9])' . preg_quote($device['key'], '/') . '([^a-z0-9]|$)/', $ascii)) {
                return $device;
            }
        }

        return null;
    }

    private function detectType(string $norm): ?array
    {
        $this->types ??= DB::table('document_types')->where('is_active', true)->orderBy('sort_order')->get()
            ->map(function ($t) {
                $keywords = collect(explode(',', (string) $t->keywords))->push($t->code)
                    ->map(fn ($k) => trim(preg_replace('/[^a-z0-9]+/', ' ', Str::of($k)->ascii()->lower()->toString()) ?? ''))
                    ->filter(fn ($k) => mb_strlen($k) >= 2)
                    ->sortByDesc(fn ($k) => mb_strlen($k))
                    ->values()->all();

                return ['id' => (int) $t->id, 'code' => $t->code, 'keywords' => $keywords];
            })->all();

        $best = null;
        $bestLen = 0;
        $haystack = " {$norm} ";

        foreach ($this->types as $type) {
            foreach ($type['keywords'] as $kw) {
                if (str_contains($haystack, " {$kw} ") && mb_strlen($kw) > $bestLen) {
                    $best    = $type;
                    $bestLen = mb_strlen($kw);
                }
            }
        }

        return $best;
    }
}
