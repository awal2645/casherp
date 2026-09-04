@extends('layouts.app')
@section('title', 'Premium Module Pricing')

@section('content')
@include('superadmin::layouts.nav')
<section class="content-header"><h1>Premium Module Pricing <small>Prices, allowances, capacity blocks, and approvals</small></h1></section>
<section class="content">
    @if($errors->any())<div class="alert alert-danger"><ul class="tw-mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Add premium module plan</h3></div>
        {!! Form::open(['route' => 'superadmin.premium-modules.store']) !!}
        <div class="box-body row">
            <div class="col-md-3"><div class="form-group"><label>Feature</label>{!! Form::select('feature_id', $features, null, ['class'=>'form-control select2','required','placeholder'=>'Select feature']) !!}</div></div>
            <div class="col-md-3"><div class="form-group"><label>Code</label><input class="form-control" name="code" required maxlength="100" placeholder="module_code"></div></div>
            <div class="col-md-3"><div class="form-group"><label>Name</label><input class="form-control" name="name" required maxlength="255"></div></div>
            <div class="col-md-3"><div class="form-group"><label>Module price ({{ $currency->code }})</label><input class="form-control" type="number" step="0.01" min="0" name="module_price" value="0" required></div></div>
            <div class="col-md-3"><div class="form-group"><label>Billing</label><select class="form-control" name="billing_interval" required><option value="month">Monthly</option><option value="year">Yearly</option><option value="one_time">One time</option></select></div></div>
            <div class="col-md-3"><div class="form-group"><label>Interval count</label><input class="form-control" type="number" min="1" max="120" name="billing_interval_count" value="1" required></div></div>
            <div class="col-md-3"><div class="form-group"><label>Allowance name</label><input class="form-control" name="allowance_name" value="uses" required></div></div>
            <div class="col-md-3"><div class="form-group"><label>Included allowance</label><input class="form-control" type="number" min="0" name="included_allowance" value="0" required><span class="help-block">0 means unlimited after entitlement.</span></div></div>
            <div class="col-md-3"><div class="form-group"><label>Allowance reset</label><select class="form-control" name="allowance_reset"><option value="monthly">Monthly</option><option value="subscription">Per subscription</option><option value="never">Capacity, never resets</option></select></div></div>
            <div class="col-md-3"><div class="form-group"><label>Capacity block</label><input class="form-control" type="number" min="0" name="capacity_increment" value="0" required></div></div>
            <div class="col-md-3"><div class="form-group"><label>Block price ({{ $currency->code }})</label><input class="form-control" type="number" step="0.01" min="0" name="capacity_price" value="0" required></div></div>
            <div class="col-md-3"><div class="form-group"><label>Sort order</label><input class="form-control" type="number" min="0" name="sort_order" value="0"></div></div>
            <div class="col-md-9"><div class="form-group"><label>Description</label><input class="form-control" name="description" maxlength="3000"></div></div>
            <div class="col-md-3"><div class="checkbox"><label><input type="checkbox" name="is_active" value="1"> Publish on pricing and upgrades</label></div></div>
        </div>
        <div class="box-footer"><button class="btn btn-primary pull-right">Create module plan</button></div>
        {!! Form::close() !!}
    </div>

    @foreach($plans as $plan)
        <div class="box {{ $plan->is_active ? 'box-success' : 'box-default' }}">
            <div class="box-header with-border"><h3 class="box-title">{{ $plan->name }} <small>{{ $plan->code }}</small></h3><span class="label {{ $plan->is_active ? 'bg-green' : 'bg-gray' }} pull-right">{{ $plan->is_active ? 'Published' : 'Draft' }}</span></div>
            <div class="box-body">
                <div class="row">
                    <div class="col-md-7">
                        {!! Form::open(['route'=>['superadmin.premium-modules.update',$plan],'method'=>'put']) !!}
                        <div class="row">
                            <input type="hidden" name="feature_id" value="{{ $plan->feature_id }}"><input type="hidden" name="code" value="{{ $plan->code }}">
                            <div class="col-md-6"><div class="form-group"><label>Name</label><input class="form-control" name="name" value="{{ $plan->name }}" required></div></div>
                            <div class="col-md-6"><div class="form-group"><label>Allowance label</label><input class="form-control" name="allowance_name" value="{{ $plan->allowance_name }}" required></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Module price</label><input class="form-control" type="number" step="0.01" min="0" name="module_price" value="{{ $plan->module_price }}" required></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Billing</label><select class="form-control" name="billing_interval">@foreach(['month'=>'Monthly','year'=>'Yearly','one_time'=>'One time'] as $value=>$label)<option value="{{ $value }}" {{ $plan->billing_interval===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Interval</label><input class="form-control" type="number" min="1" max="120" name="billing_interval_count" value="{{ $plan->billing_interval_count }}" required></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Included allowance</label><input class="form-control" type="number" min="0" name="included_allowance" value="{{ $plan->included_allowance }}" required></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Reset</label><select class="form-control" name="allowance_reset">@foreach(['monthly'=>'Monthly','subscription'=>'Subscription','never'=>'Never'] as $value=>$label)<option value="{{ $value }}" {{ $plan->allowance_reset===$value?'selected':'' }}>{{ $label }}</option>@endforeach</select></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Sort</label><input class="form-control" type="number" min="0" name="sort_order" value="{{ $plan->sort_order }}"></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Capacity block</label><input class="form-control" type="number" min="0" name="capacity_increment" value="{{ $plan->capacity_increment }}" required></div></div>
                            <div class="col-md-4"><div class="form-group"><label>Block price</label><input class="form-control" type="number" step="0.01" min="0" name="capacity_price" value="{{ $plan->capacity_price }}" required></div></div>
                            <div class="col-md-4"><div class="checkbox"><label><input type="checkbox" name="is_active" value="1" {{ $plan->is_active?'checked':'' }}> Published</label></div></div>
                            <div class="col-md-12"><div class="form-group"><label>Description</label><textarea class="form-control" name="description" rows="2">{{ $plan->description }}</textarea></div></div>
                        </div><button class="btn btn-primary">Save pricing</button>{!! Form::close() !!}
                    </div>
                    <div class="col-md-5">
                        <h4>Included in packages</h4>
                        {!! Form::open(['route'=>['superadmin.premium-modules.packages.update',$plan],'method'=>'put']) !!}
                        @foreach($packages as $package)
                            @php($included = $plan->packages->firstWhere('id',$package->id))
                            <div class="row tw-mb-2"><div class="col-xs-7"><label><input type="checkbox" name="packages[{{ $package->id }}][included]" value="1" {{ $included?'checked':'' }}> {{ $package->name }}</label></div><div class="col-xs-5"><input class="form-control input-sm" type="number" min="0" name="packages[{{ $package->id }}][allowance]" value="{{ $included ? $included->pivot->included_allowance_override : '' }}" placeholder="Default"></div></div>
                        @endforeach
                        <button class="btn btn-default btn-sm">Save package allowances</button>
                        {!! Form::close() !!}
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <div class="box box-warning">
        <div class="box-header with-border"><h3 class="box-title">Module purchase requests</h3></div>
        <div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Date</th><th>Company</th><th>Module</th><th>Type</th><th>Capacity</th><th>Amount</th><th>Reference</th><th>Status / action</th></tr></thead><tbody>
        @forelse($orders as $order)<tr><td>{{ @format_datetime($order->created_at) }}</td><td>{{ optional($order->business)->name }}</td><td>{{ optional($order->plan)->name }}</td><td>{{ ucfirst($order->order_type) }}</td><td>{{ number_format($order->allowance_quantity) }}</td><td>{{ $order->currency }} {{ number_format((float)$order->total_price,2) }}</td><td>{{ $order->payment_reference ?: '-' }}</td><td>
            <span class="label {{ $order->status==='approved'?'bg-green':($order->status==='pending'?'bg-yellow':'bg-gray') }}">{{ ucfirst($order->status) }}</span>
            @if($order->status==='pending')
                {!! Form::open(['route'=>['superadmin.premium-module-orders.approve',$order],'style'=>'display:inline']) !!}<button class="btn btn-xs btn-success">Approve</button>{!! Form::close() !!}
                {!! Form::open(['route'=>['superadmin.premium-module-orders.decline',$order],'style'=>'display:inline']) !!}<input name="review_note" required maxlength="2000" placeholder="Decline reason"><button class="btn btn-xs btn-danger">Decline</button>{!! Form::close() !!}
            @endif
        </td></tr>@empty<tr><td colspan="8" class="text-center">No module requests.</td></tr>@endforelse
        </tbody></table></div>
    </div>
</section>
@endsection

@section('javascript')<script>$(function(){ $('.select2').select2({width:'100%'}); });</script>@endsection
