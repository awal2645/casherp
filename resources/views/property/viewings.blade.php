@extends('layouts.app')
@section('title', 'Property Viewings')

@section('content')
<section class="content-header"><h1>Property Viewings <small>Requests, approvals and the viewing calendar</small></h1></section>
<section class="content">
    <div class="box box-default">
        <div class="box-body">
            <form method="get" class="form-inline">
                <label>From <input type="date" class="form-control" name="from" value="{{ request('from', $from->toDateString()) }}"></label>
                <label>To <input type="date" class="form-control" name="to" value="{{ request('to', $to->toDateString()) }}"></label>
                <select class="form-control" name="status"><option value="">All statuses</option>@foreach(['pending','approved','rejected','completed','cancelled'] as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select>
                <button class="btn btn-primary"><i class="fa fa-filter"></i> Apply</button>
                <a class="btn btn-default" href="{{ route('property.index') }}">Property dashboard</a>
            </form>
        </div>
    </div>

    @if($canManage)
    <div class="box box-primary collapsed-box">
        <div class="box-header with-border"><h3 class="box-title">Add viewing request</h3><div class="box-tools"><button class="btn btn-box-tool" type="button" data-widget="collapse"><i class="fa fa-plus"></i></button></div></div>
        <form method="post" action="{{ route('property.viewings.store') }}">@csrf
            <div class="box-body row">
                <div class="col-md-4"><div class="form-group"><label for="viewing_property">Property:*</label><select id="viewing_property" name="property_id" class="form-control select2" required><option value="">Select property</option>@foreach($properties as $property)<option value="{{ $property->id }}">{{ $property->name }}</option>@endforeach</select></div></div>
                <div class="col-md-4"><div class="form-group"><label for="viewing_unit">Unit / space:</label><select id="viewing_unit" name="property_unit_id" class="form-control select2"><option value="">Whole property / not decided</option>@foreach($properties as $property)@foreach($property->units as $unit)<option value="{{ $unit->id }}" data-property-id="{{ $property->id }}">{{ $property->name }} / {{ $unit->unit_code }}</option>@endforeach @endforeach</select></div></div>
                <div class="col-md-4"><div class="form-group"><label for="viewing_contact">Existing tenant / prospect:</label><select id="viewing_contact" name="contact_id" class="form-control select2"><option value="">Not linked</option>@foreach($contacts as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div></div>
                <div class="col-md-4"><div class="form-group"><label>Requester name:*</label><input class="form-control" name="requester_name" required maxlength="191" value="{{ old('requester_name') }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Email:</label><input type="email" class="form-control" name="requester_email" maxlength="191" value="{{ old('requester_email') }}"></div></div>
                <div class="col-md-4"><div class="form-group"><label>Phone:</label><input class="form-control" name="requester_phone" maxlength="60" value="{{ old('requester_phone') }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Starts:*</label><input type="datetime-local" class="form-control" name="requested_start_at" required value="{{ old('requested_start_at') }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Ends:*</label><input type="datetime-local" class="form-control" name="requested_end_at" required value="{{ old('requested_end_at') }}"></div></div>
                <div class="col-md-3"><div class="form-group"><label>Assign to:</label><select class="form-control select2" name="assigned_to"><option value="">Unassigned</option>@foreach($assignees as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach</select></div></div>
                <div class="col-md-3"><div class="form-group"><label>Notes:</label><input class="form-control" name="notes" maxlength="5000" value="{{ old('notes') }}"></div></div>
            </div>
            <div class="box-footer"><button class="btn btn-primary pull-right"><i class="fa fa-calendar-plus-o"></i> Add request</button></div>
        </form>
    </div>
    @endif

    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Schedule and requests</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Date &amp; time</th><th>Property / unit</th><th>Requester</th><th>Assigned</th><th>Status</th><th>Decision</th></tr></thead>
                        <tbody>
                        @forelse($viewings as $viewing)
                            <tr><td>{{ @format_datetime($viewing->requested_start_at) }}<br><small>to {{ @format_datetime($viewing->requested_end_at) }}</small></td><td><strong>{{ optional($viewing->property)->name }}</strong><br><small>{{ optional($viewing->unit)->unit_code ?: 'Whole property' }} · {{ optional(optional($viewing->property)->businessLocation)->name }}</small></td><td>{{ $viewing->requester_name }}<br><small>{{ $viewing->requester_email ?: $viewing->requester_phone }}</small></td><td>{{ optional($viewing->assignee)->user_full_name ?: 'Unassigned' }}</td><td><span class="label {{ $viewing->status === 'approved' ? 'label-success' : ($viewing->status === 'pending' ? 'label-warning' : 'label-default') }}">{{ ucfirst($viewing->status) }}</span>@if($viewing->decision_note)<br><small>{{ $viewing->decision_note }}</small>@endif</td>
                                <td>@if($canManage && in_array($viewing->status, ['pending','approved']))<form method="post" action="{{ route('property.viewings.decide', $viewing) }}">@csrf<div class="input-group input-group-sm"><input class="form-control" name="decision_note" placeholder="Note for reject/cancel"><span class="input-group-btn">@if($viewing->status === 'pending')<button class="btn btn-success" name="decision" value="approve" title="Approve"><i class="fa fa-check"></i></button><button class="btn btn-danger" name="decision" value="reject" title="Reject"><i class="fa fa-times"></i></button>@else<button class="btn btn-success" name="decision" value="complete" title="Complete"><i class="fa fa-check-square-o"></i></button>@endif<button class="btn btn-default" name="decision" value="cancel" title="Cancel"><i class="fa fa-ban"></i></button></span></div></form>@else&mdash;@endif</td>
                            </tr>
                        @empty<tr><td colspan="6" class="text-center text-muted">No viewing requests match this period.</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
                <div class="box-footer">{{ $viewings->links() }}</div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Calendar agenda</h3></div>
                <div class="box-body">
                    @forelse($calendar as $date => $dayViewings)<h4>{{ \Carbon\Carbon::parse($date)->format('D, d M Y') }}</h4>@foreach($dayViewings as $item)<div class="well well-sm"><strong>{{ $item->requested_start_at->format('H:i') }} · {{ optional($item->property)->name }}</strong><br>{{ $item->requester_name }} <span class="label {{ $item->status === 'approved' ? 'label-success' : 'label-warning' }} pull-right">{{ ucfirst($item->status) }}</span></div>@endforeach @empty<p class="text-muted">No pending or approved viewings in this period.</p>@endforelse
                </div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('javascript')
<script>
$(function () {
    $('.select2').select2({width: '100%'});
    $('#viewing_property').on('change', function () {
        var propertyId = this.value;
        $('#viewing_unit option[data-property-id]').each(function () { this.disabled = propertyId && this.dataset.propertyId !== propertyId; });
        $('#viewing_unit').val('').trigger('change.select2');
    });
});
</script>
@endsection
