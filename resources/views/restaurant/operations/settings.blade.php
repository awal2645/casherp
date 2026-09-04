@extends('layouts.app')
@section('title', 'Restaurant Operating Settings')
@section('content')
<section class="content-header"><h1>Restaurant settings <small>company-specific operational policy</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="row"><div class="col-md-8"><div class="box box-primary"><form method="post" action="{{ route('restaurant-operations.settings.update') }}">@csrf @method('PUT')
        <div class="box-body">
            <h4>Service channels</h4><p class="help-block">Industry defaults are a starting profile. Company administrators can enable the channels this company actually operates.</p>
            @foreach(config('restaurant_operations.service_channels') as $code=>$label)<label class="checkbox-inline"><input type="checkbox" name="enabled_channels[]" value="{{ $code }}" {{ in_array($code,(array)$settings->enabled_channels,true)?'checked':'' }}> {{ $label }}</label>@endforeach
            <hr><div class="row">
                <div class="col-md-6"><div class="form-group"><label>Reservation hold (minutes)</label><input class="form-control" type="number" min="0" max="10080" name="reservation_hold_minutes" value="{{ data_get($settings->reservation_policy,'hold_minutes',15) }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>Default table turn (minutes)</label><input class="form-control" type="number" min="15" max="1440" name="default_table_turn_minutes" value="{{ data_get($settings->reservation_policy,'table_turn_minutes',90) }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>Kitchen warning (minutes)</label><input class="form-control" type="number" min="1" name="kitchen_warning_minutes" value="{{ data_get($settings->kitchen_policy,'warning_minutes',15) }}" required></div></div>
                <div class="col-md-6"><div class="form-group"><label>Kitchen critical (minutes)</label><input class="form-control" type="number" min="2" name="kitchen_critical_minutes" value="{{ data_get($settings->kitchen_policy,'critical_minutes',30) }}" required></div></div>
            </div>
            <label class="checkbox"><input type="checkbox" name="require_void_reason" value="1" {{ data_get($settings->kitchen_policy,'require_void_reason',true)?'checked':'' }}> Require cancellation/void reason</label>
            <label class="checkbox"><input type="checkbox" name="require_register_variance_reason" value="1" {{ data_get($settings->register_policy,'require_variance_reason',true)?'checked':'' }}> Require register variance reason</label>
            <label class="checkbox"><input type="checkbox" name="require_register_approval" value="1" {{ data_get($settings->register_policy,'require_independent_approval',true)?'checked':'' }}> Require independent register approval</label>
            <label class="checkbox"><input type="checkbox" name="allow_negative_ingredient_stock" value="1" {{ data_get($settings->inventory_policy,'allow_negative_ingredient_stock',false)?'checked':'' }}> Allow negative ingredient stock <span class="text-warning">(not recommended)</span></label>
        </div><div class="box-footer"><button class="btn btn-primary">Save company policy</button></div>
    </form></div></div><div class="col-md-4"><div class="box"><div class="box-header"><h3 class="box-title">Industry context</h3></div><div class="box-body"><p><strong>{{ optional($business->industry)->name ?: 'Legacy company' }}</strong></p><p>Room posting: {{ !empty($profile['room_posting'])?'Available':'Not part of the default profile' }}</p><p class="help-block">These settings affect only the active company. They do not mix restaurant, hotel, staff, financial, or stock records between companies.</p></div></div></div></div>
</section>
@endsection
