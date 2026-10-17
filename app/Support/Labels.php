<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** Nhãn / màu hiển thị cho các giá trị trạng thái — đọc từ config/edl.php. */
class Labels
{
    public static function documentStatus(?string $status): string
    {
        return config("edl.document_statuses.{$status}.label", (string) $status);
    }

    public static function documentStatusBadge(?string $status): string
    {
        return config("edl.document_statuses.{$status}.badge", 'badge-secondary');
    }

    public static function deviceStatus(?string $status): string
    {
        return config("edl.device_statuses.{$status}.label", (string) $status);
    }

    public static function deviceStatusBadge(?string $status): string
    {
        return config("edl.device_statuses.{$status}.badge", 'badge-secondary');
    }

    public static function daysUntil(?string $date): ?int
    {
        return $date ? (int) today()->diffInDays(Carbon::parse($date), false) : null;
    }

    public static function date(?string $date, string $format = 'd/m/Y'): string
    {
        return $date ? Carbon::parse($date)->format($format) : '—';
    }

    public static function datetime(?string $date): string
    {
        return $date ? Carbon::parse($date)->format('d/m/Y H:i') : '—';
    }

    public static function ago(?string $date): string
    {
        return $date ? Carbon::parse($date)->locale('vi')->diffForHumans() : '—';
    }
}
