@extends('layouts.app')
@section('title', __('hms::lang.booking'))
@section('content')
    @include('hms::layouts.nav')
    <!-- Main content -->
    <section class="content no-print">
    <div class="row">
       <div class="col-md-8">
        <div class="box box-solid">
            <div class="box-header">
                <h3 class="box-title">@lang('hms::lang.booking') - {{ $transaction->ref_no }}</h3>
                <div class="pull-right">
                    @can('hms.edit_booking')
                    <a class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm" href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'edit'], ['booking' => $transaction->id]) }}">
                        <i class="fa fa-edit"></i>
                        @lang('hms::lang.edit_booking')
                    </a>
                    @endcan
                    <a class="tw-dw-btn tw-dw-btn-warning tw-text-white tw-dw-btn-sm" href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'print'], [$transaction->id]).'?document=quotation' }}" target="_blank">
                        <i class="fa fa-print"></i>
                        A4 quotation
                    </a>
                    <a class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm" href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'print'], [$transaction->id]).'?document=invoice' }}" target="_blank">
                        <i class="fa fa-file-pdf-o"></i>
                        A4 invoice
                    </a>
                    <a class="tw-dw-btn tw-dw-btn-info tw-text-white tw-dw-btn-sm print-hms-receipt" href="{{ action([\Modules\Hms\Http\Controllers\HmsBookingController::class, 'printReceipt'], [$transaction->id]) }}">
                        <i class="fa fa-receipt"></i>
                        @lang('hms::lang.print_80mm_receipt')
                    </a>
                    @if(app(\App\Services\FeatureAccessService::class)->enabled('smart_documents') && (auth()->user()->can('smart_documents.create') || auth()->user()->hasRole('Admin#'.session('business.id'))))
                        <a class="tw-dw-btn tw-dw-btn-default tw-dw-btn-sm" href="{{ route('smart-documents.create', ['source_type' => 'transaction', 'source_id' => $transaction->id, 'scenario'=>'short_stay', 'type'=>'room_booking_quotation']) }}"><i class="fa fa-file-text-o"></i> Controlled quotation</a>
                        <a class="tw-dw-btn tw-dw-btn-default tw-dw-btn-sm" href="{{ route('smart-documents.create', ['source_type' => 'transaction', 'source_id' => $transaction->id, 'scenario'=>'short_stay', 'type'=>'room_booking_invoice']) }}"><i class="fa fa-file-text-o"></i> Controlled invoice</a>
                        @if($transaction->payment_lines->reject(fn($payment) => ($payment->payment_purpose ?? null) === 'security_deposit')->sum('amount') > 0)
                            <a class="tw-dw-btn tw-dw-btn-success tw-text-white tw-dw-btn-sm" href="{{ route('smart-documents.create', ['source_type' => 'transaction', 'source_id' => $transaction->id, 'scenario' => 'payment']) }}"><i class="fa fa-money"></i> Payment receipt</a>
                        @endif
                    @endif
                </div>
            </div>
            <div class="box-body">   
                {{-- <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('customer', __('hms::lang.customer') . ':') !!}
                         {{ $transaction->name }}
                    </div>
                </div> --}}
                <div class="col-md-6">
		    		<b>{{ __('sale.customer_name') }}:</b>
		    			{{ $transaction->contact->name }}<br>
			        <b>{{ __('business.address') }}:</b><br>
			        @if(!empty($transaction->billing_address()))
			          {{$transaction->billing_address()}}
			        @else
			          @if($transaction->contact->landmark)
			              {{ $transaction->contact->landmark }},
			          @endif

			          {{ $transaction->contact->city }}

			          @if($transaction->contact->state)
			              {{ ', ' . $transaction->contact->state }}
			          @endif
			          <br>
			          @if($transaction->contact->country)
			              {{ $transaction->contact->country }}
			          @endif
			          @if($transaction->contact->mobile)
			          <br>
			              {{__('contact.mobile')}}: {{ $transaction->contact->mobile }}
			          @endif
			          @if($transaction->contact->alternate_number)
			          <br>
			              {{__('contact.alternate_contact_number')}}:
			              {{ $transaction->contact->alternate_number }}
			          @endif
			          @if($transaction->contact->landline)
			            <br>
			              {{__('contact.landline')}}:
			              {{ $transaction->contact->landline }}
			          @endif
			        @endif
		    	</div>
                <div class="col-md-6">
                    
                    @php
                        $operationalStatus = $transaction->hms_booking_status
                            ?: (!empty($transaction->check_out) ? 'checked_out' : (!empty($transaction->check_in) ? 'checked_in' : ($transaction->status === 'pending' ? 'tentative' : ($transaction->status === 'cancelled' ? 'cancelled' : 'reserved'))));
                        $statusClass = in_array($operationalStatus, ['reserved', 'checked_in']) ? 'bg-green' : ($operationalStatus === 'tentative' ? 'bg-yellow' : ($operationalStatus === 'checked_out' ? 'bg-aqua' : 'bg-red'));
                    @endphp
                    <div class="form-group">
                        {!! Form::label('status', __('hms::lang.status') . ':') !!}
                        <h6 class="{{ $statusClass }} badge">{{ __('hms::lang.status_'.$operationalStatus) }}</h6>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('arrival_date', __('hms::lang.arrival_date') . ':') !!}
                        {{ @format_date($transaction->hms_booking_arrival_date_time) }}
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('arrival_time', __('hms::lang.arrival_time') . ':') !!}
                        {{ @format_time($transaction->hms_booking_arrival_date_time) }}
                    </div>
                </div>
                 <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('departure_date', __('hms::lang.departure_date') . ':') !!}
                        {{ @format_date($transaction->hms_booking_departure_date_time) }}
                    </div>
                    <div class="form-group">
                        {!! Form::label('nights', __('hms::lang.nights') . ':') !!}
                        <strong>{{ $no_of_nights }} {{ $no_of_nights == 1 ? __('hms::lang.night') : __('hms::lang.nights') }}</strong>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        {!! Form::label('departure_time', __('hms::lang.departure_time') . ':') !!}
                        {{ @format_time($transaction->hms_booking_departure_date_time) }}
                    </div>
                </div>
                @if(!empty($transaction->check_in))
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('check_in', __('hms::lang.check_in') . ':') !!}
                            {{ @format_datetime($transaction->check_in) }}
                        </div>
                    </div>
                @endif
                @if(!empty($transaction->check_out))
                    <div class="col-md-6">
                        <div class="form-group">
                            {!! Form::label('check_out', __('hms::lang.check_out') . ':') !!}
                            {{ @format_datetime($transaction->check_out) }}
                        </div>
                    </div>
                @endif
                @include('hms::bookings.partials.operational_summary', ['transaction' => $transaction])
                <div class="col-md-12">
                    <hr>
                </div>
                <div>
                    <h3 class="col-md-12">
                        @lang('hms::lang.trip_information')
                    </h3>
                </div>
                
                <!-- Trip Details Section -->
                <div class="col-md-12">
                    <div class="row">
                        @if(!empty($transaction->hms_reason_for_trip))
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('reason_for_trip', __('hms::lang.reason_for_trip') . ':') !!}
                                <p>{{ $transaction->hms_reason_for_trip }}</p>
                            </div>
                        </div>
                        @endif

                        @if(!empty($transaction->hms_means_of_transport))
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('means_of_transport', __('hms::lang.means_of_transport') . ':') !!}
                                <p>{{ ucfirst(str_replace('_', ' ', $transaction->hms_means_of_transport)) }}</p>
                            </div>
                        </div>
                        @endif

                        @if(!empty($transaction->hms_vehicle_registration_number))
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('vehicle_registration_number', __('hms::lang.vehicle_registration_number') . ':') !!}
                                <p>{{ $transaction->hms_vehicle_registration_number }}</p>
                            </div>
                        </div>
                        @endif

                        @if(!empty($transaction->hms_place_of_origin))
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('place_of_origin', __('hms::lang.place_of_origin') . ':') !!}
                                <p>{{ $transaction->hms_place_of_origin }}</p>
                            </div>
                        </div>
                        @endif

                        @if(!empty($transaction->hms_final_destination))
                        <div class="col-md-6">
                            <div class="form-group">
                                {!! Form::label('final_destination', __('hms::lang.final_destination') . ':') !!}
                                <p>{{ $transaction->hms_final_destination }}</p>
                            </div>
                        </div>
                        @endif
                    </div>
                </div>

                <div class="col-md-12">
                    <hr>
                </div>
                <div>
                    <h3 class="col-md-12">
                        @lang('hms::lang.rooms_and_extras')
                    </h3>
                </div>
                <div class="col-md-12 booking_add_room">
                    <table class="table table-bordered">
                        <thead>
                            <tr class="bg-light-green">
                                <th>@lang('hms::lang.type')</th>
                                <th>@lang('hms::lang.room_no')</th>
                                <th>@lang('hms::lang.no_of_adult')</th>
                                <th>@lang('hms::lang.no_of_child')</th>
                                <th>@lang('hms::lang.price_per_night')</th>
                                <th>@lang('hms::lang.price')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($booking_rooms as $room)
                                <tr>
                                    <td>
                                        {{ $room->type }}
                                    
                                    </td>
                                    <td>
                                        {{ $room->room_number }}
                                    </td>
                                    <td>
                                        {{ $room->adults }}
                                    </td>
                                    <td>
                                        {{ $room->childrens }}
                                    </td>
                                    <td class="price-td">
                                        @format_currency($room->price)
                                    </td>
                                    <td class="price-td">
                                        @format_currency($room->total_price)
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-md-12">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>@lang('hms::lang.extras')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ( $extras as $index => $extra)
                            @if (in_array($extra->id, $extras_id))
                                <tr>
                                    <td>
                                        {{ $extra->name }} /<span class="display_currency" data-currency_symbol="true"> {{ $extra->price }} </span> - {{ str_replace("_", " ", $extra->price_per) }}
                                        
                                    </td>
                                </tr>
                            @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <div class="col-md-12">
                    @php
                        $settings = json_decode($busines->hms_settings);
                    @endphp
                    <div class="col-sm-12">
                        <h4>{{ ($settings->id_proof_label_1 ?? __('hms::lang.id_proof_1')) }}</h4>
                    </div>
                    <div class="col-md-12">
                        <div class="row">
                            @foreach($transaction->media as $media)
                                @if($media->model_media_type == 'id_proof_1')
                                    <div class="col-md-2 card">
                                        <div class="position-relative" style="height: 150px;">
                                            @if(Str::endsWith(strtolower($media->file_name), '.pdf'))
                                                <a href="{{ $media->display_url }}" target="_blank" title="View PDF" style="height: 50%; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-file-pdf fa-6x text-danger"></i>
                                                </a>
                                            @else
                                                <img src="{{ $media->display_url }}" class="img-fluid rounded" style="object-fit: cover; height: 50%;">
                                            @endif
                                            <a href="{{ $media->display_url }}" 
                                            download 
                                            class="position-absolute btn btn-sm btn-primary p-2"
                                            style="z-index: 2;" 
                                            title="{{ __('lang_v1.download') }}">
                                                <i class="fas fa-download fa-sm"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <h4>{{ ($settings->id_proof_label_2 ?? __('hms::lang.id_proof_2')) }}</h4>
                    </div>
                    <div class="col-md-12">
                        <div class="row">
                            @foreach($transaction->media as $media)
                                @if($media->model_media_type == 'id_proof_2')
                                    <div class="col-md-2 card">
                                        <div class="position-relative" style="height: 150px;">
                                            @if(Str::endsWith(strtolower($media->file_name), '.pdf'))
                                            <a href="{{ $media->display_url }}" target="_blank" title="View PDF" style="height: 50%; width:100%; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-file-pdf fa-6x text-danger"></i>
                                                </a>
                                            @else
                                                <img src="{{ $media->display_url }}" class="img-fluid rounded" style="object-fit: cover; height: 50%;">
                                            @endif
                                            <a href="{{ $media->display_url }}" 
                                            download 
                                            class="position-absolute btn btn-sm btn-primary p-2"
                                            style="z-index: 2;" 
                                            title="{{ __('lang_v1.download') }}">
                                                <i class="fas fa-download fa-sm"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                    <div class="col-sm-12">
                        <h4>{{ ($settings->id_proof_label_3 ?? __('hms::lang.id_proof_3'))}}</h4>
                    </div>
                    <div class="col-md-12">
                        <div class="row">
                            @foreach($transaction->media as $media)
                                @if($media->model_media_type == 'id_proof_3')
                                    <div class="col-md-2 card">
                                        <div class="position-relative" style="height: 150px;">
                                            @if(Str::endsWith(strtolower($media->file_name), '.pdf'))
                                            <a href="{{ $media->display_url }}" target="_blank" title="View PDF" style="height: 50%; display: flex; align-items: center; justify-content: center;">
                                                    <i class="fas fa-file-pdf fa-6x text-danger"></i>
                                                </a>
                                            @else
                                                <img src="{{ $media->display_url }}" class="img-fluid rounded" style="object-fit: cover; height: 50%;">
                                            @endif
                                            <a href="{{ $media->display_url }}" 
                                            download 
                                            class="position-absolute btn btn-sm btn-primary p-2"
                                            style="z-index: 2;" 
                                            title="{{ __('lang_v1.download') }}">
                                                <i class="fas fa-download fa-sm"></i>
                                            </a>
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        </div>
                    </div>
                
                </div>
            </div>
            
        </div>
       </div>
       <div class="col-md-4">
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                     @lang('hms::lang.status')
                    <div class="pull-right status_value">
                        @if($transaction->status == 'confirmed')
                            <h6 class="bg-green badge">{{ __('hms::lang.confirmed') }}</h6>
                        @elseif($transaction->status == 'pending')
                            <h6 class="bg-yellow badge">{{ __('hms::lang.pending') }}</h6>
                        @elseif($transaction->status == 'cancelled')
                            <h6 class="bg-red badge">{{ __('hms::lang.cancelled') }}</h6>
                        @endif
                    </div>
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.room_price') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="room_price" > <span class="display_currency" data-currency_symbol="true"> {{ $transaction->room_price }} </span></strong>
                        </div>
                    </div>
                        <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.extra_price') :</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="extra_price"><span class="display_currency" data-currency_symbol="true"> {{ $transaction->extra_price }} </span></strong>
                        </div>
                    </div>
                    @if($transaction->tax_amount > 0)
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('sale.tax'):</strong>
                            @if($transaction->tax)
                                <span class="text-muted">({{ $transaction->tax->name }})</span>
                            @endif
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong><span class="display_currency" data-currency_symbol="true">{{ $transaction->tax_amount }}</span></strong>
                        </div>
                    </div>
                    @endif
                    <div class="row">
                    @php
                        // Determine discount type and calculate actual discount amount
                        $discount_type = $transaction->discount_type;
                        $discount_display_value = 0;
                        
                        // If coupon is applied, use discount_amount directly
                        if (!empty($transaction->hms_coupon_id)) {
                            $discount_display_value = $transaction->discount_amount;
                        } else {
                            if ($transaction->discount_type == 'percentage') {
                                $discount_type = 'percentage';
                                $discount_display_value = ($transaction->discount_amount / 100) * $transaction->total_before_tax;
                            } else {
                                $discount_type = 'fixed';
                                $discount_display_value = $transaction->discount_amount;
                            }
                        }
                    @endphp
                    @if ($discount_display_value > 0)
                        <div class="col-xs-6">
                            @if (!empty($transaction->hms_coupon_id))
                                <strong>@lang('hms::lang.discount'):</strong>
                                <span class="text-muted">({{ $transaction->coupon_code }})</span>
                            @elseif ($discount_type == 'percentage')
                                <strong>@lang('hms::lang.discount'):</strong> 
                                <span class="discount_percentage"> ( {{ @number_format($transaction->discount_amount, 2) }} ) % </span>
                            @else
                                <strong>@lang('hms::lang.discount'):</strong>
                            @endif
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="total_discount"> <span class="display_currency" data-currency_symbol="true"> {{ $discount_display_value }} </span></strong>
                        </div>
                    @else
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.discount'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="total_discount"> <span class="display_currency" data-currency_symbol="true"> {{ $discount_display_value }} </span></strong>
                        </div>
                    @endif
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('hms::lang.total'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong class="total"> <span class="display_currency" data-currency_symbol="true"> {{ $transaction->final_total }} </span></strong>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Payment Information Section -->
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        @lang('sale.payment_info')
                    </h3>
                </div>
                <div class="panel-body">
                    <!-- Payment Summary -->
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('sale.total_amount'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong><span class="display_currency" data-currency_symbol="true">{{ $transaction->final_total }}</span></strong>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('sale.total_paid'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong><span class="display_currency" data-currency_symbol="true">{{ $transaction->payment_lines->sum('amount') }}</span></strong>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('lang_v1.due'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <strong><span class="display_currency" data-currency_symbol="true">{{ $transaction->final_total - $transaction->payment_lines->sum('amount') }}</span></strong>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-xs-6">
                            <strong>@lang('sale.payment_status'):</strong>
                        </div>
                        <div class="col-xs-6 text-right">
                            <span class="label @payment_status($transaction->payment_status)">{{ __('lang_v1.' . $transaction->payment_status) }}</span>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <!-- Payment Details -->
                    @if(!empty($transaction->payment_lines) && count($transaction->payment_lines) > 0)
                        <h5><strong>@lang('lang_v1.payment_details')</strong></h5>
                        <div class="table-responsive">
                            <table class="table table-condensed table-striped">
                                <thead>
                                    <tr>
                                        <th>@lang('messages.date')</th>
                                        <th>@lang('sale.payment_mode')</th>
                                        <th>@lang('sale.amount')</th>
                                        <th>@lang('purchase.payment_note')</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach($transaction->payment_lines as $payment_line)
                                        @if($payment_line['is_return'] != 1)
                                            <tr>
                                                <td>{{ @format_datetime($payment_line['paid_on']) }}</td>
                                                <td>{{ ucfirst($payment_line['method']) }}</td>
                                                <td><span class="display_currency" data-currency_symbol="true">{{ $payment_line['amount'] }}</span></td>
                                                <td>{{ $payment_line['note'] ?? '-' }}</td>
                                            </tr>
                                        @endif
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p class="text-muted">@lang('sale.no_payments_found')</p>
                    @endif
                </div>
            </div>
            
            @if (!empty($transaction->hms_coupon_id))
            <div class="panel panel-default">
                <div class="panel-heading">
                    <h3 class="panel-title">
                        @lang('hms::lang.apply_coupon')
                    </h3>
                </div>
                <div class="panel-body">
                    <div class="row">
                        <div class="col-xs-12">
                            <div class="form-group">
                                {!! Form::label('coupon_code', __('hms::lang.coupon_code') . ':') !!}
                                {{ $transaction->coupon_code }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            @endif
       <div>
    </div>
    </section>
    <!-- /.content -->
@endsection

@section('javascript')
<script type="text/javascript">
    $(document).ready(function() {
        // Handle HMS 80mm receipt printing via AJAX
        $(document).on('click', '.print-hms-receipt', function(e) {
            e.preventDefault();
            var href = $(this).attr('href');
            
            $.ajax({
                method: 'GET',
                url: href,
                dataType: 'json',
                success: function(result) {
                    if (result.success == 1 && result.receipt.html_content != '') {
                        $('#receipt_section').html(result.receipt.html_content);
                        __currency_convert_recursively($('#receipt_section'));
                        
                        var title = document.title;
                        if (typeof result.receipt.print_title != 'undefined') {
                            document.title = result.receipt.print_title;
                        }
                        
                        __print_receipt('receipt_section');
                        
                        setTimeout(function() {
                            document.title = title;
                        }, 1200);
                    } else {
                        toastr.error(result.msg);
                    }
                },
                error: function(xhr, status, error) {
                    toastr.error('Error printing receipt: ' + error);
                }
            });
        });
    });
</script>
@endsection

