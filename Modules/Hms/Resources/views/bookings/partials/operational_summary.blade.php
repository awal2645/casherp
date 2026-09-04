@php
    $operationalStatus = $transaction->hms_booking_status
        ?: (!empty($transaction->check_out)
            ? 'checked_out'
            : (!empty($transaction->check_in)
                ? 'checked_in'
                : ($transaction->status === 'pending' ? 'tentative' : ($transaction->status === 'cancelled' ? 'cancelled' : 'reserved'))));
@endphp

<div class="col-md-12">
    <hr>
    <h3>@lang('hms::lang.operational_details')</h3>
</div>
<div class="col-md-6">
    <div class="form-group">
        <strong>@lang('hms::lang.booking_source'):</strong>
        {{ __('hms::lang.source_'.($transaction->hms_booking_source ?: 'direct')) }}
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        <strong>@lang('hms::lang.status'):</strong>
        <span class="label label-info">{{ __('hms::lang.status_'.$operationalStatus) }}</span>
    </div>
</div>
@if($transaction->hms_property)
    <div class="col-md-6">
        <div class="form-group"><strong>@lang('hms::lang.property'):</strong> {{ $transaction->hms_property->name }}</div>
    </div>
@endif
@if($transaction->hms_rate_plan)
    <div class="col-md-6">
        <div class="form-group"><strong>@lang('hms::lang.rate_plan'):</strong> {{ $transaction->hms_rate_plan->name }}</div>
    </div>
@endif
@if($transaction->hms_group_booking)
    <div class="col-md-6">
        <div class="form-group"><strong>@lang('hms::lang.group_booking'):</strong> {{ $transaction->hms_group_booking->name }}</div>
    </div>
@endif
@if($transaction->hms_folios->isNotEmpty())
    <div class="col-md-6">
        <div class="form-group">
            <strong>@lang('hms::lang.folio'):</strong>
            @foreach($transaction->hms_folios as $folio)
                <a href="{{ route('hms.folios.show', $folio->id) }}">{{ $folio->folio_number }}</a>@if(!$loop->last), @endif
            @endforeach
        </div>
    </div>
@endif
@if($transaction->hms_external_reference)
    <div class="col-md-6">
        <div class="form-group"><strong>@lang('hms::lang.external_reference'):</strong> {{ $transaction->hms_external_reference }}</div>
    </div>
@endif
@if($transaction->hms_hold_expires_at)
    <div class="col-md-6">
        <div class="form-group"><strong>@lang('hms::lang.hold_expires_at'):</strong> @format_datetime($transaction->hms_hold_expires_at)</div>
    </div>
@endif
@if($transaction->hms_special_requests)
    <div class="col-md-12">
        <div class="form-group"><strong>@lang('hms::lang.special_requests'):</strong><br>{{ $transaction->hms_special_requests }}</div>
    </div>
@endif
@if($transaction->hms_cancellation_reason)
    <div class="col-md-12">
        <div class="alert alert-warning">
            <strong>@lang('hms::lang.cancellation_reason'):</strong> {{ $transaction->hms_cancellation_reason }}
        </div>
    </div>
@endif

<div class="col-md-12">
    <h3>@lang('hms::lang.booking_timeline')</h3>
    <ul class="timeline">
        @forelse($transaction->hms_booking_events as $event)
            <li>
                <i class="fa fa-history bg-aqua"></i>
                <div class="timeline-item">
                    <span class="time"><i class="fa fa-clock"></i> @format_datetime($event->occurred_at)</span>
                    <h3 class="timeline-header">
                        {{ __('hms::lang.'.$event->event_type) }}
                        @if($event->from_status && $event->to_status)
                            <small>
                                {{ __('hms::lang.status_'.$event->from_status) }}
                                &rarr;
                                {{ __('hms::lang.status_'.$event->to_status) }}
                            </small>
                        @endif
                    </h3>
                    @if($event->notes || $event->actor)
                        <div class="timeline-body">
                            @if($event->notes)<p>{{ $event->notes }}</p>@endif
                            @if($event->actor)
                                <small>@lang('hms::lang.by_user'): {{ trim($event->actor->first_name.' '.$event->actor->last_name) }}</small>
                            @endif
                        </div>
                    @endif
                </div>
            </li>
        @empty
            <li><div class="timeline-item"><div class="timeline-body">@lang('hms::lang.no_timeline_events')</div></div></li>
        @endforelse
    </ul>
</div>
<div class="clearfix"></div>
