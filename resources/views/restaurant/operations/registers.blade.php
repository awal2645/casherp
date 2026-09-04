@extends('layouts.app')
@section('title', 'Restaurant Register Control')
@section('content')
<section class="content-header"><h1>Register control <small>cash movements, count, variance and independent approval</small></h1></section>
<section class="content">
    @include('restaurant.operations.partials.nav')
    <div class="alert alert-info">Expected cash is calculated from CashERP register transactions plus controlled cash movements. The person submitting a count cannot approve the same reconciliation.</div>
    @can('restaurant.register.manage')
    <div class="box box-info"><div class="box-header with-border"><h3 class="box-title">Controlled cash movement</h3></div><div class="box-body">
        <form method="post" action="{{ route('restaurant-operations.registers.movements.store') }}" class="form-inline">@csrf
            {!! Form::select('cash_register_id',$registers->mapWithKeys(fn($register)=>[$register->id=>'Register #'.$register->id.' · location #'.$register->location_id]),null,['class'=>'form-control','required','placeholder'=>'Select register']) !!}
            {!! Form::select('movement_type',config('restaurant_operations.register_movement_types'),null,['class'=>'form-control','required','placeholder'=>'Movement type']) !!}
            <input type="number" name="amount" min="0.0001" step="0.0001" class="form-control" required placeholder="Amount">
            <input name="reference" maxlength="100" class="form-control" placeholder="Reference">
            <input name="reason" maxlength="2000" class="form-control" required placeholder="Required reason">
            <button class="btn btn-info">Record movement</button>
        </form>
    </div></div>
    @endcan
    <div class="box"><div class="box-header"><h3 class="box-title">Recent registers</h3><a class="btn btn-default btn-sm pull-right" href="{{ route('restaurant-operations.registers.export') }}"><i class="fa fa-download"></i> Export CSV</a></div><div class="box-body table-responsive">
        <table class="table table-bordered table-striped"><thead><tr><th>Register</th><th>Location</th><th>Status</th><th>Expected cash</th><th>Count and submit</th></tr></thead><tbody>
        @forelse($registers as $register)<tr><td>#{{ $register->id }}</td><td>#{{ $register->location_id }}</td><td>{{ ucfirst($register->status) }}</td><td>{{ number_format((float)($expectedCash[$register->id]??0),2) }}</td><td>
            @if(auth()->user()->can('restaurant.register.reconcile'))
            <form method="post" action="{{ route('restaurant-operations.registers.reconcile',$register->id) }}" class="register-count-form form-inline">@csrf
                <span class="denomination-rows"><span class="denomination-row">
                    <input class="form-control input-sm" type="number" name="counts[0][denomination]" min="0.01" step="0.01" placeholder="Denomination" required>
                    <input class="form-control input-sm" type="number" name="counts[0][quantity]" min="0" placeholder="Count" required>
                </span></span>
                <button type="button" class="btn btn-default btn-sm add-denomination" title="Add denomination"><i class="fa fa-plus"></i></button>
                <input class="form-control input-sm" name="variance_reason" placeholder="Variance reason if any">
                <button class="btn btn-primary btn-sm">Submit</button>
            </form>
            @else
                <span class="text-muted">View only</span>
            @endif
        </td></tr>@empty<tr><td colspan="5" class="text-center">No cash registers found.</td></tr>@endforelse</tbody></table>
    </div></div>
    <div class="box"><div class="box-header"><h3 class="box-title">Reconciliation review</h3></div><div class="box-body table-responsive"><table class="table table-bordered table-striped"><thead><tr><th>Register</th><th>Expected</th><th>Counted</th><th>Variance</th><th>Status</th><th>Review</th></tr></thead><tbody>
        @forelse($reconciliations as $item)<tr><td>#{{ $item->cash_register_id }}</td><td>{{ number_format((float)$item->expected_cash,2) }}</td><td>{{ number_format((float)$item->counted_cash,2) }}</td><td class="{{ abs((float)$item->variance)>0.0001?'text-danger':'' }}">{{ number_format((float)$item->variance,2) }}<br><small>{{ $item->variance_reason }}</small></td><td>{{ str_replace('_',' ',ucfirst($item->status)) }}</td><td>
            @if(auth()->user()->can('restaurant.register.approve') && $item->status === 'pending_approval')
                <form method="post" action="{{ route('restaurant-operations.registers.review',$item->id) }}" class="form-inline">@csrf @method('PATCH')<select class="form-control input-sm" name="decision" required><option value="approved">Approve</option><option value="rejected">Reject</option></select><input class="form-control input-sm" name="review_note" placeholder="Review note"><button class="btn btn-default btn-sm">Review</button></form>
            @endif
        </td></tr>
        @empty<tr><td colspan="6" class="text-center">No reconciliations submitted.</td></tr>@endforelse
    </tbody></table></div></div>
</section>
@endsection
@section('javascript')
<script>
document.addEventListener('click', function (event) {
    var button = event.target.closest('.add-denomination');
    if (!button) return;
    var form = button.closest('.register-count-form'), rows = form.querySelector('.denomination-rows'), index = rows.querySelectorAll('.denomination-row').length;
    var row = document.createElement('span'); row.className = 'denomination-row';
    row.innerHTML = ' <input class="form-control input-sm" type="number" name="counts['+index+'][denomination]" min="0.01" step="0.01" placeholder="Denomination" required> <input class="form-control input-sm" type="number" name="counts['+index+'][quantity]" min="0" placeholder="Count" required> ';
    rows.appendChild(row);
});
</script>
@endsection
