@extends('layouts.app')
@section('title', 'Import Review')

@section('content')
@php($activeStatuses = ['uploaded','validating','queued','processing','rolling_back'])
<section class="content-header">
    <h1>Import Review <small>{{ $definition['label'] }} · {{ $import->uuid }}</small></h1>
</section>
<section class="content">
    @if($import->failure_message)<div class="alert alert-danger"><i class="fas fa-exclamation-triangle"></i> {{ $import->failure_message }}</div>@endif
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">{{ $import->original_name }}</h3>
            <div class="box-tools"><span id="import_status" class="label label-info">{{ ucwords(str_replace('_', ' ', $import->status)) }}</span></div>
        </div>
        <div class="box-body">
            <dl class="row">
                <dt class="col-sm-2">Company</dt><dd class="col-sm-4">{{ $business->name }}</dd><dt class="col-sm-2">Location scope</dt><dd class="col-sm-4">{{ optional($import->location)->name ?: 'File-defined / not applicable' }}</dd>
                <dt class="col-sm-2">Uploaded by</dt><dd class="col-sm-4">{{ optional($import->uploader)->user_full_name ?: optional($import->uploader)->email }}</dd><dt class="col-sm-2">Duplicate policy</dt><dd class="col-sm-4">{{ ucfirst($import->duplicate_strategy) }}</dd>
                <dt class="col-sm-2">Uploaded</dt><dd class="col-sm-4">{{ @format_datetime($import->created_at) }}</dd><dt class="col-sm-2">Checksum</dt><dd class="col-sm-4"><code>{{ substr($import->checksum, 0, 16) }}…</code></dd>
            </dl>
            <div class="row text-center" id="import_counts">
                @foreach(['total_rows' => 'Total', 'valid_rows' => 'Valid', 'invalid_rows' => 'Invalid', 'created_rows' => 'Created', 'updated_rows' => 'Updated', 'skipped_rows' => 'Skipped', 'failed_rows' => 'Failed'] as $field => $label)
                    <div class="col-md-1 col-sm-3 col-xs-4" style="min-width:110px"><strong class="h3" data-count="{{ $field }}">{{ number_format($import->{$field}) }}</strong><br><span class="text-muted">{{ $label }}</span></div>
                @endforeach
            </div>
        </div>
        <div class="box-footer">
            <a href="{{ route('data-imports.index') }}" class="btn btn-default"><i class="fas fa-arrow-left"></i> All imports</a>
            <a href="{{ route('data-imports.source', $import) }}" class="btn btn-default"><i class="fas fa-download"></i> Source file</a>
            @if($import->invalid_rows || $import->failed_rows || $import->status === 'rollback_failed')<a href="{{ route('data-imports.errors', $import) }}" class="btn btn-warning"><i class="fas fa-file-csv"></i> Error report</a>@endif
            @if($import->status === 'ready' && $canApprove)
                <form method="post" action="{{ route('data-imports.commit', $import) }}" class="pull-right" onsubmit="return confirm('Commit all valid rows to live company data? Invalid rows will remain excluded.')">@csrf<button class="btn btn-success"><i class="fas fa-check"></i> Approve and import {{ number_format($import->valid_rows) }} valid rows</button></form>
            @elseif($import->canBeCancelled())
                <form method="post" action="{{ route('data-imports.cancel', $import) }}" class="pull-right" onsubmit="return confirm('Cancel this import? No live records will be changed.')">@csrf<button class="btn btn-default">Cancel import</button></form>
            @elseif(in_array($import->status, ['completed','completed_with_errors','rollback_failed']) && $canRollback && $rollbackDays > 0)
                <form method="post" action="{{ route('data-imports.rollback', $import) }}" class="pull-right" onsubmit="return confirm('Roll back records created or updated by this import? Records changed later will be protected and reported.')">@csrf<button class="btn btn-danger"><i class="fas fa-undo"></i> Roll back import</button></form>
            @endif
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Staged rows</h3><div class="box-tools"><span class="text-muted">Only valid rows are processed</span></div></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-condensed">
                <thead><tr><th>Row</th><th>Status</th>@foreach(array_keys($definition['columns']) as $column)<th>{{ ucwords(str_replace('_',' ',$column)) }}</th>@endforeach<th>Validation details</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    @php($rowClass = $row->status === 'invalid' || in_array($row->status, ['failed','rollback_failed']) ? 'danger' : (in_array($row->status, ['created','updated']) ? 'success' : ''))
                    <tr class="{{ $rowClass }}"><td>{{ $row->row_number }}</td><td>{{ ucwords(str_replace('_',' ',$row->status)) }}</td>@foreach(array_keys($definition['columns']) as $column)@php($cell = data_get($row->normalized_values, $column))<td>{{ \Illuminate\Support\Str::limit(is_array($cell) ? implode(', ', $cell) : (string) $cell, 80) }}</td>@endforeach<td>@foreach((array) $row->validation_errors as $field => $messages)<div><strong>{{ $field }}:</strong> {{ implode('; ', (array) $messages) }}</div>@endforeach</td></tr>
                @empty<tr><td colspan="{{ count($definition['columns']) + 3 }}" class="text-center text-muted">Rows are being prepared. This page will refresh automatically.</td></tr>@endforelse
                </tbody>
            </table>
        </div>
        <div class="box-footer">{{ $rows->links() }}</div>
    </div>

    <div class="box box-default collapsed-box">
        <div class="box-header with-border"><h3 class="box-title">Audit trail</h3><div class="box-tools"><button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button></div></div>
        <div class="box-body table-responsive"><table class="table table-condensed"><thead><tr><th>Time</th><th>Event</th><th>User</th><th>Context</th></tr></thead><tbody>@foreach($import->events->sortByDesc('created_at') as $event)<tr><td>{{ @format_datetime($event->created_at) }}</td><td>{{ ucwords(str_replace('_',' ',$event->event)) }}</td><td>{{ $event->user_id ?: 'System worker' }}</td><td><code>{{ json_encode($event->context, JSON_UNESCAPED_UNICODE) }}</code></td></tr>@endforeach</tbody></table></div>
    </div>
</section>
@endsection

@if(in_array($import->status, $activeStatuses))
@section('javascript')
<script>
(function pollImport() {
    fetch(@json(route('data-imports.progress', $import)), {headers: {'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'}})
        .then(function (response) { return response.json(); })
        .then(function (data) {
            document.getElementById('import_status').textContent = data.status.replace(/_/g, ' ').replace(/\b\w/g, function (c) { return c.toUpperCase(); });
            document.querySelectorAll('[data-count]').forEach(function (node) { node.textContent = Number(data[node.dataset.count] || 0).toLocaleString(); });
            if (['uploaded','validating','queued','processing','rolling_back'].includes(data.status)) {
                window.setTimeout(pollImport, 2500);
            } else {
                window.location.reload();
            }
        }).catch(function () { window.setTimeout(pollImport, 5000); });
})();
</script>
@endsection
@endif
