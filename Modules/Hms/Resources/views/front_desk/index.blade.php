@extends('layouts.app')
@section('title', __('hms::lang.front_desk'))

@section('content')
    @include('hms::layouts.nav')

    <section class="content no-print">
        <div class="box box-default">
            <div class="box-body">
                <form method="GET" action="{{ route('hms.front_desk.index') }}" class="form-inline">
                    <div class="form-group">
                        <label for="property_id">@lang('hms::lang.property')</label>
                        <select name="property_id" id="property_id" class="form-control select2" style="min-width: 260px;">
                            <option value="">@lang('hms::lang.all_properties')</option>
                            @foreach($properties as $id => $name)
                                <option value="{{ $id }}" @selected((int) $propertyId === (int) $id)>{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">@lang('hms::lang.apply_filter')</button>
                </form>
            </div>
        </div>
        <div class="row">
            @foreach([
                ['label' => __('hms::lang.arrivals_today'), 'value' => $stats['arrivals'], 'class' => 'bg-aqua', 'icon' => 'fa-sign-in-alt'],
                ['label' => __('hms::lang.departures_today'), 'value' => $stats['departures'], 'class' => 'bg-yellow', 'icon' => 'fa-sign-out-alt'],
                ['label' => __('hms::lang.in_house'), 'value' => $stats['occupied_rooms'].' / '.$stats['total_rooms'], 'class' => 'bg-green', 'icon' => 'fa-bed'],
                ['label' => __('hms::lang.occupancy'), 'value' => $stats['occupancy'].'%', 'class' => 'bg-purple', 'icon' => 'fa-chart-pie'],
            ] as $metric)
                <div class="col-md-3 col-sm-6 col-xs-12">
                    <div class="info-box">
                        <span class="info-box-icon {{ $metric['class'] }}"><i class="fas {{ $metric['icon'] }}"></i></span>
                        <div class="info-box-content">
                            <span class="info-box-text">{{ $metric['label'] }}</span>
                            <span class="info-box-number">{{ $metric['value'] }}</span>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row">
            <div class="col-md-3 col-sm-6">
                @component('components.widget', ['title' => __('hms::lang.room_readiness')])
                    <p>@lang('hms::lang.ready'): <strong>{{ $stats['ready_rooms'] }}</strong></p>
                    <p>@lang('hms::lang.dirty'): <strong>{{ $stats['dirty_rooms'] }}</strong></p>
                    <p>@lang('hms::lang.cleaning'): <strong>{{ $stats['cleaning_rooms'] }}</strong></p>
                    <p>@lang('hms::lang.out_of_order'): <strong>{{ $stats['out_of_order_rooms'] }}</strong></p>
                @endcomponent
            </div>
            <div class="col-md-3 col-sm-6">
                @component('components.widget', ['title' => __('hms::lang.operational_alerts')])
                    <p>@lang('hms::lang.holds_expiring'): <strong>{{ $stats['holds_expiring'] }}</strong></p>
                    <p>@lang('hms::lang.overdue_departures'): <strong>{{ $stats['overdue_departures'] }}</strong></p>
                    <p>@lang('hms::lang.no_show_candidates'): <strong>{{ $noShowCandidates->count() }}</strong></p>
                @endcomponent
            </div>
            <div class="col-md-3 col-sm-6">
                @component('components.widget', ['title' => __('hms::lang.adr_30_days')])
                    <h3 class="display_currency" data-currency_symbol="true">{{ $stats['adr_30'] }}</h3>
                    <small>@lang('hms::lang.adr_help')</small>
                @endcomponent
            </div>
            <div class="col-md-3 col-sm-6">
                @component('components.widget', ['title' => __('hms::lang.revpar_30_days')])
                    <h3 class="display_currency" data-currency_symbol="true">{{ $stats['revpar_30'] }}</h3>
                    <small>@lang('hms::lang.revpar_help')</small>
                @endcomponent
            </div>
        </div>

        @include('hms::front_desk.partials.booking_table', [
            'title' => __('hms::lang.arrivals_today'),
            'bookings' => $arrivals,
            'action' => 'check_in',
        ])

        @include('hms::front_desk.partials.booking_table', [
            'title' => __('hms::lang.in_house_and_departures'),
            'bookings' => $inHouse,
            'action' => 'check_out',
        ])

        @if($noShowCandidates->isNotEmpty())
            @include('hms::front_desk.partials.booking_table', [
                'title' => __('hms::lang.no_show_review'),
                'bookings' => $noShowCandidates,
                'action' => 'no_show',
            ])
        @endif
    </section>
@endsection
