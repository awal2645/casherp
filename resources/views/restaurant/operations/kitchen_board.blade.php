@extends('layouts.app')
@section('title', 'Kitchen Display')
@section('content')
<section class="content-header"><h1>Kitchen display <small>station-specific KOT queue</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="row">
        @forelse($tickets as $ticket)
            @php $age = optional($ticket->fired_at)->diffInMinutes(now()) ?? 0; @endphp
            <div class="col-lg-3 col-md-4 col-sm-6">
                <div class="box {{ $age >= 30 ? 'box-danger' : ($age >= 15 ? 'box-warning' : 'box-primary') }}">
                    <div class="box-header"><h3 class="box-title">{{ $ticket->ticket_number }}</h3><span class="label label-default pull-right">{{ $ticket->station->name ?? 'Main kitchen' }}</span></div>
                    <div class="box-body"><p><strong>{{ config('restaurant_operations.service_channels.'.$ticket->service_channel,$ticket->service_channel) }}</strong> · {{ $age }} min</p><ul class="list-unstyled">
                        @foreach($ticket->items as $item)<li><strong>{{ (float)$item->quantity }} × {{ $item->item_name }}</strong>@foreach((array)$item->modifier_snapshot as $modifier)<br><small>+ {{ $modifier['name'] ?? 'Modifier' }}</small>@endforeach</li>@endforeach
                    </ul></div>
                    <div class="box-footer">
                        @foreach((array)config('restaurant_operations.kitchen_transitions.'.$ticket->status,[]) as $next)
                            <form method="post" action="{{ route('restaurant-operations.kitchen-tickets.transition',$ticket->id) }}" class="inline-form">@csrf @method('PATCH')<input type="hidden" name="status" value="{{ $next }}">@if($next==='cancelled')<input name="cancellation_reason" class="form-control input-sm" required placeholder="Cancellation reason">@endif<button class="btn btn-xs {{ $next==='cancelled'?'btn-danger':'btn-primary' }}">{{ config('restaurant_operations.kitchen_statuses.'.$next,$next) }}</button></form>
                        @endforeach
                    </div>
                </div>
            </div>
        @empty<div class="col-md-12"><div class="alert alert-info">No active kitchen tickets match this filter.</div></div>@endforelse
    </div>
    {{ $tickets->links() }}
</section>
@endsection
