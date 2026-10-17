<?php

namespace App\Support;

/**
 * Tra cứu định dạng file theo config/edl.php: được phép lưu không, thuộc nhóm nào,
 * xem trước bằng cách nào, màu biểu tượng.
 */
class FileType
{
    public static function normalize(?string $extension): string
    {
        return strtolower(ltrim(trim((string) $extension), '.'));
    }

    public static function isAllowed(?string $extension): bool
    {
        $ext = self::normalize($extension);

        return $ext !== ''
            && ! in_array($ext, config('edl.blocked_extensions', []), true)
            && array_key_exists($ext, config('edl.file_types', []));
    }

    /** @return array<int, string> */
    public static function allowedExtensions(): array
    {
        return array_values(array_diff(
            array_keys(config('edl.file_types', [])),
            config('edl.blocked_extensions', [])
        ));
    }

    public static function group(?string $extension): string
    {
        return config('edl.file_types.' . self::normalize($extension) . '.group', 'other');
    }

    public static function groupLabel(string $group): string
    {
        return config("edl.file_groups.{$group}.label", 'Khác');
    }

    public static function color(?string $extension): string
    {
        return config('edl.file_groups.' . self::group($extension) . '.color', '#64748b');
    }

    public static function previewMode(?string $extension): string
    {
        return config('edl.file_types.' . self::normalize($extension) . '.preview', 'none');
    }

    /** @return array<int, string> đuôi file thuộc một nhóm — dùng cho bộ lọc "Định dạng". */
    public static function extensionsOfGroup(string $group): array
    {
        return collect(config('edl.file_types', []))
            ->filter(fn ($def) => $def['group'] === $group)
            ->keys()
            ->all();
    }

    /** MIME an toàn để trả về khi xem trực tuyến — chỉ cho các chế độ xem đã hỗ trợ. */
    public static function inlineMime(string $extension): ?string
    {
        return match (self::normalize($extension)) {
            'pdf'         => 'application/pdf',
            'jpg', 'jpeg' => 'image/jpeg',
            'png'         => 'image/png',
            'gif'         => 'image/gif',
            'bmp'         => 'image/bmp',
            'webp'        => 'image/webp',
            'mp4'         => 'video/mp4',
            'webm'        => 'video/webm',
            'ogv'         => 'video/ogg',
            'mp3'         => 'audio/mpeg',
            'wav'         => 'audio/wav',
            'ogg'         => 'audio/ogg',
            'm4a'         => 'audio/mp4',
            default       => null,
        };
    }

    public static function humanSize(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i     = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return ($i === 0 ? (int) $bytes : number_format($bytes, $bytes >= 100 ? 0 : 1, ',', '.')) . ' ' . $units[$i];
    }
}
