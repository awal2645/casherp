@extends('layouts.app')
@section('title', 'New Data Import')

@section('content')
<section class="content-header">
    <h1>New Data Import <small>{{ $definition['label'] }}</small></h1>
</section>
<section class="content">
    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">1. Prepare and upload your file</h3></div>
                <form action="{{ route('data-imports.store') }}" method="post" enctype="multipart/form-data" id="data_import_form">
                    @csrf
                    <div class="box-body">
                        <div class="alert alert-info"><i class="fas fa-shield-alt"></i> Uploads are stored privately. Rows are staged and checked before an approver can commit them to live company data.</div>
                        <div class="form-group">
                            <label for="dataset">Dataset</label>
                            <select name="dataset" id="dataset" class="form-control" required onchange="window.location='{{ route('data-imports.create') }}?dataset='+encodeURIComponent(this.value)">
                                @foreach($datasets as $dataset)<option value="{{ $dataset['key'] }}" @selected($dataset['key'] === $definition['key'])>{{ $dataset['label'] }}</option>@endforeach
                            </select>
                            <p class="help-block">{{ $definition['description'] }}</p>
                        </div>
                        <div class="form-group">
                            <label for="business_location_id">Default company location</label>
                            <select name="business_location_id" id="business_location_id" class="form-control select2"><option value="">Use location names in the file / not applicable</option>@foreach($locations as $id => $name)<option value="{{ $id }}" @selected((string) old('business_location_id') === (string) $id)>{{ $name }}</option>@endforeach</select>
                            <p class="help-block">Only your permitted locations are available. A row-specific location name takes precedence.</p>
                        </div>
                        <div class="form-group">
                            <label for="duplicate_strategy">When a matching company record already exists</label>
                            <select name="duplicate_strategy" id="duplicate_strategy" class="form-control" required>
                                <option value="reject" @selected(old('duplicate_strategy', 'reject') === 'reject')>Reject the row (safest)</option>
                                <option value="skip" @selected(old('duplicate_strategy') === 'skip')>Skip the existing record</option>
                                <option value="update" @selected(old('duplicate_strategy') === 'update')>Update allowed fields and retain rollback history</option>
                            </select>
                        </div>
                        <div class="form-group @error('file') has-error @enderror">
                            <label for="file">CSV, XLSX or XLS file</label>
                            <input type="file" name="file" id="file" class="form-control" accept=".csv,.xlsx,.xls" required>
                            <p class="help-block">Maximum {{ $usage['max_file_size_mb'] }} MB and {{ number_format($usage['max_rows_per_file']) }} data rows. Formulas, macros and remote image downloads are not used.</p>
                            @error('file')<span class="help-block">{{ $message }}</span>@enderror
                        </div>
                        <div class="checkbox"><label><input type="checkbox" name="allow_duplicate_file" value="1"> I intentionally want to upload this file even if its checksum matches a recent import.</label></div>
                    </div>
                    <div class="box-footer">
                        <button type="submit" class="btn btn-primary" id="upload_button"><i class="fas fa-upload"></i> Upload and validate</button>
                        <a href="{{ route('data-imports.index') }}" class="btn btn-default">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-default">
                <div class="box-header with-border"><h3 class="box-title">Required structure</h3></div>
                <div class="box-body">
                    <a class="btn btn-success btn-block" href="{{ route('data-imports.template', $definition['key']) }}"><i class="fas fa-download"></i> Download current CSV template</a>
                    <hr>
                    <div class="table-responsive"><table class="table table-condensed"><thead><tr><th>Column</th><th>Required</th></tr></thead><tbody>@foreach($definition['columns'] as $key => $column)<tr><td><code>{{ $key }}</code></td><td>{!! ($column['required'] ?? false) ? '<span class="label label-danger">Yes</span>' : '<span class="text-muted">No</span>' !!}</td></tr>@endforeach</tbody></table></div>
                    <p class="text-muted"><i class="fas fa-info-circle"></i> Keep the first header row unchanged. Dates use <code>YYYY-MM-DD</code>. Decimal numbers must not contain currency symbols.</p>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
document.getElementById('data_import_form').addEventListener('submit', function () {
    var button = document.getElementById('upload_button');
    button.disabled = true;
    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading securely…';
});
</script>
@endsection
