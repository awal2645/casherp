@extends('layouts.app')
@section('title', 'Data Imports')

@section('content')
<section class="content-header">
    <h1>Data Imports <small>Securely migrate company data with validation, approval and audit history</small></h1>
</section>

<section class="content">
    @if(!$usage['enabled'])
        <div class="alert alert-warning"><i class="fas fa-lock"></i> Data imports are not enabled in the current subscription package.</div>
    @endif

    <div class="row">
        <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-aqua"><i class="fas fa-file-import"></i></span><div class="info-box-content"><span class="info-box-text">Rows used this month</span><span class="info-box-number">{{ number_format($usage['used_rows']) }}</span><span class="progress-description">{{ $usage['monthly_rows'] === 0 ? 'Unlimited allowance' : number_format($usage['remaining_rows']).' remaining' }}</span></div></div></div>
        <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-green"><i class="fas fa-table"></i></span><div class="info-box-content"><span class="info-box-text">Rows per file</span><span class="info-box-number">{{ number_format($usage['max_rows_per_file']) }}</span><span class="progress-description">Maximum validated rows</span></div></div></div>
        <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-yellow"><i class="fas fa-hdd"></i></span><div class="info-box-content"><span class="info-box-text">Maximum file</span><span class="info-box-number">{{ $usage['max_file_size_mb'] }} MB</span><span class="progress-description">CSV, XLSX or XLS</span></div></div></div>
        <div class="col-md-3 col-sm-6"><div class="info-box"><span class="info-box-icon bg-purple"><i class="fas fa-tasks"></i></span><div class="info-box-content"><span class="info-box-text">Active imports</span><span class="info-box-number">{{ $usage['active_imports'] }} / {{ $usage['concurrent_imports'] }}</span><span class="progress-description">Validated in the background</span></div></div></div>
    </div>

    @if($datasets->isNotEmpty())
        <div class="box box-primary">
            <div class="box-header with-border"><h3 class="box-title">Industry-ready imports</h3></div>
            <div class="box-body">
                <p class="text-muted">Only datasets relevant to <strong>{{ optional($business->industry)->name ?: 'this company' }}</strong> and your permissions are shown.</p>
                <div class="row">
                    @foreach($datasets as $dataset)
                        <div class="col-md-4 col-sm-6">
                            <div class="well well-sm" style="min-height:145px">
                                <h4>{{ $dataset['label'] }}</h4>
                                <p>{{ $dataset['description'] }}</p>
                                <a class="btn btn-primary btn-sm" href="{{ route('data-imports.create', ['dataset' => $dataset['key']]) }}" @if(!$usage['enabled']) aria-disabled="true" onclick="return false" @endif>
                                    <i class="fas fa-upload"></i> Start import
                                </a>
                                <a class="btn btn-default btn-sm" href="{{ route('data-imports.template', $dataset['key']) }}"><i class="fas fa-download"></i> Template</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

    @if($legacyImports->isNotEmpty())
        <div class="box box-default">
            <div class="box-header with-border"><h3 class="box-title">Specialized existing imports</h3></div>
            <div class="box-body table-responsive">
                <p class="text-muted">These existing workflows remain available where they contain specialized pricing, stock, transaction or attendance logic.</p>
                <table class="table table-bordered table-striped">
                    <thead><tr><th>Dataset</th><th>Purpose</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                        @foreach($legacyImports as $legacy)
                            <tr><td><strong>{{ $legacy['label'] }}</strong></td><td>{{ $legacy['description'] }}</td><td class="text-right"><a class="btn btn-default btn-sm" href="{{ url($legacy['route']) }}">Open specialized import <i class="fas fa-arrow-right"></i></a></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <div class="box box-solid">
        <div class="box-header with-border">
            <h3 class="box-title">Import history</h3>
            @if($datasets->isNotEmpty() && $usage['enabled'])<div class="box-tools"><a class="btn btn-primary btn-sm" href="{{ route('data-imports.create') }}"><i class="fas fa-plus"></i> New import</a></div>@endif
        </div>
        <div class="box-body">
            <form method="get" class="form-inline tw-mb-4">
                <select name="status" class="form-control input-sm"><option value="">All statuses</option>@foreach(['ready','processing','completed','completed_with_errors','failed','cancelled','rolled_back','rollback_failed'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucwords(str_replace('_', ' ', $status)) }}</option>@endforeach</select>
                <button class="btn btn-default btn-sm">Filter</button>
                @if(request()->hasAny(['status','dataset']))<a class="btn btn-link btn-sm" href="{{ route('data-imports.index') }}">Clear</a>@endif
            </form>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead><tr><th>Uploaded</th><th>Dataset</th><th>File</th><th>Location</th><th>Rows</th><th>Status</th><th>Uploaded by</th><th></th></tr></thead>
                    <tbody>
                    @forelse($imports as $import)
                        @php($statusClass = in_array($import->status, ['completed','rolled_back']) ? 'success' : (in_array($import->status, ['failed','rollback_failed']) ? 'danger' : (in_array($import->status, ['completed_with_errors']) ? 'warning' : 'info')))
                        <tr>
                            <td>{{ @format_datetime($import->created_at) }}</td><td>{{ ucwords(str_replace('_', ' ', $import->dataset)) }}</td><td>{{ \Illuminate\Support\Str::limit($import->original_name, 36) }}</td><td>{{ optional($import->location)->name ?: 'All / file-defined' }}</td>
                            <td>{{ number_format($import->total_rows) }}</td><td><span class="label label-{{ $statusClass }}">{{ ucwords(str_replace('_', ' ', $import->status)) }}</span></td><td>{{ optional($import->uploader)->user_full_name ?: optional($import->uploader)->email }}</td>
                            <td class="text-right"><a href="{{ route('data-imports.show', $import) }}" class="btn btn-xs btn-primary">Review</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-muted">No imports have been uploaded for this company.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $imports->links() }}
        </div>
    </div>
</section>
@endsection
