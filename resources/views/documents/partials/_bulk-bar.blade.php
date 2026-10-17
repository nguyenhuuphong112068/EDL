{{-- Thanh thao tác hàng loạt — hiện khi có dòng được chọn (EDL.initBulk) --}}
<form method="POST" action="{{ route('documents.bulk') }}" class="bulk-bar" id="bulk-form">
    @csrf
    <span class="bulk-count">Đã chọn 0</span>
    <input type="hidden" name="action" value="">
    @if (user_can('documents.download'))
        <button type="submit" class="btn btn-sm btn-primary" data-bulk-action="zip">{!! icon('archive') !!} Tải ZIP</button>
    @endif
    @if (user_can('documents.update'))
        <select name="status" class="form-select" style="width:auto;padding:5px 8px;font-size:.8rem" data-bulk-status>
            <option value="">Đổi tình trạng…</option>
            @foreach (config('edl.document_statuses') as $key => $s)
                <option value="{{ $key }}">{{ $s['label'] }}</option>
            @endforeach
        </select>
    @endif
    @if (user_can('documents.delete'))
        <button type="submit" class="btn btn-sm btn-outline-danger" data-bulk-action="trash" data-confirm="Chuyển các tài liệu đã chọn sang hết hiệu lực?">{!! icon('archive') !!} Hết hiệu lực</button>
    @endif
</form>

@push('scripts')
    <script>
        (function () {
            var form = document.getElementById('bulk-form');
            if (!form) { return; }
            var actionInput = form.querySelector('input[name=action]');
            var addIds = function () {
                form.querySelectorAll('input[name="ids[]"]').forEach(function (i) { i.remove(); });
                EDL.selectedIds().forEach(function (id) {
                    var i = document.createElement('input'); i.type = 'hidden'; i.name = 'ids[]'; i.value = id; form.appendChild(i);
                });
            };
            form.querySelectorAll('[data-bulk-action]').forEach(function (btn) {
                btn.addEventListener('click', function () { actionInput.value = btn.dataset.bulkAction; addIds(); });
            });
            var status = form.querySelector('[data-bulk-status]');
            if (status) {
                status.addEventListener('change', function () {
                    if (!status.value) { return; }
                    if (!confirm('Đổi tình trạng các tài liệu đã chọn?')) { status.value = ''; return; }
                    actionInput.value = 'status'; addIds(); form.submit();
                });
            }
        })();
    </script>
@endpush
