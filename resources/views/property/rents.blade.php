@extends('layouts.app')
@section('title', 'Rent Collection')

@section('content')
<section class="content-header">
    <h1>Rent Collection <small>Monthly rent dues and receipts</small></h1>
</section>

<section class="content">
    <div class="tw-mb-3">
        <a class="btn btn-default" href="{{ route('property.index') }}">
            <i class="fa fa-dashboard"></i> Property Dashboard
        </a>
        <a class="btn btn-default" href="{{ route('property.reports') }}">
            <i class="fa fa-bar-chart"></i> Reports
        </a>
        @can('property.accounting.manage')
            <a class="btn btn-default" href="{{ route('property.accounting.settings') }}">
                <i class="fa fa-cogs"></i> Accounting Settings
            </a>
        @endcan
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Portfolio scope</h3>
        </div>
        <div class="box-body">
            {!! Form::open(['route' => 'property.rents', 'method' => 'get', 'class' => 'form-inline']) !!}
                @include('property.partials.portfolio_filters')
                <button class="btn btn-default"><i class="fa fa-filter"></i> Apply</button>
            {!! Form::close() !!}
            <p class="help-block">Results include only locations permitted for your user account.</p>
        </div>
    </div>

    @can('property.rent.manage')
    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Generate monthly rent dues</h3>
        </div>
        <div class="box-body">
            {!! Form::open(['route' => 'property.rents.generate', 'class' => 'form-inline']) !!}
                <input type="hidden" name="location_id" value="{{ $selectedLocationId }}">
                <input type="hidden" name="property_id" value="{{ $selectedPropertyId }}">
                <div class="form-group">
                    {!! Form::label('month', 'Month:') !!}
                    {!! Form::month('month', now()->format('Y-m'), [
                        'class' => 'form-control',
                        'required',
                    ]) !!}
                </div>
                <button class="btn btn-primary">Generate dues for this scope</button>
            {!! Form::close() !!}
            <p class="help-block">
                Running the same month again is safe; each active lease in the selected scope receives only one due per month.
            </p>
        </div>
    </div>
    @endcan

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Rent dues</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Due date</th>
                        <th>Property / Unit</th>
                        <th>Tenant</th>
                        <th>Due</th>
                        <th>Paid</th>
                        <th>Balance</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($dues as $due)
                        <tr>
                            <td>{{ @format_date($due->due_date) }}</td>
                            <td>
                                <small class="text-muted">
                                    {{ optional(optional(optional(optional($due->lease)->unit)->property)->businessLocation)->name ?: 'Unassigned location' }}
                                </small><br>
                                {{ optional(optional(optional($due->lease)->unit)->property)->name }}
                                /
                                {{ optional(optional($due->lease)->unit)->unit_code }}
                            </td>
                            <td>{{ optional(optional($due->lease)->tenant)->name }}</td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $due->amount_due }}
                                </span>
                            </td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $due->amount_paid }}
                                </span>
                            </td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $due->amount_due - $due->amount_paid }}
                                </span>
                            </td>
                            <td>
                                <span class="label {{ $due->status === 'paid' ? 'bg-green' : ($due->status === 'partial' ? 'bg-yellow' : 'bg-red') }}">
                                    {{ ucfirst($due->status) }}
                                </span>
                            </td>
                            <td>
                                @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.create') || auth()->user()->hasRole('Admin#'.session('business.id'))))
                                    <a class="btn btn-xs btn-info" href="{{ route('smart-documents.create', ['source_type' => 'property_rent_due', 'source_id' => $due->id]) }}"><i class="fa fa-file-text-o"></i> Rent invoice</a>
                                    @foreach($due->payments as $payment)
                                        <a class="btn btn-xs btn-default" href="{{ route('smart-documents.create', ['source_type' => 'property_rent_payment', 'source_id' => $payment->id]) }}"><i class="fa fa-file-o"></i> Receipt {{ $loop->iteration }}</a>
                                    @endforeach
                                @endif
                                @if($due->amount_paid < $due->amount_due && auth()->user()->can('property.rent.manage'))
                                    <a
                                        class="btn btn-xs btn-primary"
                                        href="{{ route('property.rents.payment.create', $due) }}"
                                    >
                                        Record payment
                                    </a>
                                @elseif(!app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') || (!auth()->user()->can('smart_documents.create') && !auth()->user()->hasRole('Admin#'.session('business.id'))))
                                    &mdash;
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No rent dues generated yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
