@extends('layouts.app')
@section('title', 'Premium Modules & Capacity')

@section('content')
<section class="content-header">
    <h1>Premium Modules & Capacity <small>Clear allowances and controlled upgrades</small></h1>
</section>

<section class="content">
    @if($errors->any())
        <div class="alert alert-danger"><ul class="tw-mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="alert alert-info">
        <i class="fa fa-info-circle"></i>
        Each module shows what is included, current usage, and the exact price of an additional capacity block. Requests are recorded for billing review; no capacity is silently changed.
    </div>

    <div class="row">
        @forelse($catalog as $item)
            @php($plan = $item['plan'])
            <div class="col-md-6 col-lg-4">
                <div class="box {{ $item['entitled'] ? 'box-success' : 'box-primary' }} tw-shadow-sm">
                    <div class="box-header with-border">
                        <h3 class="box-title">{{ $plan->name }}</h3>
                        <span class="label {{ $item['entitled'] ? 'bg-green' : 'bg-gray' }} pull-right">
                            {{ $item['entitled'] ? 'Active' : 'Available' }}
                        </span>
                    </div>
                    <div class="box-body">
                        <p class="text-muted">{{ $plan->description }}</p>
                        <p><strong>{{ $plan->allowance_name }}:</strong>
                            {{ $item['unlimited'] ? 'Unlimited' : number_format($item['allowance']) }}
                            @if($plan->allowance_reset === 'monthly') per month @endif
                        </p>
                        @if($item['entitled'] && !$item['unlimited'])
                            @php($percentage = $item['allowance'] > 0 ? min(100, round(($item['used'] / $item['allowance']) * 100)) : 0)
                            <div class="progress tw-mb-1"><div class="progress-bar" style="width: {{ $percentage }}%"></div></div>
                            <p class="small text-muted">{{ number_format($item['used']) }} used · {{ number_format($item['remaining']) }} remaining</p>
                        @endif

                        @if(!$item['entitled'])
                            <p class="tw-text-lg"><strong><span class="display_currency" data-currency_symbol="true">{{ $plan->module_price }}</span></strong> / {{ $plan->billing_interval_count }} {{ $plan->billing_interval }}</p>
                        @elseif($plan->capacity_increment > 0)
                            <p><strong>Extra capacity:</strong> +{{ number_format($plan->capacity_increment) }} for <span class="display_currency" data-currency_symbol="true">{{ $plan->capacity_price }}</span></p>
                        @endif
                    </div>
                    <div class="box-footer">
                        @if($item['pending_order'])
                            <button class="btn btn-default btn-block" disabled><i class="fa fa-clock-o"></i> Request pending review</button>
                        @else
                            {!! Form::open(['route' => 'premium-modules.orders.store', 'method' => 'post']) !!}
                                <input type="hidden" name="premium_module_plan_id" value="{{ $plan->id }}">
                                <input type="hidden" name="order_type" value="{{ $item['entitled'] ? 'capacity' : 'module' }}">
                                @if($item['entitled'] && $plan->capacity_increment > 0)
                                    <div class="form-group">
                                        <label>Capacity blocks</label>
                                        <input class="form-control" type="number" name="increments" value="1" min="1" max="100" required>
                                    </div>
                                @else
                                    <input type="hidden" name="increments" value="1">
                                @endif
                                <div class="form-group"><input class="form-control" name="payment_reference" maxlength="255" placeholder="Payment reference (optional)"></div>
                                <button class="btn btn-primary btn-block" {{ $item['entitled'] && $plan->capacity_increment < 1 ? 'disabled' : '' }}>
                                    {{ $item['entitled'] ? 'Request extra capacity' : 'Request module' }}
                                </button>
                            {!! Form::close() !!}
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="col-xs-12"><div class="well text-center">No premium modules are currently offered.</div></div>
        @endforelse
    </div>

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">Upgrade request history</h3></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Date</th><th>Module</th><th>Request</th><th>Capacity</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    <tr>
                        <td>{{ @format_datetime($order->created_at) }}</td>
                        <td>{{ optional($order->plan)->name }}</td>
                        <td>{{ ucfirst($order->order_type) }}</td>
                        <td>{{ number_format($order->allowance_quantity) }} {{ optional($order->plan)->allowance_name }}</td>
                        <td>{{ $order->currency }} {{ number_format((float) $order->total_price, 2) }}</td>
                        <td><span class="label {{ $order->status === 'approved' ? 'bg-green' : ($order->status === 'pending' ? 'bg-yellow' : 'bg-gray') }}">{{ ucfirst($order->status) }}</span></td>
                        <td>
                            @if($order->status === 'pending')
                                {!! Form::open(['route' => ['premium-modules.orders.cancel', $order], 'method' => 'delete', 'style' => 'display:inline']) !!}
                                    <button class="btn btn-xs btn-default">Cancel</button>
                                {!! Form::close() !!}
                            @else&mdash;@endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center">No module upgrade requests yet.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
