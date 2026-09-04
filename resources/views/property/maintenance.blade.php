@extends('layouts.app')
@section('title', 'Property Maintenance')

@section('content')
<section class="content-header">
    <h1>Property Maintenance <small>Repairs, suppliers, and actual costs</small></h1>
</section>

<section class="content">
    <div class="tw-mb-3">
        <a class="btn btn-default" href="{{ route('property.index') }}">
            <i class="fa fa-dashboard"></i> Property Dashboard
        </a>
        <a class="btn btn-default" href="{{ route('property.reports') }}">
            <i class="fa fa-bar-chart"></i> Reports
        </a>
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Portfolio scope</h3>
        </div>
        <div class="box-body">
            {!! Form::open(['route' => 'property.maintenance', 'method' => 'get', 'class' => 'form-inline']) !!}
                @include('property.partials.portfolio_filters')
                <button class="btn btn-default"><i class="fa fa-filter"></i> Apply</button>
            {!! Form::close() !!}
            <p class="help-block">Results include only locations permitted for your user account.</p>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Maintenance tickets</h3>
            <div class="box-tools pull-right">
                @can('property.maintenance.manage')
                <a class="btn btn-primary btn-sm" href="{{ route('property.maintenance.create') }}">
                    <i class="fa fa-plus"></i> Add ticket
                </a>
                @endcan
            </div>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Reported</th>
                        <th>Property / Unit</th>
                        <th>Title</th>
                        <th>Priority</th>
                        <th>Vendor</th>
                        <th>Estimated</th>
                        <th>Actual</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($tickets as $ticket)
                        <tr>
                            <td>{{ @format_date($ticket->reported_on) }}</td>
                            <td>
                                <small class="text-muted">
                                    {{ optional(optional(optional($ticket->unit)->property)->businessLocation)->name ?: 'Unassigned location' }}
                                </small><br>
                                {{ optional(optional($ticket->unit)->property)->name }}
                                /
                                {{ optional($ticket->unit)->unit_code }}
                            </td>
                            <td>{{ $ticket->title }}</td>
                            <td>{{ ucfirst($ticket->priority) }}</td>
                            <td>{{ optional($ticket->vendor)->name }}</td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $ticket->estimated_cost }}
                                </span>
                            </td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $ticket->actual_cost }}
                                </span>
                            </td>
                            <td>
                                <span class="label {{ $ticket->status === 'completed' ? 'bg-green' : 'bg-yellow' }}">
                                    {{ ucfirst(str_replace('_', ' ', $ticket->status)) }}
                                </span>
                            </td>
                            <td>
                                @if($ticket->status !== 'completed' && auth()->user()->can('property.maintenance.manage'))
                                    <a
                                        class="btn btn-xs btn-success"
                                        href="{{ route('property.maintenance.complete', $ticket) }}"
                                    >
                                        Complete
                                    </a>
                                @elseif($ticket->status === 'completed')
                                    {{ @format_date($ticket->resolved_on) }}
                                @else
                                    &mdash;
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="text-center">No maintenance tickets added yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
