@extends('layouts.app')
@section('title', 'Property Access')

@section('content')
<section class="content-header">
    <h1>Property Access <small>Control which properties each company role or user may use</small></h1>
</section>
<section class="content">
    <div class="callout callout-info">
        <h4><i class="fa fa-shield"></i> Company, location and property controls work together</h4>
        <p>A user must first have the relevant role permission and location access. A property remains available under those normal rules until its first assignment is added here. After that, only matching users or roles with the selected capability may access it. Company administrators retain emergency access.</p>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Add or update an assignment</h3></div>
        {!! Form::open(['route' => 'property.access.store', 'method' => 'post']) !!}
        <div class="box-body">
            <div class="row">
                <div class="col-md-4 form-group"><label>Property</label><select name="property_id" class="form-control select2" required><option value="">Select property</option>@foreach($properties as $property)<option value="{{ $property->id }}" @selected((int) old('property_id') === (int) $property->id)>{{ optional($property->businessLocation)->name ? optional($property->businessLocation)->name.' — ' : '' }}{{ $property->name }}</option>@endforeach</select></div>
                <div class="col-md-2 form-group"><label>Assign to</label><select name="subject_type" id="property_subject_type" class="form-control" required><option value="role" @selected(old('subject_type', 'role') === 'role')>Company role</option><option value="user" @selected(old('subject_type') === 'user')>Named user</option></select></div>
                <div class="col-md-6 form-group property-subject property-subject-role"><label>Company role</label>{!! Form::select('subject_id', $roles, old('subject_type', 'role') === 'role' ? old('subject_id') : null, ['class' => 'form-control select2 property-subject-select', 'data-subject' => 'role', 'placeholder' => 'Select role']) !!}</div>
                <div class="col-md-6 form-group property-subject property-subject-user" style="display:none"><label>Named user</label>{!! Form::select('subject_id_disabled', $users, old('subject_type') === 'user' ? old('subject_id') : null, ['class' => 'form-control select2 property-subject-select', 'data-subject' => 'user', 'placeholder' => 'Select user', 'disabled' => true]) !!}</div>
            </div>
            <div class="row">
                @foreach($abilities as $ability => $label)
                    <div class="col-md-4"><div class="checkbox"><label><input type="checkbox" name="abilities[]" value="{{ $ability }}" @checked(in_array($ability, old('abilities', ['view']), true))> <strong>{{ ucfirst($ability) }}</strong> — {{ $label }}</label></div></div>
                @endforeach
            </div>
        </div>
        <div class="box-footer"><a href="{{ route('property.index') }}" class="btn btn-default">Back to properties</a><button class="btn btn-primary pull-right"><i class="fa fa-save"></i> Save assignment</button></div>
        {!! Form::close() !!}
    </div>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Active assignments</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped"><thead><tr><th>Location / property</th><th>Assigned role or user</th><th>Capabilities</th><th>Action</th></tr></thead><tbody>
            @forelse($grants as $grant)
                <tr><td>{{ optional(optional($grant->property)->businessLocation)->name ? optional($grant->property->businessLocation)->name.' — ' : '' }}{{ optional($grant->property)->name }}</td><td><span class="label label-default">{{ ucfirst($grant->subject_type) }}</span> {{ $grant->subject_type === 'role' ? ($roleNames[$grant->subject_id] ?? 'Removed role') : ($userNames[$grant->subject_id] ?? 'Removed user') }}</td><td>@foreach((array) $grant->abilities as $ability)<span class="label label-primary">{{ ucfirst($ability) }}</span> @endforeach</td><td>{!! Form::open(['route' => ['property.access.destroy', $grant], 'method' => 'delete', 'style' => 'display:inline']) !!}<button class="btn btn-xs btn-danger" onclick="return confirm('Remove this property assignment?')"><i class="fa fa-trash"></i> Remove</button>{!! Form::close() !!}</td></tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">No property-level restrictions are active. Existing role and location permissions apply.</td></tr>
            @endforelse
            </tbody></table>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
(function () {
    function syncSubject() {
        var type = document.getElementById('property_subject_type').value;
        document.querySelectorAll('.property-subject').forEach(function (group) { group.style.display = 'none'; });
        document.querySelectorAll('.property-subject-select').forEach(function (field) { field.disabled = true; field.name = 'subject_id_disabled'; });
        var active = document.querySelector('.property-subject-' + type);
        if (active) {
            active.style.display = '';
            var field = active.querySelector('.property-subject-select');
            field.disabled = false;
            field.name = 'subject_id';
        }
    }
    document.getElementById('property_subject_type').addEventListener('change', syncSubject);
    syncSubject();
})();
</script>
@endsection
