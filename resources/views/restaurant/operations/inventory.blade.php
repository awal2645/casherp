@extends('layouts.app')
@section('title', 'Restaurant Ingredient Ledger')
@section('content')
<section class="content-header"><h1>Ingredient ledger <small>recipe consumption, reversal and waste audit</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="row"><div class="col-md-4"><div class="box box-warning"><div class="box-header"><h3 class="box-title">Record waste</h3></div>
        <form method="post" action="{{ route('restaurant-operations.inventory.waste') }}">@csrf<div class="box-body">
            <div class="form-group"><label>Location *</label>{!! Form::select('location_id',$locations,null,['class'=>'form-control select2','placeholder'=>'Select location','required']) !!}</div>
            <div class="form-group"><label>Ingredient product *</label>{!! Form::select('ingredient_product_id',$ingredients->pluck('name','id'),null,['class'=>'form-control select2','placeholder'=>'Select ingredient','required']) !!}</div>
            <div class="form-group"><label>Ingredient variation *</label><select name="ingredient_variation_id" class="form-control select2" required><option value="">Select variation</option>@foreach($variations as $variation)<option value="{{ $variation->id }}" data-product="{{ $variation->product_id }}">{{ $variation->product_name }} - {{ $variation->name }} ({{ $variation->sub_sku }})</option>@endforeach</select></div>
            <div class="form-group"><label>Quantity *</label><input name="quantity" type="number" min="0.0001" step="0.0001" class="form-control" required></div>
            <div class="form-group"><label>Reason *</label><textarea name="reason" class="form-control" maxlength="2000" required></textarea></div>
        </div><div class="box-footer"><button class="btn btn-warning">Record waste</button></div></form>
    </div></div><div class="col-md-8">
        <div class="box"><div class="box-header"><h3 class="box-title">Movement summary</h3></div><div class="box-body"><div class="row">@foreach($summary as $item)<div class="col-sm-4"><strong>{{ ucfirst($item->movement_type) }}</strong><br>{{ number_format(abs((float)$item->quantity),4) }} units · {{ number_format((float)$item->total_cost,2) }}</div>@endforeach</div></div></div>
        <div class="box"><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Date</th><th>Location</th><th>Variation</th><th>Type</th><th>Quantity</th><th>Cost</th><th>Reason</th></tr></thead><tbody>
        @forelse($movements as $movement)<tr><td>{{ $movement->created_at }}</td><td>{{ $locations[$movement->location_id] ?? $movement->location_id }}</td><td>#{{ $movement->ingredient_variation_id }}</td><td>{{ ucfirst($movement->movement_type) }}</td><td>{{ (float)$movement->quantity }}</td><td>{{ number_format((float)$movement->total_cost,2) }}</td><td>{{ $movement->reason }}</td></tr>@empty<tr><td colspan="7" class="text-center">No ingredient movements yet.</td></tr>@endforelse
        </tbody></table>{{ $movements->links() }}</div></div>
    </div></div>
</section>
@endsection
