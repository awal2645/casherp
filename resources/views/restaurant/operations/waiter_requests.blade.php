@extends('layouts.app')
@section('title', 'Waiter Requests')
@section('content')
<section class="content-header"><h1>Waiter requests <small>location and table service queue</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="row"><div class="col-md-4"><div class="box box-primary"><div class="box-header"><h3 class="box-title">Add request</h3></div>
        <form method="post" action="{{ route('restaurant-operations.waiter-requests.store') }}">@csrf<div class="box-body">
            <div class="form-group"><label>Location *</label>{!! Form::select('location_id',$locations,null,['class'=>'form-control select2','required','placeholder'=>'Select location']) !!}</div>
            <div class="form-group"><label>Table *</label><select name="table_id" class="form-control select2" required><option value="">Select table</option>@foreach($tables as $table)<option value="{{ $table->id }}" data-location="{{ $table->location_id }}">{{ $locations[$table->location_id] ?? 'Location' }} — {{ $table->name }}</option>@endforeach</select></div>
            <div class="form-group"><label>Request *</label>{!! Form::select('request_type',config('restaurant_operations.waiter_request_types'),null,['class'=>'form-control','required']) !!}</div>
            <div class="form-group"><label>Notes</label><textarea name="notes" class="form-control" maxlength="1000"></textarea></div>
        </div><div class="box-footer"><button class="btn btn-primary">Add to queue</button></div></form>
    </div></div><div class="col-md-8"><div class="box"><div class="box-body table-responsive">
        <table class="table table-bordered table-striped"><thead><tr><th>Waiting since</th><th>Location</th><th>Table</th><th>Request</th><th>Status</th><th>Action</th></tr></thead><tbody>
        @forelse($requests as $item)<tr class="{{ $item->created_at->lt(now()->subMinutes(10))?'warning':'' }}"><td>{{ $item->created_at->diffForHumans() }}</td><td>{{ $locations[$item->location_id] ?? $item->location_id }}</td><td>#{{ $item->table_id }}</td><td>{{ config('restaurant_operations.waiter_request_types.'.$item->request_type,$item->request_type) }}<br><small>{{ $item->notes }}</small></td><td>{{ ucfirst($item->status) }}</td><td>
            @foreach($item->status==='open'?['acknowledged','resolved']:['resolved'] as $next)<form method="post" action="{{ route('restaurant-operations.waiter-requests.update',$item->id) }}" class="inline-form">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next }}"><button class="btn btn-xs btn-primary">{{ ucfirst($next) }}</button></form>@endforeach
        </td></tr>@empty<tr><td colspan="6" class="text-center">No open waiter requests.</td></tr>@endforelse</tbody></table>{{ $requests->links() }}
    </div></div></div></div>
</section>
@endsection
