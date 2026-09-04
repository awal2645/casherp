@extends('layouts.app')
@section('title', 'Property Reports')

@section('content')
<section class="content-header">
    <h1>Property Reports <small>Finance, rent ageing, and Accounting audit</small></h1>
</section>

<section class="content">
    <div class="tw-mb-3">
        <a class="btn btn-default" href="{{ route('property.index') }}">
            <i class="fa fa-dashboard"></i> Property Dashboard
        </a>
        <a class="btn btn-default" href="{{ route('property.rents') }}">
            <i class="fa fa-money"></i> Rent Collection
        </a>
        <a class="btn btn-default" href="{{ route('property.maintenance') }}">
            <i class="fa fa-wrench"></i> Maintenance
        </a>
        @can('property.accounting.manage')
            <a class="btn btn-default" href="{{ route('property.accounting.settings') }}">
                <i class="fa fa-cogs"></i> Accounting Settings
            </a>
        @endcan
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Report period</h3>
        </div>
        <div class="box-body">
            {!! Form::open([
                'route' => 'property.reports',
                'method' => 'get',
                'class' => 'form-inline',
            ]) !!}
                <div class="form-group">
                    {!! Form::label('date_from', 'From:') !!}
                    {!! Form::date('date_from', $from->toDateString(), [
                        'class' => 'form-control',
                        'required',
                    ]) !!}
                </div>
                <div class="form-group">
                    {!! Form::label('date_to', 'To:') !!}
                    {!! Form::date('date_to', $to->toDateString(), [
                        'class' => 'form-control',
                        'required',
                    ]) !!}
                </div>
                @include('property.partials.portfolio_filters')
                <button class="btn btn-primary">
                    <i class="fa fa-filter"></i> Apply
                </button>
            {!! Form::close() !!}
        </div>
    </div>

    <div class="row">
        <div class="col-md-2 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-aqua"><i class="fa fa-file-text-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Rent Billed</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['billed'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-green"><i class="fa fa-money"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Collected</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['collected'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-red"><i class="fa fa-clock-o"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Outstanding</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['outstanding'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-2 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon bg-yellow"><i class="fa fa-wrench"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Maintenance</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['maintenance'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
        <div class="col-md-4 col-sm-12">
            <div class="info-box">
                <span class="info-box-icon bg-blue"><i class="fa fa-line-chart"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">Net Cash After Maintenance</span>
                    <span class="info-box-number">
                        <span class="display_currency" data-currency_symbol="true">
                            {{ $summary['net_cash'] }}
                        </span>
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="box box-primary">
        <div class="box-header with-border">
            <h3 class="box-title">Performance by property</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Property</th>
                        <th>Rent billed</th>
                        <th>Rent collected</th>
                        <th>Outstanding</th>
                        <th>Maintenance</th>
                        <th>Net cash</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($propertyRows as $row)
                        <tr>
                            <td>{{ $row['property'] }}</td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['billed'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['collected'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['outstanding'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['maintenance'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['net_cash'] }}</span></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="text-center">
                                No Property finance activity in this period.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="box box-danger">
        <div class="box-header with-border">
            <h3 class="box-title">Tenant rent ageing as at {{ @format_date($to) }}</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Tenant</th>
                        <th>Property / Unit</th>
                        <th>Current</th>
                        <th>1–30 days</th>
                        <th>31–60 days</th>
                        <th>61–90 days</th>
                        <th>Over 90 days</th>
                        <th>Total</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($ageingRows as $row)
                        <tr>
                            <td>{{ $row['tenant'] }}</td>
                            <td>{{ $row['property'] }} / {{ $row['unit'] }}</td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['current'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['days_1_30'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['days_31_60'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['days_61_90'] }}</span></td>
                            <td><span class="display_currency" data-currency_symbol="true">{{ $row['days_over_90'] }}</span></td>
                            <td><strong><span class="display_currency" data-currency_symbol="true">{{ $row['total'] }}</span></strong></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center">No outstanding tenant rent.</td>
                        </tr>
                    @endforelse
                </tbody>
                @if($ageingRows->isNotEmpty())
                    <tfoot>
                        <tr>
                            <th colspan="2">Total</th>
                            <th><span class="display_currency" data-currency_symbol="true">{{ $ageingRows->sum('current') }}</span></th>
                            <th><span class="display_currency" data-currency_symbol="true">{{ $ageingRows->sum('days_1_30') }}</span></th>
                            <th><span class="display_currency" data-currency_symbol="true">{{ $ageingRows->sum('days_31_60') }}</span></th>
                            <th><span class="display_currency" data-currency_symbol="true">{{ $ageingRows->sum('days_61_90') }}</span></th>
                            <th><span class="display_currency" data-currency_symbol="true">{{ $ageingRows->sum('days_over_90') }}</span></th>
                            <th><span class="display_currency" data-currency_symbol="true">{{ $ageingRows->sum('total') }}</span></th>
                        </tr>
                    </tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="box box-default">
        <div class="box-header with-border">
            <h3 class="box-title">Property Accounting posting audit</h3>
        </div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead>
                    <tr>
                        <th>Posted</th>
                        <th>Source</th>
                        <th>Debit account</th>
                        <th>Credit account</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($postings as $posting)
                        <tr>
                            <td>
                                {{ $posting->posted_at ? @format_datetime($posting->posted_at) : '-' }}
                            </td>
                            <td>
                                {{ ucwords(str_replace('_', ' ', $posting->source_type)) }}
                                #{{ $posting->source_id }}
                            </td>
                            <td>{{ optional($posting->debitAccount)->name }}</td>
                            <td>{{ optional($posting->creditAccount)->name }}</td>
                            <td>
                                <span class="display_currency" data-currency_symbol="true">
                                    {{ $posting->amount }}
                                </span>
                            </td>
                            <td>
                                <span class="label {{ $posting->status === 'posted' ? 'bg-green' : ($posting->status === 'reversed' ? 'bg-yellow' : 'bg-red') }}">
                                    {{ ucfirst($posting->status) }}
                                </span>
                            </td>
                            <td>
                                @can('property.accounting.manage')
                                    @if($posting->status === 'posted' && $posting->source_type !== 'reversal')
                                        {!! Form::open([
                                            'route' => ['property.accounting.postings.reverse', $posting],
                                            'style' => 'display:inline',
                                        ]) !!}
                                            <button
                                                class="btn btn-xs btn-warning"
                                                onclick="return confirm('Create balanced reversal entries for this posting?')"
                                            >
                                                Reverse ledger entry
                                            </button>
                                        {!! Form::close() !!}
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center">
                                No Property Accounting postings yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</section>
@endsection
