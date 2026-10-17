{{-- Cột "phiên": thời lượng + cách kết thúc. Biến: $log --}}
@php
    use App\Services\Security\LoginHistory;
    use Illuminate\Support\Carbon;

    $isOpen = $log->status === 'success' && ! $log->logout_at && $log->last_seen_at
        && Carbon::parse($log->last_seen_at)->gte(now()->subMinutes(config('edl.security.idle_timeout_minutes')));
    $end     = $log->logout_at ?? $log->last_seen_at;
    $minutes = $log->status === 'success' && $end ? (int) Carbon::parse($log->login_at)->diffInMinutes(Carbon::parse($end)) : null;
    $duration = $minutes === null ? null : ($minutes >= 60 ? intdiv($minutes, 60) . ' giờ ' . ($minutes % 60) . ' phút' : ($minutes < 1 ? 'dưới 1 phút' : $minutes . ' phút'));
@endphp
@if ($log->status !== 'success')
    <span class="text-muted">—</span>
@elseif ($isOpen)
    <span class="badge badge-success badge-dot">Đang mở</span>
    <div class="cell-sub">{{ $duration }} · thao tác cuối {{ Carbon::parse($log->last_seen_at)->format('H:i') }}</div>
@elseif ($log->logout_at)
    {{ Carbon::parse($log->logout_at)->format('d/m H:i') }}
    <div class="cell-sub">{{ LoginHistory::LOGOUT_TYPES[$log->logout_type] ?? '' }} · {{ $duration }}</div>
@else
    <span class="text-muted text-sm">Đóng trình duyệt</span>
    <div class="cell-sub">thao tác cuối {{ $log->last_seen_at ? Carbon::parse($log->last_seen_at)->format('d/m H:i') : '—' }}@if ($duration) · {{ $duration }}@endif</div>
@endif
