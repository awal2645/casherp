@extends('layouts.app')
@section('title', 'Restaurant Operations')
@section('content')
<section class="content-header">
    <h1 class="tw-text-xl md:tw-text-3xl tw-font-bold tw-text-black">Restaurant Operations <small>live service, kitchen, stock and cash controls</small></h1>
</section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <form method="get" class="form-inline tw-mb-4">
        <label for="restaurant_location">Location</label>
        {!! Form::select('location_id', $locations, $selectedLocation, ['id' => 'restaurant_location', 'class' => 'form-control', 'placeholder' => 'All permitted locations', 'onchange' => 'this.form.submit()']) !!}
    </form>
    <div class="row">
        @foreach([
            ['Active kitchen tickets', 'active_kitchen_tickets', 'bg-aqua', 'fa-fire'],
            ['Kitchen tickets beyond target', 'overdue_kitchen_tickets', 'bg-yellow', 'fa-clock-o'],
            ['Active orders', 'active_orders', 'bg-green', 'fa-cutlery'],
            ["Today's reservations", 'today_reservations', 'bg-blue', 'fa-calendar-check-o'],
            ['Open waiter requests', 'open_waiter_requests', 'bg-purple', 'fa-bell'],
            ['Registers awaiting approval', 'registers_pending_approval', 'bg-red', 'fa-balance-scale'],
        ] as [$label, $key, $colour, $icon])
            <div class="col-lg-4 col-sm-6">
                <div class="small-box {{ $colour }} tw-transition-all tw-duration-200 hover:tw-shadow-md">
                    <div class="inner"><h3>{{ number_format($metrics[$key]) }}</h3><p>{{ $label }}</p></div>
                    <div class="icon"><i class="fa {{ $icon }}"></i></div>
                </div>
            </div>
        @endforeach
    </div>
    <div class="alert alert-info">
        <strong>Operational control:</strong> every record on this page belongs to the active company and a permitted location. Hotel room service and banqueting use the same order pipeline only when Restaurant Operations is enabled for that company.
    </div>
</section>
@endsection
