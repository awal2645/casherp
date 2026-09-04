@extends('layouts.app')
@section('title', 'Property Management')

@section('content')
<section class="content-header">
    <h1>Property Management <small>Properties, units, leases, rent, and maintenance</small></h1>
</section>

<section class="content">
    <div class="tw-mb-3">
        <a class="btn btn-primary" href="{{ route('property.rents') }}">
            <i class="fa fa-money"></i> Rent Collection
        </a>
        <a class="btn btn-default" href="{{ route('property.maintenance') }}">
            <i class="fa fa-wrench"></i> Maintenance
        </a>
        <a class="btn btn-default" href="{{ route('property.reports') }}">
            <i class="fa fa-bar-chart"></i> Reports
        </a>
        @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.view') || auth()->user()->hasRole('Admin#'.session('business.id'))))
            <a class="btn btn-default" href="{{ route('smart-documents.index', ['scenario_code' => 'rental']) }}"><i class="fa fa-file-text-o"></i> Rental documents</a>
        @endif
        @can('property.accounting.manage')
            <a class="btn btn-default" href="{{ route('property.accounting.settings') }}">
                <i class="fa fa-cogs"></i> Accounting Settings
            </a>
        @endcan
    </div>

    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-building"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Occupied / Total Units</span>
                    <span class="info-box-number">
                        {{ $summary['occupied'] }} / {{ $summary['units'] }}
                    </span>
                    <small>{{ $summary['vacant'] }} vacant</small>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Rent Collected</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['rent_paid'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-red">
                    <i class="fa fa-exclamation-triangle"></i>
                </span>
                <div class="info-box-content">
                    <span class="info-box-text">Overdue Rent</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['rent_overdue'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-wrench"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Open Maintenance</span>
                    <span class="info-box-number">{{ $summary['maintenance_open'] }}</span>
                    <small>
                        Cost:
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['maintenance_cost'] }}
                        </span>
                    </small>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Properties</h3>
            <div class="box-tools pull-right">
                @can('property.manage')
                <a class="btn btn-primary btn-sm" href="{{ route('property.properties.create') }}">
                    <i class="fa fa-plus"></i> Add property
                </a>
                @endcan
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Type</th>
                        <th>Address</th>
                        <th>Units</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($properties as $property)
                        <tr>
                            <td>{{ $property->name }}</td>
                            <td>{{ ucfirst($property->type) }}</td>
                            <td>{{ $property->address }}</td>
                            <td>{{ $property->units_count }}</td>
                            <td>
                                @can('property.manage')
                                <a
                                    class="btn btn-xs btn-default"
                                    href="{{ route('property.units.create', $property) }}"
                                >
                                    Add unit
                                </a>
                                @else
                                    &mdash;
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="text-center">No properties added yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Recent leases</h3>
            <div class="box-tools pull-right">
                @can('property.manage')
                <a class="btn btn-primary btn-sm" href="{{ route('property.leases.create') }}">
                    <i class="fa fa-plus"></i> Add lease
                </a>
                @endcan
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Unit</th>
                        <th>Tenant</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Monthly rent</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($leases as $lease)
                        <tr>
                            <td>
                                {{ optional(optional($lease->unit)->property)->name }}
                                /
                                {{ optional($lease->unit)->unit_code }}
                            </td>
                            <td>{{ optional($lease->tenant)->name }}</td>
                            <td>{{ @format_date($lease->start_date) }}</td>
                            <td>
                                {{ $lease->end_date ? @format_date($lease->end_date) : '-' }}
                            </td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $lease->monthly_rent }}
                                </span>
                            </td>
                            <td>{{ ucfirst($lease->status) }}</td>
                            <td>
                                @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.create') || auth()->user()->hasRole('Admin#'.session('business.id'))))
                                    <a class="btn btn-xs btn-info" href="{{ route('smart-documents.create', ['source_type' => 'property_lease', 'source_id' => $lease->id]) }}"><i class="fa fa-file-text-o"></i> Lease agreement</a>
                                @endif
                                @if($lease->status === 'active' && auth()->user()->can('property.manage'))
                                    {!! Form::open([
                                        'route' => ['property.leases.end', $lease],
                                        'class' => 'form-inline',
                                        'style' => 'display:inline-flex; gap:4px;',
                                    ]) !!}
                                        {!! Form::date('end_date', now()->toDateString(), [
                                            'class' => 'form-control input-sm',
                                            'required',
                                        ]) !!}
                                        <button
                                            class="btn btn-xs btn-warning"
                                            onclick="return confirm('End this lease and make the unit vacant?')"
                                        >
                                            End lease
                                        </button>
                                    {!! Form::close() !!}
                                @elseif(!app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') || (!auth()->user()->can('smart_documents.create') && !auth()->user()->hasRole('Admin#'.session('business.id'))))
                                    &mdash;
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">No leases added yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
