@extends('layouts.app')
@section('title', __('hms::lang.folios'))
@section('content')
@include('hms::layouts.nav')
<section class="content-header"><h1>@lang('hms::lang.folios') <small>@lang('hms::lang.folios_help')</small></h1></section>
<section class="content">
@if($securityAlerts->isNotEmpty())
<div class="box box-warning"><div class="box-header with-border"><h3 class="box-title"><i class="fa fa-shield"></i> Security-deposit actions</h3><span class="label label-warning pull-right">{{ $securityAlerts->count() }}</span></div><div class="box-body table-responsive"><table class="table table-condensed"><thead><tr><th>Booking</th><th>Guest</th><th>Property</th><th>Reason</th><th>Held balance</th><th>Action</th></tr></thead><tbody>
@foreach($securityAlerts as $alert)<tr><td>{{ $alert['transaction_label'] }}<br><small>{{ $alert['transaction_date'] ?: 'No departure date' }}</small></td><td>{{ optional($alert['deposit']->contact)->name ?: 'Not assigned' }}</td><td>{{ optional($alert['deposit']->location)->name ?: 'Not assigned' }}</td><td><span class="label label-{{ $alert['severity'] }}">{{ $alert['reason'] }}</span></td><td><span class="display_currency" data-currency_symbol="true">{{ $alert['deposit']->held_balance }}</span></td><td>@if($alert['folio_id'])<a class="btn btn-xs btn-primary" href="{{ route('hms.folios.show', $alert['folio_id']) }}">Open folio</a>@else<span class="text-muted">Folio unavailable</span>@endif</td></tr>@endforeach
</tbody></table></div></div>
@endif
<div class="box box-primary"><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>@lang('hms::lang.folio_number')</th><th>@lang('hms::lang.property')</th><th>@lang('hms::lang.guest')</th><th>@lang('hms::lang.booking')</th><th>@lang('hms::lang.balance')</th><th>@lang('hms::lang.status')</th><th>@lang('messages.action')</th></tr></thead><tbody>
@forelse($folios as $folio)<tr><td>{{ $folio->folio_number }}</td><td>{{ optional($folio->property)->name }}</td><td>{{ optional($folio->contact)->name ?: optional($folio->groupBooking)->name }}</td><td>{{ optional($folio->booking)->ref_no ?: optional($folio->groupBooking)->code ?: '-' }}</td><td><span class="display_currency" data-currency_symbol="true">{{ $folio->balance }}</span></td><td><span class="label {{ $folio->status === 'settled' ? 'bg-green':'bg-yellow' }}">{{ ucfirst($folio->status) }}</span></td><td><a href="{{ route('hms.folios.show',$folio->id) }}" class="btn btn-xs btn-primary">@lang('messages.view')</a></td></tr>@empty<tr><td colspan="7" class="text-center">@lang('hms::lang.no_folios')</td></tr>@endforelse
</tbody></table><div class="pull-right">{{ $folios->links() }}</div></div></div></section>
@endsection
