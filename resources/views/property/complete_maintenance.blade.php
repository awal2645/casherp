@extends('layouts.app')
@section('title', 'Complete Maintenance')

@section('content')
<section class="content-header">
    <h1>Complete Maintenance <small>{{ $ticket->title }}</small></h1>
</section>

<section class="content">
    <div class="box box-primary">
        <div class="box-body">
            <p>
                <strong>Property / Unit:</strong>
                {{ optional(optional($ticket->unit)->property)->name }}
                /
                {{ optional($ticket->unit)->unit_code }}
            </p>
            @if($ticket->description)
                <p>{{ $ticket->description }}</p>
            @endif
        </div>

        {!! Form::open(['route' => ['property.maintenance.complete.store', $ticket]]) !!}
        <div class="box-body row">
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('actual_cost', 'Actual cost:*') !!}
                    {!! Form::text('actual_cost', $ticket->estimated_cost, [
                        'class' => 'form-control input_number',
                        'required',
                    ]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label('resolved_on', 'Completion date:*') !!}
                    {!! Form::date('resolved_on', now()->toDateString(), [
                        'class' => 'form-control',
                        'required',
                    ]) !!}
                </div>
            </div>
            <div class="col-md-4">
                <div class="form-group">
                    {!! Form::label(
                        'payment_account_id',
                        $autoPost ? 'Cash / bank / payable account:*' : 'Cash / bank / payable account:'
                    ) !!}
                    {!! Form::select('payment_account_id', $accounts, null, [
                        'class' => 'form-control select2',
                        'placeholder' => 'Select the credited account',
                    ]) !!}
                    <p class="help-block">
                        @if($autoPost)
                            Required when the actual cost is above zero.
                        @else
                            Optional until automatic Property Accounting posting is enabled.
                        @endif
                    </p>
                </div>
            </div>
        </div>
        <div class="box-footer">
            <a href="{{ route('property.maintenance') }}" class="btn btn-default">Cancel</a>
            <button class="btn btn-success pull-right">Complete maintenance</button>
        </div>
        {!! Form::close() !!}
    </div>
</section>
@endsection
