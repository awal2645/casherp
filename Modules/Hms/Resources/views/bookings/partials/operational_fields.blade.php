@php
    $booking = $transaction ?? null;
    $sourceOptions = [
        'direct' => __('hms::lang.source_direct'),
        'walk_in' => __('hms::lang.source_walk_in'),
        'website' => __('hms::lang.source_website'),
        'phone' => __('hms::lang.source_phone'),
        'email' => __('hms::lang.source_email'),
        'ota' => __('hms::lang.source_ota'),
        'corporate' => __('hms::lang.source_corporate'),
        'travel_agent' => __('hms::lang.source_travel_agent'),
        'other' => __('hms::lang.source_other'),
    ];
    $holdValue = old('hms_hold_expires_at');
    if (!$holdValue && $booking && $booking->hms_hold_expires_at) {
        $holdValue = \Carbon\Carbon::parse($booking->hms_hold_expires_at)->format('Y-m-d\TH:i');
    }
@endphp

<div class="col-md-4">
    <div class="form-group">
        {!! Form::label('hms_property_id', __('hms::lang.property') . ':*') !!}
        {!! Form::select('hms_property_id', $properties ?? [], old('hms_property_id', $booking->hms_property_id ?? null), [
            'class' => 'form-control select2', 'required', 'placeholder' => __('messages.please_select'),
        ]) !!}
    </div>
</div>
<div class="col-md-4">
    <div class="form-group">
        {!! Form::label('hms_rate_plan_id', __('hms::lang.rate_plan') . ':') !!}
        {!! Form::select('hms_rate_plan_id', $ratePlans ?? [], old('hms_rate_plan_id', $booking->hms_rate_plan_id ?? null), [
            'class' => 'form-control select2', 'placeholder' => __('hms::lang.standard_rate'),
        ]) !!}
    </div>
</div>
<div class="col-md-4">
    <div class="form-group">
        {!! Form::label('hms_group_booking_id', __('hms::lang.group_booking') . ':') !!}
        {!! Form::select('hms_group_booking_id', $groupBookings ?? [], old('hms_group_booking_id', $booking->hms_group_booking_id ?? null), [
            'class' => 'form-control select2', 'placeholder' => __('lang_v1.none'),
        ]) !!}
    </div>
</div>
<div class="clearfix"></div>

<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('hms_booking_source', __('hms::lang.booking_source') . ':') !!}
        {!! Form::select(
            'hms_booking_source',
            $sourceOptions,
            old('hms_booking_source', $booking->hms_booking_source ?? 'direct'),
            ['class' => 'form-control', 'required']
        ) !!}
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('hms_external_reference', __('hms::lang.external_reference') . ':') !!}
        {!! Form::text(
            'hms_external_reference',
            old('hms_external_reference', $booking->hms_external_reference ?? null),
            ['class' => 'form-control', 'maxlength' => 191]
        ) !!}
        <small class="help-block">@lang('hms::lang.external_reference_help')</small>
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('hms_hold_expires_at', __('hms::lang.hold_expires_at') . ':') !!}
        <input type="datetime-local" name="hms_hold_expires_at" id="hms_hold_expires_at"
            class="form-control" value="{{ $holdValue }}">
        <small class="help-block">@lang('hms::lang.hold_expiry_help')</small>
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('hms_cancellation_reason', __('hms::lang.cancellation_reason') . ':') !!}
        {!! Form::text(
            'hms_cancellation_reason',
            old('hms_cancellation_reason', $booking->hms_cancellation_reason ?? null),
            ['class' => 'form-control', 'maxlength' => 2000]
        ) !!}
        <small class="help-block">@lang('hms::lang.cancellation_reason_help')</small>
    </div>
</div>
<div class="col-md-12">
    <div class="form-group">
        {!! Form::label('hms_special_requests', __('hms::lang.special_requests') . ':') !!}
        {!! Form::textarea(
            'hms_special_requests',
            old('hms_special_requests', $booking->hms_special_requests ?? null),
            ['class' => 'form-control', 'rows' => 2, 'maxlength' => 5000]
        ) !!}
    </div>
</div>
<div class="col-md-12">
    <hr>
    <h4><i class="fa fa-user"></i> @lang('hms::lang.guest_preferences_and_consent')</h4>
    <p class="help-block">@lang('hms::lang.guest_data_minimization_help')</p>
</div>
<div class="col-md-3">
    <div class="form-group">
        {!! Form::label('preferred_language', __('hms::lang.preferred_language') . ':') !!}
        {!! Form::text('preferred_language', old('preferred_language', optional($booking->hms_guest_profile ?? null)->preferred_language), ['class' => 'form-control', 'maxlength' => 12]) !!}
    </div>
</div>
<div class="col-md-3">
    <div class="form-group">
        {!! Form::label('nationality', __('hms::lang.nationality') . ':') !!}
        {!! Form::text('nationality', old('nationality', optional($booking->hms_guest_profile ?? null)->nationality), ['class' => 'form-control', 'maxlength' => 80]) !!}
    </div>
</div>
<div class="col-md-3">
    <div class="form-group">
        {!! Form::label('identity_document_type', __('hms::lang.identity_document_type') . ':') !!}
        {!! Form::text('identity_document_type', old('identity_document_type', optional($booking->hms_guest_profile ?? null)->identity_document_type), ['class' => 'form-control', 'maxlength' => 40]) !!}
    </div>
</div>
<div class="col-md-3">
    <div class="form-group">
        {!! Form::label('identity_document_last_four', __('hms::lang.identity_document_last_four') . ':') !!}
        {!! Form::text('identity_document_last_four', old('identity_document_last_four', optional($booking->hms_guest_profile ?? null)->identity_document_last_four), ['class' => 'form-control', 'maxlength' => 8, 'autocomplete' => 'off']) !!}
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('accessibility_needs', __('hms::lang.accessibility_needs') . ':') !!}
        {!! Form::textarea('accessibility_needs', old('accessibility_needs', optional($booking->hms_guest_profile ?? null)->accessibility_needs), ['class' => 'form-control', 'rows' => 2, 'maxlength' => 3000]) !!}
    </div>
</div>
<div class="col-md-3">
    <div class="checkbox" style="margin-top: 25px;">
        <label>{!! Form::checkbox('marketing_consent', 1, old('marketing_consent', optional($booking->hms_guest_profile ?? null)->marketing_consent), ['class' => 'input-icheck']) !!} @lang('hms::lang.marketing_consent')</label>
    </div>
</div>
<div class="col-md-3">
    <div class="checkbox" style="margin-top: 25px;">
        <label>{!! Form::checkbox('do_not_contact', 1, old('do_not_contact', optional($booking->hms_guest_profile ?? null)->do_not_contact), ['class' => 'input-icheck']) !!} @lang('hms::lang.do_not_contact')</label>
    </div>
</div>
<div class="clearfix"></div>
