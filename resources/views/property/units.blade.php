@extends('layouts.app')
@section('title', 'Property Units & Listings')
@section('content')
<section class="content-header"><h1>Units &amp; Listings <small>Rent, lease, sale and availability controls</small></h1></section>
<section class="content">
    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Portfolio scope</h3></div>
        <div class="box-body">
            {!! Form::open(['route' => 'property.units.index', 'method' => 'get', 'class' => 'form-inline']) !!}
                @include('property.partials.portfolio_filters')
                <button class="btn btn-default"><i class="fa fa-filter"></i> Apply</button>
            {!! Form::close() !!}
            <p class="help-block">Results include only locations permitted for your user account.</p>
        </div>
    </div>
    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Spaces in permitted locations</h3><div class="box-tools"><a class="btn btn-default btn-sm" href="{{ route('property.index') }}">Dashboard</a></div></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped"><thead><tr><th>Property / location</th><th>Unit</th><th>Purpose</th><th>Price</th><th>Available</th><th>Occupancy</th><th>Listing</th><th></th></tr></thead><tbody>
            @forelse($units as $unit)
                <tr><td><strong>{{ optional($unit->property)->name }}</strong><br><small>{{ optional(optional($unit->property)->businessLocation)->name }}</small></td><td>{{ $unit->unit_code }}<br><small>{{ $unit->unit_type }}</small></td><td>{{ ucwords(str_replace('_',' ',$unit->listing_purpose)) }}</td><td>@if($unit->listing_purpose === 'sale')<span class="display_currency" data-currency_symbol="true">{{ $unit->asking_price }}</span>@else<span class="display_currency" data-currency_symbol="true">{{ $unit->monthly_rent }}</span><small> / month</small>@endif</td><td>@if($unit->available_from) @format_date($unit->available_from) @else Now / unspecified @endif</td><td><span class="label {{ $unit->status === 'vacant' ? 'label-success' : 'label-default' }}">{{ ucfirst($unit->status) }}</span></td><td><span class="label {{ $unit->is_listed && $unit->listing_status === 'active' ? 'label-success' : 'label-default' }}">{{ $unit->is_listed ? ucfirst(str_replace('_',' ',$unit->listing_status)) : 'Internal' }}</span></td><td>@can('property.manage')<a class="btn btn-xs btn-primary" href="{{ route('property.units.edit',$unit) }}">Edit</a>@else&mdash;@endcan</td></tr>
            @empty<tr><td colspan="8" class="text-center text-muted">No units match this portfolio scope.</td></tr>@endforelse
            </tbody></table>
        </div>
        <div class="box-footer">{{ $units->links() }}</div>
    </div>
</section>
@endsection
