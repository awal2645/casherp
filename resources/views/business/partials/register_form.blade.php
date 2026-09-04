@if(empty($is_admin))
    <h3>@lang('business.business')</h3>
@endif
{!! Form::hidden('language', request()->lang); !!}
@php($isGoogleRegistration = ($registrationPrefill['social_provider'] ?? null) === 'google')

@if($errors->any())
    <div class="col-md-12" role="alert" aria-live="assertive">
        <div class="alert alert-danger">
            <strong>Please review the highlighted information.</strong>
            <ul class="tw-mb-0 tw-mt-2">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<fieldset>
<legend class="text-black">@lang('business.business_details'):</legend>
<div class="col-md-12">
    <div class="form-group">
        {!! Form::label('name', __('business.business_name') . ':*' ) !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-suitcase"></i>
            </span>
            {!! Form::text('name', old('name', $registrationPrefill['name'] ?? null), ['class' => 'form-control','placeholder' => __('business.business_name'), 'required', 'autocomplete' => 'organization']); !!}
        </div>
    </div>
</div>

<div class="col-md-12">
    <div class="form-group">
        {!! Form::label('industry_id', 'Business industry:*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-building"></i>
            </span>
            {!! Form::select('industry_id', $industries, old('industry_id', $registrationPrefill['industry_id'] ?? null), ['class' => 'form-control select2_register', 'placeholder' => 'Select your business industry', 'required', 'data-industry-selector' => true, 'aria-describedby' => 'industry_profile_help']) !!}
        </div>
        <span class="help-block" id="industry_profile_help"><strong>Industry belongs to this company, not to your login.</strong> Each company under your account can use a different industry and keeps an independent workspace.</span>
    </div>
</div>
<div class="clearfix"></div>

<div class="col-md-12">
    <div class="alert alert-info tw-hidden" data-industry-profile role="status" aria-live="polite">
        <strong data-industry-profile-name></strong>
        <p class="tw-mb-1" data-industry-profile-audience></p>
        <p class="tw-mb-1"><strong>Recommended workspace:</strong> <span data-industry-profile-workspace></span></p>
        <p class="tw-mb-0"><strong>Default feature profile:</strong> <span data-industry-profile-features></span></p>
        <small>These are company defaults. Authorised administrators can enable or disable available features later.</small>
    </div>
    <div class="row" data-industry-questions></div>
</div>
<div class="clearfix"></div>
        
<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('start_date', __('business.start_date') . ':') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-calendar"></i>
        </span>
        {!! Form::text('start_date', null, ['class' => 'form-control start-date-picker','placeholder' => __('business.start_date'), 'readonly']); !!}
    </div>
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('currency_id', __('business.currency') . ':*') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fas fa-money-bill-alt"></i>
        </span>
        {!! Form::select('currency_id', $currencies, '', ['class' => 'form-control select2_register','placeholder' => __('business.currency_placeholder'), 'required']); !!}
    </div>
    </div>
</div>
<div class="clearfix"></div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('business_logo', __('business.upload_logo') . ':') !!}
        {!! Form::file('business_logo', ['accept' => 'image/*']); !!}
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('website', __('lang_v1.website') . ':') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-globe"></i>
            </span>
            {!! Form::text('website', null, ['class' => 'form-control','placeholder' => __('lang_v1.website'), 'autocomplete' => 'url', 'inputmode' => 'url']); !!}
        </div>
    </div>
</div>
<div class="clearfix"></div>
<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('mobile', __('lang_v1.business_telephone') . ':') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-phone"></i>
        </span>
        {!! Form::text('mobile', null, ['class' => 'form-control','placeholder' => __('lang_v1.business_telephone'), 'autocomplete' => 'tel', 'inputmode' => 'tel']); !!}
    </div>
    </div>
</div>

<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('alternate_number', __('business.alternate_number') . ':') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-phone"></i>
            </span>
            {!! Form::text('alternate_number', null, ['class' => 'form-control','placeholder' => __('business.alternate_number'), 'autocomplete' => 'tel', 'inputmode' => 'tel']); !!}
        </div>
    </div>
</div>

<div class="clearfix"></div>

<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('country', __('business.country') . ':*') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-globe"></i>
        </span>
        {!! Form::text('country', old('country', $registrationPrefill['country'] ?? null), ['class' => 'form-control','placeholder' => __('business.country'), 'required', 'autocomplete' => 'country-name']); !!}
    </div>
    </div>
</div>

<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('state',__('business.state') . ':') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-map-marker"></i>
        </span>
        {!! Form::text('state', null, ['class' => 'form-control','placeholder' => __('business.state'), 'autocomplete' => 'address-level1']); !!}
    </div>
    </div>
</div>
<div class="clearfix"></div>
<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('city',__('business.city'). ':*') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-map-marker"></i>
        </span>
        {!! Form::text('city', null, ['class' => 'form-control','placeholder' => __('business.city'), 'required', 'autocomplete' => 'address-level2']); !!}
    </div>
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('zip_code', __('business.zip_code') . ':') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-map-marker"></i>
        </span>
        {!! Form::text('zip_code', null, ['class' => 'form-control','placeholder' => __('business.zip_code_placeholder'), 'autocomplete' => 'postal-code']); !!}
    </div>
    </div>
</div>
<div class="clearfix"></div>
<div class="col-md-6">
    <div class="form-group">
    {!! Form::label('landmark', __('business.landmark') . ':*') !!}
    <div class="input-group">
        <span class="input-group-addon">
            <i class="fa fa-map-marker"></i>
        </span>
        {!! Form::text('landmark', null, ['class' => 'form-control','placeholder' => __('business.landmark'), 'required', 'autocomplete' => 'street-address']); !!}
    </div>
    </div>
</div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('time_zone', __('business.time_zone') . ':*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fas fa-clock"></i>
            </span>
            {!! Form::select('time_zone', $timezone_list, config('app.timezone'), ['class' => 'form-control select2_register','placeholder' => __('business.time_zone'), 'required']); !!}
        </div>
    </div>
</div>
</fieldset>

<!-- tax details -->
@if(empty($is_admin))
    <h3>@lang('business.business_settings')</h3>

    <fieldset>
    <legend class="text-black">@lang('business.business_settings'):</legend>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('tax_label_1', __('business.tax_1_name') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-info"></i>
                </span>
                {!! Form::text('tax_label_1', null, ['class' => 'form-control','placeholder' => __('business.tax_1_placeholder')]); !!}
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('tax_number_1', __('business.tax_1_no') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-info"></i>
                </span>
                {!! Form::text('tax_number_1', null, ['class' => 'form-control']); !!}
            </div>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('tax_label_2',__('business.tax_2_name') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-info"></i>
                </span>
                {!! Form::text('tax_label_2', null, ['class' => 'form-control','placeholder' => __('business.tax_1_placeholder')]); !!}
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('tax_number_2',__('business.tax_2_no') . ':') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-info"></i>
                </span>
                {!! Form::text('tax_number_2', null, ['class' => 'form-control',]); !!}
            </div>
        </div>
    </div>
    <div class="clearfix"></div>
    <div class="col-md-6">
        <div class="form-group">
            {!! Form::label('fy_start_month', __('business.fy_start_month') . ':*') !!} @show_tooltip(__('tooltip.fy_start_month'))
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-calendar"></i>
                </span>
                {!! Form::select('fy_start_month', $months, null, ['class' => 'form-control select2_register', 'required', 'style' => 'width:100%;']); !!}
            </div>
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            {!! Form::label('accounting_method', __('business.accounting_method') . ':*') !!}
            <div class="input-group">
                <span class="input-group-addon">
                    <i class="fa fa-calculator"></i>
                </span>
                {!! Form::select('accounting_method', $accounting_methods, null, ['class' => 'form-control select2_register', 'required', 'style' => 'width:100%;']); !!}
            </div>
        </div>
    </div>
    </fieldset>
@endif

<!-- Owner Information -->
@if(empty($is_admin))
    <h3>@lang('business.owner')</h3>
@endif

<fieldset>
<legend class="text-black">@lang('business.owner_info')</legend>
@if($isGoogleRegistration)
    <div class="col-md-12">
        <div class="alert alert-success" role="status">
            <strong>Google email verified.</strong>
            Continue as {{ $registrationPrefill['email'] }}. CashERP will link this company owner to that Google identity and will not store the Google access token.
        </div>
    </div>
@endif
<div class="col-md-4">
    <div class="form-group">
        {!! Form::label('surname', __('business.prefix') . ':') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-info"></i>
            </span>
            {!! Form::text('surname', null, ['class' => 'form-control','placeholder' => __('business.prefix_placeholder')]); !!}
        </div>
    </div>
</div>

<div class="col-md-4">
    <div class="form-group">
        {!! Form::label('first_name', __('business.first_name') . ':*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-info"></i>
            </span>
            {!! Form::text('first_name', old('first_name', $registrationPrefill['first_name'] ?? null), ['class' => 'form-control','placeholder' => __('business.first_name'), 'required', 'autocomplete' => 'given-name']); !!}
        </div>
    </div>
</div>

<div class="col-md-4">
    <div class="form-group">
        {!! Form::label('last_name', __('business.last_name') . ':') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-info"></i>
            </span>
            {!! Form::text('last_name', old('last_name', $registrationPrefill['last_name'] ?? null), ['class' => 'form-control','placeholder' =>  __('business.last_name'), 'autocomplete' => 'family-name']); !!}
        </div>
    </div>
</div>
<div class="clearfix"></div>
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('username', __('business.username') . ':*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-user"></i>
            </span>
            {!! Form::text('username', old('username', $registrationPrefill['username'] ?? null), ['class' => 'form-control','placeholder' => __('business.username'), 'required', 'autocomplete' => 'username', 'minlength' => 4, 'maxlength' => 50, 'pattern' => '[A-Za-z0-9._-]+']); !!}
        </div>
    </div>
</div>

<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('email', __('business.email') . ':*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-envelope"></i>
            </span>
            {!! Form::email('email', old('email', $registrationPrefill['email'] ?? null), array_filter(['class' => 'form-control','placeholder' => __('business.email'), 'required' => true, 'autocomplete' => 'email', 'inputmode' => 'email', 'readonly' => $isGoogleRegistration ? true : null])); !!}
        </div>
    </div>
</div>
<div class="clearfix"></div>
@if(!$isGoogleRegistration)
<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('password', __('business.password') . ':*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-lock"></i>
            </span>
            {!! Form::password('password', ['class' => 'form-control','placeholder' => __('business.password'), 'required', 'autocomplete' => 'new-password', 'minlength' => 8, 'aria-describedby' => 'password_guidance']); !!}
        </div>
        <span class="help-block" id="password_guidance">Use at least 8 characters with upper- and lower-case letters and a number.</span>
        <div class="progress tw-h-1 tw-mb-0" aria-hidden="true"><div class="progress-bar" data-password-strength style="width:0%"></div></div>
    </div>
</div>

<div class="col-md-6">
    <div class="form-group">
        {!! Form::label('confirm_password', __('business.confirm_password') . ':*') !!}
        <div class="input-group">
            <span class="input-group-addon">
                <i class="fa fa-lock"></i>
            </span>
            {!! Form::password('confirm_password', ['class' => 'form-control','placeholder' => __('business.confirm_password'), 'required', 'autocomplete' => 'new-password', 'minlength' => 8]); !!}
        </div>
    </div>
</div>
<div class="clearfix"></div>
@else
    <div class="col-md-12">
        <p class="help-block">No CashERP password is required for this registration. You can sign in with Google and may set a password later through the password-reset flow.</p>
    </div>
    <div class="clearfix"></div>
@endif
    @if(!empty($system_settings['superadmin_enable_register_tc']) && !empty($is_register))
        <div class="col-md-6">
            <div>
                <label>
                    {!! Form::checkbox('accept_tc', 1, false, ['required', 'class' => 'input-check-box']); !!}
                    <a class="terms_condition cursor-pointer" data-toggle="modal" data-target="#tc_modal">
                        @lang('lang_v1.accept_terms_and_conditions') <i></i>
                    </a>
                </label>
            </div>
            @include('business.partials.terms_conditions')
        </div>
    @endif

    @if(config('constants.enable_recaptcha') && !empty($is_register))
        <div class="col-md-6">
            <div class="form-group">
                <div id="recaptcha-container"></div>
                @if ($errors->has('g-recaptcha-response'))
                    <span class="text-danger">{{ $errors->first('g-recaptcha-response') }}</span>
                @endif
            </div>
        </div>
    @endif
<div class="clearfix"></div>
</fieldset>
@if(config('constants.enable_recaptcha') && !empty($is_register))
    <script>
        window.RECAPTCHA_SITE_KEY = "{{ config('constants.google_recaptcha_key') }}";
    </script>
@endif
