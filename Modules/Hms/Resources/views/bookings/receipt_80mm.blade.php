@php
    $settings = json_decode($business->hms_settings);
    
    // Calculate discount display value same as print_pdf
    if (empty($transaction->discount_amount)) {
        $discount_display_value = 0;
    } else {
        if ($transaction->discount_type == 'percentage') {
            $discount_type = 'percentage';
            $discount_display_value = ($transaction->discount_amount / 100) * $transaction->total_before_tax;
        } else {
            $discount_type = 'fixed';
            $discount_display_value = $transaction->discount_amount;
        }
    }

    // Payment method labels (with custom labels and translations)
    $payment_types = app(\App\Utils\Util::class)->payment_types(null, true, $business->id);
@endphp

<div class="ticket">
            <!-- Business Header - Using slim template structure -->
            @if (!empty(Session::get('business.logo')))
                <div class="text-box centered">
                    <img style="max-height: 100px; width: auto; display: block; margin-left: auto; margin-right: auto;" src="{{ asset('uploads/business_logos/' . Session::get('business.logo')) }}" alt="Logo">
                </div>
            @endif
            <div class="text-box">
                <p class="centered">
                    <span class="headings">
                        {{ $business->name }}
                    </span>
                    <br/>
                    
                    @if (!empty($settings->booking_pdf->address))
                        {!! nl2br(e($settings->booking_pdf->address)) !!}
                        <br/>
                    @endif

                    @if (!empty($settings->booking_pdf->phone))
                        @lang('contact.contact'): {{ $settings->booking_pdf->phone }}
                    @endif
                    @if (!empty($settings->booking_pdf->phone) && !empty($settings->booking_pdf->email))
                        <br/>
                    @endif
                    @if (!empty($settings->booking_pdf->email))
                        @lang('hms::lang.email'): {{ $settings->booking_pdf->email }}
                    @endif
                    @if (!empty($settings->booking_pdf->website))
                        <br/>@lang('hms::lang.website'): {{ $settings->booking_pdf->website }}
                    @endif
                    
                    <!-- Title of receipt -->
                    <br/><span class="sub-headings">{{ $documentContext['title'] ?? __('hms::lang.booking_receipt') }}</span>
                </p>
            </div>
            
            <!-- Booking Information - Using slim template structure -->
            <div class="border-top textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.booking_Id'):</strong></p>
                <p class="f-right">{{ $transaction->ref_no }}</p>
            </div>
            @if(!empty($documentContext))
            <div class="textbox-info">
                <p class="f-left"><strong>Booking date:</strong></p>
                <p class="f-right">{{ \Carbon\Carbon::parse($documentContext['booking_date'])->format('d M Y') }}</p>
            </div>
            @endif
            <div class="textbox-info">
                <p class="f-left"><strong>Arrival:</strong></p>
                <p class="f-right">{{ !empty($documentContext) ? \Carbon\Carbon::parse($documentContext['arrival_date'])->format('d M Y').' '.$documentContext['arrival_time'] : \Carbon\Carbon::parse($transaction->hms_booking_arrival_date_time)->format('d M Y H:i') }}</p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>Checkout:</strong></p>
                <p class="f-right">{{ !empty($documentContext) ? \Carbon\Carbon::parse($documentContext['checkout_date'])->format('d M Y').' '.$documentContext['checkout_time'] : \Carbon\Carbon::parse($transaction->hms_booking_departure_date_time)->format('d M Y H:i') }}</p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.nights'):</strong></p>
                <p class="f-right">{{ $no_of_nights }} {{ $no_of_nights == 1 ? __('hms::lang.night') : __('hms::lang.nights') }}</p>
            </div>
            @if(!empty($documentContext))
            <div class="textbox-info">
                <p class="f-left"><strong>Stay duration:</strong></p>
                <p class="f-right">{{ $documentContext['stay_duration'] }}</p>
            </div>
            @endif
            @if (!empty($transaction->check_in))
                <div class="textbox-info">
                    <p class="f-left"><strong>@lang('hms::lang.check_in'):</strong></p>
                    <p class="f-right">{{ @format_datetime($transaction->check_in) }}</p>
                </div>
            @endif
            @if (!empty($transaction->check_out))
                <div class="textbox-info">
                    <p class="f-left"><strong>@lang('hms::lang.check_out'):</strong></p>
                    <p class="f-right">{{ @format_datetime($transaction->check_out) }}</p>
                </div>
            @endif
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.status'):</strong></p>
                <p class="f-right">
                    @if($transaction->status == 'confirmed')
                        {{ __('hms::lang.confirmed') }}
                    @elseif($transaction->status == 'pending')
                        {{ __('hms::lang.pending') }}
                    @elseif($transaction->status == 'cancelled')
                        {{ __('hms::lang.cancelled') }}
                    @else
                        {{ ucfirst($transaction->status) }}
                    @endif
                </p>
            </div>

            <!-- Customer Information - Using slim template structure -->
            <div class="border-top textbox-info">
                <p style="vertical-align: top;"><strong>@lang('sale.customer_name'):</strong></p>
                <p>
                    <div class="bw">
                        {{ $transaction->contact->name }}
                    </div>
                </p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.address'):</strong></p>
                <p class="f-right">
                    @if ($transaction->contact->landmark)
                        {{ $transaction->contact->landmark }},
                    @endif
                    {{ $transaction->contact->city }}
                    @if ($transaction->contact->state)
                        {{ ', ' . $transaction->contact->state }}
                    @endif
                    <br>
                    @if ($transaction->contact->country)
                        {{ $transaction->contact->country }}
                    @endif
                </p>
            </div>
            @if ($transaction->contact->mobile)
                <div class="textbox-info">
                    <p class="f-left"><strong>@lang('contact.mobile'):</strong></p>
                    <p class="f-right">{{ $transaction->contact->mobile }}</p>
                </div>
            @endif
            @if ($transaction->contact->alternate_number)
                <div class="textbox-info">
                    <p class="f-left"><strong>@lang('contact.alternate_contact_number'):</strong></p>
                    <p class="f-right">{{ $transaction->contact->alternate_number }}</p>
                </div>
            @endif
            @if ($transaction->contact->landline)
                <div class="textbox-info">
                    <p class="f-left"><strong>@lang('contact.landline'):</strong></p>
                    <p class="f-right">{{ $transaction->contact->landline }}</p>
                </div>
            @endif

            <!-- Rooms Information - Using slim template structure -->
            @if(!empty($booking_rooms))
                <div class="border-top">
                    <p class="centered"><strong>@lang('hms::lang.rooms')</strong></p>
                    <table style="margin-top: 25px !important" class="width-100 table-f-12 mb-10">
                        <thead class="border-bottom-dotted">
                            <tr>
                                <th class="serial_number" width="5%">#</th>
                                <th class="description" width="15%">@lang('hms::lang.type')</th>
                                <th class="quantity text-right" width="15%">@lang('hms::lang.room_no')</th>
                                <th class="unit_price text-right" width="10%">@lang('hms::lang.no_of_adult')</th>
                                <th class="price text-right" width="20%">@lang('hms::lang.price_per_night')</th>
                                <th class="price text-right" width="20%">@lang('hms::lang.price')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($booking_rooms as $room)
                                <tr>
                                    <td class="serial_number" style="vertical-align: top;">{{ $loop->iteration }}</td>
                                    <td class="description">{{ $room->type }}</td>
                                    <td class="quantity text-right">{{ $room->room_number }}</td>
                                    <td class="unit_price text-right">{{ $room->adults }}</td>
                                    <td class="price text-right">@format_currency($room->price)</td>
                                    <td class="price text-right">@format_currency($room->total_price)</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Extras Information - Using slim template structure -->
            @if(!empty($extras_id))
                <div class="border-top">
                    <p class="centered"><strong>@lang('hms::lang.extras')</strong></p>
                    <table style="margin-top: 25px !important" class="width-100 table-f-12 mb-10">
                        <thead class="border-bottom-dotted">
                            <tr>
                                <th class="serial_number">#</th>
                                <th class="description" width="70%">@lang('hms::lang.name')</th>
                                <th class="price text-right">@lang('hms::lang.price')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($extras as $extra)
                                @if(in_array($extra->id, $extras_id))
                                    <tr>
                                        <td class="serial_number" style="vertical-align: top;">{{ $loop->iteration }}</td>
                                        <td class="description">{{$extra->name}}</td>
                                        <td class="price text-right">@format_currency($extra->price)</td>
                                    </tr>
                                @endif
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Pricing Summary - Using slim template structure -->
            <div class="border-top textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.room_price'):</strong></p>
                <p class="f-right">@format_currency($transaction->room_price)</p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.extra_price'):</strong></p>
                <p class="f-right">@format_currency($transaction->extra_price)</p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>
                    @if ($discount_display_value > 0)
                        @if ($discount_type == 'percentage')
                            @lang('hms::lang.discount'): ({{ number_format($transaction->discount_amount, 2) }} % )
                        @else
                            @lang('hms::lang.discount'):
                        @endif
                    @else
                        @lang('hms::lang.discount'):
                    @endif
                </strong></p>
                <p class="f-right">
                    @format_currency($discount_display_value)
                    @if (!empty($transaction->hms_coupon_id) && !empty($transaction->coupon_code))
                        <br><small>({{ $transaction->coupon_code }})</small>
                    @endif
                </p>
            </div>
            @if($transaction->tax_amount > 0)
                <div class="textbox-info">
                    <p class="f-left"><strong>@lang('hms::lang.booking_tax'):</strong></p>
                    <p class="f-right">
                        @format_currency($transaction->tax_amount)
                        @if(!empty($transaction->tax) && !empty($transaction->tax->name))
                            <br><small>({{ $transaction->tax->name }})</small>
                        @endif
                    </p>
                </div>
            @endif
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.total'):</strong></p>
                <p class="f-right">@format_currency($transaction->final_total)</p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.total_paid'):</strong></p>
                <p class="f-right">@format_currency($transaction->total_paid)</p>
            </div>
            <div class="textbox-info">
                <p class="f-left"><strong>@lang('hms::lang.due'):</strong></p>
                <p class="f-right">@format_currency($transaction->final_total - $transaction->total_paid)</p>
            </div>
            @if(!empty($securityDeposit) && (float)$securityDeposit->required_amount > 0)
                <div class="border-top textbox-info">
                    <p><strong>Refundable security deposit</strong><br>
                        Required: @format_currency($securityDeposit->required_amount)<br>
                        Held separately: @format_currency($securityDeposit->held_balance)<br>
                        <small>Not included in accommodation income, tax, total paid or amount due.</small>
                    </p>
                </div>
            @endif

            <!-- Payment Information - Using slim template structure -->
            @if(!empty($transaction->payment_lines) && count($transaction->payment_lines) > 0)
                <div class="border-top">
                    <p class="centered"><strong>@lang('sale.payment_info')</strong></p>
                    <table style="margin-top: 25px !important" class="width-100 table-f-12 mb-10">
                        <thead class="border-bottom-dotted">
                            <tr>
                                <th class="serial_number">#</th>
                                <th class="description" width="20%">@lang('messages.date')</th>
                                <th class="quantity text-right">@lang('purchase.ref_no')</th>
                                <th class="unit_price text-right">@lang('sale.amount')</th>
                                <th class="price text-right">@lang('sale.payment_mode')</th>
                            </tr>
                        </thead>
                        <tbody>
                            @php
                                $total_paid = 0;
                            @endphp
                            @foreach($transaction->payment_lines as $payment_line)
                                @php
                                    if($payment_line->is_return == 1){
                                        $total_paid -= $payment_line->amount;
                                    } else {
                                        $total_paid += $payment_line->amount;
                                    }
                                @endphp
                                <tr>
                                    <td class="serial_number" style="vertical-align: top;">{{ $loop->iteration }}</td>
                                    <td class="description">{{ @format_date($payment_line->paid_on) }}</td>
                                    <td class="quantity text-right">{{ $payment_line->payment_ref_no }}</td>
                                    <td class="text-right">@format_currency($payment_line->amount)</td>
                                    <td class="price text-right">
                                        {{ $payment_types[$payment_line->method] ?? ucfirst($payment_line->method) }}
                                        @if($payment_line->method == 'cheque' && !empty($payment_line->cheque_number))
                                            <br>({{ __('lang_v1.cheque_number') }}: {{ $payment_line->cheque_number }})
                                        @elseif($payment_line->method == 'card' && !empty($payment_line->card_transaction_number))
                                            <br>({{ __('lang_v1.card_transaction_no') }}: {{ $payment_line->card_transaction_number }})
                                        @elseif($payment_line->method == 'bank_transfer' && !empty($payment_line->bank_account_number))
                                            <br>({{ __('lang_v1.bank_account_no') }}: {{ $payment_line->bank_account_number }})
                                        @elseif(strpos($payment_line->method, 'custom_pay_') === 0 && !empty($payment_line->transaction_no))
                                            <br>({{ __('lang_v1.transaction_no') }}: {{ $payment_line->transaction_no }})
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <!-- Additional notes -->
            @if(!empty($settings->booking_pdf->text_after_table))
                <hr>
                    <p class="centered">
                        {!! $settings->booking_pdf->text_after_table !!}
                    </p>
            @endif

        </div>
        <footer class=" text-center" style="position: absolute !important; bottom: 200 !important; width: 100% !important;">
    <div class="row">
        <div class="col-xs-12">
            <div class="footer-content">

                @if (!empty($settings->booking_pdf->footer_text))
                <hr>
                    <div>
                        {!! $settings->booking_pdf->footer_text !!}
                    </div>
                @endif
            </div>
        </div>
    </div>
</footer>

<style type="text/css">
.f-8 {
    font-size: 8px !important;
}
@media print {
    * {
        font-size: 12px;
        font-family: 'Times New Roman';
        word-break: break-all;
    }
    .f-8 {
        font-size: 8px !important;
    }
    
    /* Footer styling for print/PDF */
    .footer-container {
        position: absolute !important;
        bottom: 20px !important;
        width: 100% !important;
        left: 0 !important;
        right: 0 !important;
    }

    .footer-content {
        text-align: center !important;
        font-size: 10px !important;
        padding: 5px !important;
        word-break: break-word !important;
    }
    
.headings{
    font-size: 16px;
    font-weight: 700;
    text-transform: uppercase;
    white-space: nowrap;
}

.sub-headings{
    font-size: 15px !important;
    font-weight: 700 !important;
}

.border-top{
    border-top: 1px solid #242424;
}
.border-bottom{
    border-bottom: 1px solid #242424;
}

.border-bottom-dotted{
    border-bottom: 1px dotted darkgray;
}

td.serial_number, th.serial_number{
    width: 5%;
    max-width: 5%;
}

td.description,
th.description {
    width: 15%;
    max-width: 15%;
    word-break: break-all;
}

td.quantity,
th.quantity {
    width: 15%;
    max-width: 15%;
    word-break: break-all;
}
td.unit_price, th.unit_price{
    width: 15%;
    max-width: 15%;
    word-break: break-all;
}

td.price,
th.price {
    width: 10%;
    max-width: 10%;
    word-break: break-all;
}

.centered {
    text-align: center;
    align-content: center;
}

.ticket {
    width: 100%;
    max-width: 100%;
}

.text-box {
    padding: 5px;
}

.textbox-info {
    padding: 3px 5px;
    display: flex;
    justify-content: space-between;
}

.f-left {
    float: left;
    text-align: left;
}

.f-right {
    float: right;
    text-align: right;
}

.table-info {
    width: 100%;
}
.table-info tr:first-child td, .table-info tr:first-child th {
    padding-top: 8px;
}
.table-info th {
    text-align: left;
}
.table-info td {
    text-align: right;
}
.logo {
    float: left;
    width:35%;
    padding: 10px;
}

.text-with-image {
    float: left;
    width:65%;
}
.text-box {
    width: 100%;
    height: auto;
}

.textbox-info {
    clear: both;
}
.textbox-info p {
    margin-bottom: 0px
}
.flex-box {
    display: flex;
    width: 100%;
}
.flex-box p {
    width: 50%;
    margin-bottom: 0px;
    white-space: nowrap;
}

.table-f-12 th, .table-f-12 td {
    font-size: 12px;
    word-break: break-word;
}

.bw {
    word-break: break-word;
}

.width-100 {
    width: 100%;
}

.mb-10 {
    margin-bottom: 10px;
}

.text-right {
    text-align: right;
}
}
</style>
