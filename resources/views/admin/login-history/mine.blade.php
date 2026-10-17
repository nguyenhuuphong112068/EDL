@extends('layouts.app')
@php use App\Services\Security\LoginHistory; use Illuminate\Support\Carbon; @endphp
@section('title', 'Lịch sử đăng nhập của tôi')
@section('page-title', 'Lịch sử đăng nhập của tôi')

@section('content')
    <div class="page-header">
        <div>
            <h2>Lịch sử đăng nhập của tôi</h2>
            <p>Kiểm tra các lần đăng nhập vào tài khoản <span class="mono fw-700">{{ auth()->user()->userName }}</span>.
                Nếu thấy lượt đăng nhập lạ, hãy <a href="{{ route('password.change') }}">đổi mật khẩu</a> ngay và báo quản trị hệ thống.</p>
        </div>
    </div>

    @if ($failed30 > 0)
        <div class="flash-alert warning">{!! icon('alert') !!}<div>Có <b>{{ $failed30 }}</b> lần đăng nhập sai mật khẩu vào tài khoản của bạn trong 30 ngày qua.</div></div>
    @endif

    <div class="card">
        <div class="table-wrap">
            <table>
                <thead><tr><th>Thời điểm</th><th>Kết quả</th><th>Địa chỉ IP</th><th>Trình duyệt / HĐH</th><th>Kết thúc phiên</th></tr></thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr @if ((int) $log->id === (int) $currentId) style="background:#eff6ff" @endif>
                            <td class="nowrap">{{ Carbon::parse($log->login_at)->format('d/m/Y H:i:s') }}
                                @if ((int) $log->id === (int) $currentId)<div><span class="badge badge-primary">Phiên hiện tại</span></div>@endif
                            </td>
                            <td>
                                <span class="badge {{ LoginHistory::STATUSES[$log->status]['badge'] ?? 'badge-secondary' }}">{{ LoginHistory::STATUSES[$log->status]['label'] ?? $log->status }}</span>
                                @if ($log->reason)<div class="cell-sub">{{ $log->reason }}</div>@endif
                            </td>
                            <td class="mono text-sm">{{ $log->ip_address }}</td>
                            <td class="text-sm">{{ $log->browser ?? '—' }}<div class="cell-sub">{{ $log->platform }}</div></td>
                            <td class="text-sm">@include('admin.login-history._session', ['log' => $log])</td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><div class="empty-state">{!! icon('key') !!}<p>Chưa có dữ liệu.</p></div></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $logs->links() }}
    </div>
@endsection
