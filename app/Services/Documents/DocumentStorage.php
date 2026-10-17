<?php

namespace App\Services\Documents;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * NƠI LƯU FILE — tổ chức theo đối tượng như REMS (documents/supplier_{id}/{folder}),
 * ở EDL là theo thiết bị:
 *
 *   storage/app/private/documents/device_{id}/{MÃ_LOẠI}/{uuid}.{ext}
 *   storage/app/private/tmp-uploads/user_{id}/{token}.{ext}   ← file tạm khi tải lên hàng loạt
 *
 * Khác REMS: dùng disk riêng tư (không nằm trong public/) — xem / tải bắt buộc đi qua
 * controller để kiểm tra quyền và ghi nhật ký. Tên file trên đĩa là ngẫu nhiên, tên gốc lưu DB.
 */
class DocumentStorage
{
    public function disk()
    {
        return Storage::disk(config('edl.storage.disk'));
    }

    public function deviceDirectory(int $deviceId, ?string $typeCode = null): string
    {
        $type = $typeCode ? Str::upper(Str::slug($typeCode, '_')) : 'KHAC';

        return config('edl.storage.root') . "/device_{$deviceId}/{$type}";
    }

    /* ---------------- File tạm (tải lên hàng loạt) ---------------- */

    public function tmpDirectory(int $userId): string
    {
        return config('edl.storage.tmp') . "/user_{$userId}";
    }

    /** Lưu file tạm, trả về token (tên file) — chỉ chính user đó dùng lại được. */
    public function storeTemp(UploadedFile $file, int $userId): array
    {
        $ext   = strtolower($file->getClientOriginalExtension());
        $token = Str::random(40);
        $name  = $token . ($ext ? ".{$ext}" : '');

        $this->disk()->putFileAs($this->tmpDirectory($userId), $file, $name);

        $meta = [
            'token'         => $token,
            'original_name' => $this->cleanName($file->getClientOriginalName()),
            'extension'     => $ext,
            'mime_type'     => $file->getMimeType() ?: $file->getClientMimeType(),
            'size'          => $file->getSize(),
            'checksum'      => hash_file('sha256', $file->getRealPath()),
            'uploaded_at'   => now()->toIso8601String(),
        ];

        $this->disk()->put($this->tmpDirectory($userId) . "/{$token}.json", json_encode($meta, JSON_UNESCAPED_UNICODE));

        return $meta;
    }

    public function tempMeta(int $userId, string $token): ?array
    {
        if (! preg_match('/^[A-Za-z0-9]{40}$/', $token)) {
            return null;
        }

        $json = $this->disk()->get($this->tmpDirectory($userId) . "/{$token}.json");

        return $json ? json_decode($json, true) : null;
    }

    public function tempPath(int $userId, array $meta): string
    {
        return $this->tmpDirectory($userId) . '/' . $meta['token'] . ($meta['extension'] ? ".{$meta['extension']}" : '');
    }

    public function discardTemp(int $userId, string $token): void
    {
        if ($meta = $this->tempMeta($userId, $token)) {
            $this->disk()->delete([$this->tempPath($userId, $meta), $this->tmpDirectory($userId) . "/{$token}.json"]);
        }
    }

    /** Chuyển file tạm vào thư mục thiết bị — trả về đường dẫn lưu chính thức. */
    public function promoteTemp(int $userId, array $meta, int $deviceId, ?string $typeCode): string
    {
        $from = $this->tempPath($userId, $meta);
        $to   = $this->deviceDirectory($deviceId, $typeCode) . '/' . Str::uuid() . ($meta['extension'] ? ".{$meta['extension']}" : '');

        if (! $this->disk()->exists($from)) {
            throw new RuntimeException('File tạm không còn tồn tại (có thể đã quá hạn). Vui lòng tải lại file.');
        }

        $this->disk()->move($from, $to);
        $this->disk()->delete($this->tmpDirectory($userId) . "/{$meta['token']}.json");

        return $to;
    }

    /** Lưu thẳng một file tải lên (thay file / phiên bản mới). */
    public function storeUploaded(UploadedFile $file, int $deviceId, ?string $typeCode): string
    {
        $ext = strtolower($file->getClientOriginalExtension());

        return $this->disk()->putFileAs(
            $this->deviceDirectory($deviceId, $typeCode),
            $file,
            Str::uuid() . ($ext ? ".{$ext}" : '')
        );
    }

    public function absolutePath(string $path): string
    {
        return $this->disk()->path($path);
    }

    public function exists(?string $path): bool
    {
        return $path && $this->disk()->exists($path);
    }

    public function delete(?string $path): void
    {
        if ($path) {
            $this->disk()->delete($path);
        }
    }

    /** Dọn file tạm quá hạn (lệnh edl:clean-temp chạy hằng ngày). */
    public function cleanExpiredTemp(): int
    {
        $limit   = now()->subHours(config('edl.storage.tmp_ttl_hours'))->getTimestamp();
        $removed = 0;

        foreach ($this->disk()->allFiles(config('edl.storage.tmp')) as $file) {
            if ($this->disk()->lastModified($file) < $limit) {
                $this->disk()->delete($file);
                $removed++;
            }
        }

        return $removed;
    }

    /** Bỏ ký tự điều khiển / đường dẫn khỏi tên file gốc. */
    public function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        return mb_substr(trim($name), 0, 250) ?: 'file';
    }
}
