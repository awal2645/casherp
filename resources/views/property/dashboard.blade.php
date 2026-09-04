@extends('layouts.app')
@section('title', 'Property Management & Real Estate')

@section('content')
<section class="content-header">
    <h1>Property Management &amp; Real Estate <small>Portfolio, tenants, leases, rent and maintenance</small></h1>
</section>

<section class="content {{ $compactMode ? 'property-dashboard-compact' : '' }}">
    <div class="box box-default">
        <div class="box-body">
            {!! Form::open(['route' => 'property.index', 'method' => 'get', 'class' => 'row', 'id' => 'property_dashboard_filters']) !!}
                <div class="col-md-4">
                    <div class="form-group tw-mb-0">
                        <label for="location_id">Location</label>
                        <select class="form-control select2" id="location_id" name="location_id">
                            <option value="">All permitted locations</option>
                            @foreach($locations as $location)
                                <option value="{{ $location->id }}" @selected((int) $selectedLocationId === (int) $location->id)>
                                    {{ $location->name }}{{ $location->city ? ' - '.$location->city : '' }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group tw-mb-0">
                        <label for="property_id">Property</label>
                        <select class="form-control select2" id="property_id" name="property_id">
                            <option value="">All properties in scope</option>
                            @foreach($propertyOptions as $propertyOption)
                                <option value="{{ $propertyOption->id }}" data-location-id="{{ $propertyOption->business_location_id }}" @selected((int) $selectedPropertyId === (int) $propertyOption->id)>
                                    {{ $propertyOption->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-4 tw-pt-6">
                    <button class="btn btn-primary" type="submit"><i class="fa fa-filter"></i> Apply scope</button>
                    <button class="btn btn-default" type="button" data-toggle="modal" data-target="#property_dashboard_settings"><i class="fa fa-sliders"></i> Customize dashboard</button>
                </div>
            {!! Form::close() !!}
        </div>
    </div>

    <div class="alert alert-info property-profile-summary" role="note">
        <div class="row">
            <div class="col-md-8">
                <strong>{{ $profile['operating_model'] }}</strong>
                <span class="tw-mx-2" aria-hidden="true">&middot;</span>
                {{ $profile['portfolio_category'] }}
                @if($profile['property_subtypes']->isNotEmpty())
                    <div class="small tw-mt-1">Configured property types: {{ $profile['property_subtypes']->implode(', ') }}</div>
                @endif
            </div>
            <div class="col-md-4 text-right">
                <span class="label label-primary">{{ $locations->count() }} permitted {{ Str::plural('location', $locations->count()) }}</span>
                <span class="label label-default">{{ $summary['properties'] }} {{ Str::plural('property', $summary['properties']) }} in scope</span>
            </div>
        </div>
    </div>

    <div class="tw-mb-3">
        <a class="btn btn-primary" href="{{ route('property.rents') }}"><i class="fa fa-money"></i> Rent Collection</a>
        <a class="btn btn-default" href="{{ route('property.maintenance') }}"><i class="fa fa-wrench"></i> Maintenance</a>
        <a class="btn btn-default" href="{{ route('property.units.index') }}"><i class="fa fa-list"></i> Units &amp; Listings</a>
        <a class="btn btn-default" href="{{ route('property.viewings.index') }}"><i class="fa fa-calendar-check-o"></i> Viewings</a>
        <a class="btn btn-default" href="{{ route('property.reports') }}"><i class="fa fa-bar-chart"></i> Reports</a>
        <a class="btn btn-default" href="{{ route('property.deposits.index') }}"><i class="fa fa-shield"></i> Deposit Register &amp; Alerts</a>
        @can('property.manage')
            <a class="btn btn-default" href="{{ route('property.properties.create') }}"><i class="fa fa-building"></i> Add Property</a>
            <a class="btn btn-default" href="{{ route('business-location.create') }}"><i class="fa fa-map-marker"></i> Add Location</a>
        @endcan
        @if(auth()->user()->can('property.access.manage') || auth()->user()->hasRole('Admin#'.session('user.business_id')) || auth()->user()->can('superadmin'))
            <a class="btn btn-default" href="{{ route('property.access.index') }}"><i class="fa fa-shield"></i> Property Access</a>
        @endif
        @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.view') || auth()->user()->hasRole('Admin#'.session('user.business_id')) || auth()->user()->can('superadmin')))
            <a class="btn btn-default" href="{{ route('smart-documents.index', ['scenario_code' => 'rental']) }}"><i class="fa fa-file-text-o"></i> Property Documents</a>
        @endif
    </div>

    @if(in_array('portfolio_summary', $visibleWidgets, true))
    <div class="row">
        @foreach([
            ['icon' => 'fa-building', 'color' => 'bg-aqua', 'label' => 'Properties', 'value' => $summary['properties'], 'note' => $locations->count().' locations available'],
            ['icon' => 'fa-home', 'color' => 'bg-green', 'label' => 'Occupancy', 'value' => $summary['occupancy_rate'].'%', 'note' => $summary['occupied'].' of '.$summary['units'].' units'],
            ['icon' => 'fa-users', 'color' => 'bg-purple', 'label' => 'Active Tenants', 'value' => $summary['active_tenants'], 'note' => $summary['vacant'].' vacant units'],
            ['icon' => 'fa-calendar', 'color' => 'bg-yellow', 'label' => 'Leases Expiring', 'value' => $summary['leases_expiring'], 'note' => 'Next 60 days'],
        ] as $card)
        <div class="col-md-3 col-sm-6">
            <div class="info-box">
                <span class="info-box-icon {{ $card['color'] }}"><i class="fa {{ $card['icon'] }}"></i></span>
                <div class="info-box-content">
                    <span class="info-box-text">{{ $card['label'] }}</span>
                    <span class="info-box-number">{{ $card['value'] }}</span>
                    <small>{{ $card['note'] }}</small>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    @endif

    @if(in_array('rent_performance', $visibleWidgets, true))
    <div class="row">
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><span class="info-box-icon bg-blue"><i class="fa fa-file-text-o"></i></span><div class="info-box-content"><span class="info-box-text">Rent Billed This Month</span><span class="info-box-number"><span class="display_currency" data-currency_symbol="true">{{ $summary['rent_billed'] }}</span></span><small>Selected portfolio scope</small></div></div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><span class="info-box-icon bg-green"><i class="fa fa-money"></i></span><div class="info-box-content"><span class="info-box-text">Rent Collected</span><span class="info-box-number"><span class="display_currency" data-currency_symbol="true">{{ $summary['rent_paid'] }}</span></span><small>{{ $summary['collection_rate'] }}% collection rate</small></div></div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><span class="info-box-icon bg-red"><i class="fa fa-exclamation-triangle"></i></span><div class="info-box-content"><span class="info-box-text">Overdue Rent</span><span class="info-box-number"><span class="display_currency" data-currency_symbol="true">{{ $summary['rent_overdue'] }}</span></span><small>Past due and unpaid</small></div></div>
        </div>
        <div class="col-md-3 col-sm-6">
            <div class="info-box"><span class="info-box-icon bg-yellow"><i class="fa fa-wrench"></i></span><div class="info-box-content"><span class="info-box-text">Open Maintenance</span><span class="info-box-number">{{ $summary['maintenance_open'] }}</span><small>Open or in progress</small></div></div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Six-Month Rent Performance</h3></div>
                <div class="box-body table-responsive">
                    <table class="table table-condensed property-performance-table">
                        <thead><tr><th>Month</th><th>Billed</th><th>Collected</th><th>Collection</th></tr></thead>
                        <tbody>
                        @foreach($rentChart as $month)
                            @php($rate = $month['billed'] > 0 ? min(100, round(($month['collected'] / $month['billed']) * 100, 1)) : 0)
                            <tr>
                                <td>{{ $month['label'] }}</td>
                                <td><span class="display_currency" data-currency_symbol="true">{{ $month['billed'] }}</span></td>
                                <td><span class="display_currency" data-currency_symbol="true">{{ $month['collected'] }}</span></td>
                                <td style="min-width:180px"><div class="progress progress-xs tw-mb-1"><div class="progress-bar progress-bar-success" style="width:{{ $rate }}%"></div></div><small>{{ $rate }}%</small></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @if(in_array('receivables_ageing', $visibleWidgets, true))
        <div class="col-md-4">
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Receivables Ageing</h3></div>
                <div class="box-body">
                    @foreach(['current' => 'Current / not yet due', '1_30' => '1-30 days overdue', '31_60' => '31-60 days overdue', '61_plus' => '61+ days overdue'] as $key => $label)
                        <div class="clearfix tw-py-2 tw-border-b tw-border-gray-100"><span>{{ $label }}</span><strong class="pull-right display_currency" data-currency_symbol="true">{{ $receivablesAgeing[$key] }}</strong></div>
                    @endforeach
                </div>
            </div>
        </div>
        @endif
    </div>
    @endif

    <div class="row">
        @if(in_array('unit_status', $visibleWidgets, true))
        <div class="col-md-4">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Unit Status</h3></div>
                <div class="box-body">
                    @forelse($unitStatus as $status => $total)
                        @php($percent = $summary['units'] > 0 ? round(($total / $summary['units']) * 100, 1) : 0)
                        <div class="tw-mb-3"><span class="text-capitalize">{{ str_replace('_', ' ', $status) }}</span><strong class="pull-right">{{ $total }} <small>({{ $percent }}%)</small></strong><div class="progress progress-xs tw-mt-1"><div class="progress-bar progress-bar-primary" style="width:{{ $percent }}%"></div></div></div>
                    @empty
                        <p class="text-muted text-center">Add properties and units to see availability.</p>
                    @endforelse
                </div>
            </div>
        </div>
        @endif

        @if(in_array('lease_expiries', $visibleWidgets, true))
        <div class="col-md-4">
            <div class="box box-warning">
                <div class="box-header with-border"><h3 class="box-title">Upcoming Lease Expiries</h3></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-condensed">
                        <tbody>
                        @forelse($expiringLeases as $lease)
                            <tr><td><strong>{{ optional($lease->tenant)->name ?: 'Unassigned tenant' }}</strong><br><small>{{ optional(optional($lease->unit)->property)->name }} / {{ optional($lease->unit)->unit_code }}</small></td><td class="text-right">{{ @format_date($lease->end_date) }}</td></tr>
                        @empty
                            <tr><td class="text-center text-muted">No leases expire in the next 90 days.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if(in_array('maintenance', $visibleWidgets, true))
        <div class="col-md-4">
            <div class="box box-danger">
                <div class="box-header with-border"><h3 class="box-title">Maintenance Priorities</h3><div class="box-tools"><a href="{{ route('property.maintenance') }}">View all</a></div></div>
                <div class="box-body table-responsive no-padding">
                    <table class="table table-condensed">
                        <tbody>
                        @forelse($maintenanceTickets as $ticket)
                            <tr><td><strong>{{ $ticket->title }}</strong><br><small>{{ optional(optional($ticket->unit)->property)->name }} / {{ optional($ticket->unit)->unit_code }}</small></td><td><span class="label {{ in_array($ticket->priority, ['urgent', 'high']) ? 'label-danger' : 'label-warning' }}">{{ ucfirst($ticket->priority) }}</span></td></tr>
                        @empty
                            <tr><td class="text-center text-muted">No open maintenance work.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif
    </div>

    @if(in_array('properties', $visibleWidgets, true))
    <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Property Portfolio</h3><div class="box-tools pull-right">@can('property.manage')<a class="btn btn-primary btn-sm" href="{{ route('property.properties.create') }}"><i class="fa fa-plus"></i> Add property</a>@endcan</div></div>
        <div class="box-body table-responsive">
            <table class="table table-bordered table-striped">
                <thead><tr><th>Property</th><th>Location</th><th>Category</th><th>Subtype</th><th>Occupancy</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($properties as $property)
                    @php($propertySubtype = data_get(config('property_management.property_subtypes'), $property->property_subtype.'.label', ucfirst(str_replace('_', ' ', $property->property_subtype ?: $property->type))))
                    <tr>
                        <td><strong>{{ $property->name }}</strong><br><small>{{ $property->address }}</small>@if(!empty($property->amenities))<br><small class="text-muted">{{ collect($property->amenities)->take(4)->implode(', ') }}</small>@endif</td>
                        <td>{{ optional($property->businessLocation)->name ?: 'Unassigned' }}</td>
                        <td>{{ data_get(config('property_management.portfolio_categories'), $property->portfolio_category, ucfirst(str_replace('_', ' ', $property->type))) }}</td>
                        <td>{{ $propertySubtype }}</td>
                        <td>{{ $property->occupied_units_count }} / {{ $property->units_count }} <small>({{ $property->units_count ? round(($property->occupied_units_count / $property->units_count) * 100, 1) : 0 }}%)</small></td>
                        <td>@can('property.manage')<a class="btn btn-xs btn-default" href="{{ route('property.units.create', $property) }}">Add unit</a>@else&mdash;@endcan</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted">No properties exist in this location and portfolio scope.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif

    <div class="row">
        @if(in_array('recent_leases', $visibleWidgets, true))
        <div class="col-md-8">
            <div class="box box-primary">
                <div class="box-header with-border"><h3 class="box-title">Recent and Active Leases</h3><div class="box-tools pull-right">@can('property.manage')<a class="btn btn-primary btn-sm" href="{{ route('property.leases.create') }}"><i class="fa fa-plus"></i> Add lease</a>@endcan</div></div>
                <div class="box-body table-responsive">
                    <table class="table table-bordered table-striped">
                        <thead><tr><th>Property / Unit</th><th>Tenant</th><th>Lease Period</th><th>Monthly Rent</th><th>Status</th><th>Action</th></tr></thead>
                        <tbody>
                        @forelse($leases as $lease)
                            <tr>
                                <td>{{ optional(optional($lease->unit)->property)->name }} / {{ optional($lease->unit)->unit_code }}<br><small>{{ optional(optional(optional($lease->unit)->property)->businessLocation)->name }}</small></td>
                                <td>{{ optional($lease->tenant)->name ?: 'Unassigned' }}</td>
                                <td>{{ @format_date($lease->start_date) }} - {{ $lease->end_date ? @format_date($lease->end_date) : 'Open-ended' }}</td>
                                <td><span class="display_currency" data-currency_symbol="true">{{ $lease->monthly_rent }}</span></td>
                                <td><span class="label {{ $lease->status === 'active' ? 'label-success' : 'label-default' }}">{{ ucfirst($lease->status) }}</span></td>
                                <td><a class="btn btn-xs btn-default" href="{{ route('property.deposits.show',$lease) }}"><i class="fa fa-shield"></i> Deposits</a> @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.create') || auth()->user()->hasRole('Admin#'.session('business.id'))))<a class="btn btn-xs btn-info" href="{{ route('smart-documents.create', ['source_type' => 'property_lease', 'source_id' => $lease->id]) }}"><i class="fa fa-file-text-o"></i> Agreement</a>@endif</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No leases exist in this scope.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        @endif

        @if(in_array('documents', $visibleWidgets, true))
        <div class="col-md-4">
            <div class="box box-info">
                <div class="box-header with-border"><h3 class="box-title">Property Documents</h3></div>
                <div class="box-body">
                    @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents'))
                        @foreach([
                            'rental_quotation' => 'Rental Quotation', 'lease_agreement' => 'Lease Agreement',
                            'rental_invoice' => 'Rental Invoice', 'rent_receipt' => 'Rent Receipt',
                            'security_deposit_receipt' => 'Security Deposit Receipt', 'owner_statement' => 'Owner Statement',
                        ] as $typeCode => $label)
                            <a class="btn btn-default btn-block text-left" href="{{ route('smart-documents.create', ['type_code' => $typeCode, 'scenario_code' => 'rental']) }}"><i class="fa fa-file-text-o tw-mr-2"></i> {{ $label }}</a>
                        @endforeach
                    @else
                        <p class="text-muted">Enable Smart Documents to use property-specific agreements, invoices, receipts and statements.</p>
                    @endif
                </div>
            </div>
        </div>
        @endif
    </div>
</section>

<div class="modal fade" id="property_dashboard_settings" tabindex="-1" role="dialog" aria-labelledby="property_dashboard_settings_title">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            {!! Form::open(['route' => 'property.dashboard.preferences.update', 'method' => 'put']) !!}
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="property_dashboard_settings_title">Customize My Property Dashboard</h4>
            </div>
            <div class="modal-body">
                <p class="text-muted">These settings apply only to your login in this company. They do not change another user's dashboard.</p>
                <div class="row">
                    <div class="col-md-6"><div class="form-group"><label for="default_business_location_id">Default location</label><select class="form-control" id="default_business_location_id" name="default_business_location_id"><option value="">All permitted locations</option>@foreach($locations as $location)<option value="{{ $location->id }}" @selected((int) $preference->default_business_location_id === (int) $location->id)>{{ $location->name }}</option>@endforeach</select></div></div>
                    <div class="col-md-6"><div class="form-group"><label for="default_property_id">Default property</label><select class="form-control" id="default_property_id" name="default_property_id"><option value="">All properties in location</option>@foreach($propertyOptions as $propertyOption)<option value="{{ $propertyOption->id }}" @selected((int) $preference->default_property_id === (int) $propertyOption->id)>{{ $propertyOption->name }}</option>@endforeach</select></div></div>
                </div>
                <fieldset>
                    <legend class="h5">Visible dashboard sections</legend>
                    @foreach($availableWidgets as $key => $widget)
                        <div class="checkbox"><label><input type="checkbox" name="visible_widgets[]" value="{{ $key }}" @checked(in_array($key, $visibleWidgets, true))> <strong>{{ $widget['label'] }}</strong><br><small class="text-muted">{{ $widget['description'] }}</small></label></div>
                    @endforeach
                </fieldset>
                <input type="hidden" name="compact_mode" value="0">
                <div class="checkbox"><label><input type="checkbox" name="compact_mode" value="1" @checked($compactMode)> Use compact spacing</label></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Save my dashboard</button>
            </div>
            {!! Form::close() !!}
            <div class="tw-px-4 tw-pb-4">
                {!! Form::open(['route' => 'property.dashboard.preferences.reset', 'method' => 'delete']) !!}
                    <button class="btn btn-link text-danger tw-p-0" type="submit" onclick="return confirm('Reset your property dashboard to the recommended layout?')">Reset to recommended layout</button>
                {!! Form::close() !!}
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<style>
    .property-profile-summary { border-left: 4px solid #3c8dbc; }
    .property-dashboard-compact .box-body { padding-top: 8px; padding-bottom: 8px; }
    .property-dashboard-compact .info-box { min-height: 78px; }
    .property-dashboard-compact .info-box-icon { height: 78px; line-height: 78px; }
    .property-dashboard-compact .info-box-content { margin-left: 78px; padding-top: 6px; }
    .property-performance-table .progress { margin-bottom: 2px; }
</style>
@endsection

@section('javascript')
<script>
    $(function () {
        $('.select2').select2({ width: '100%' });

        $('#location_id').on('change', function () {
            var locationId = String($(this).val() || '');
            var property = $('#property_id');
            property.find('option').each(function () {
                var optionLocation = String($(this).data('location-id') || '');
                $(this).prop('disabled', locationId && optionLocation && optionLocation !== locationId);
            });
            if (property.find('option:selected').prop('disabled')) {
                property.val('').trigger('change.select2');
            }
        }).trigger('change');
    });
</script>
@endsection
