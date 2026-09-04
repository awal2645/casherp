@extends('layouts.app')
@section('title', 'Add another company')
@section('css')
<link rel="stylesheet" href="{{ asset('css/casherp_onboarding.css?v=' . config('constants.asset_version')) }}">
@endsection

@section('content')
<section class="content-header">
    <h1>Add another company <small>Choose an industry and create a separate company workspace.</small></h1>
</section>

<section class="content">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="tw-mb-0">
                @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
            </ul>
        </div>
    @endif

    <div class="alert {{ $quota['can_create'] ? 'alert-info' : 'alert-warning' }}" role="status">
        @if($quota['unlimited'])
            Your current package allows unlimited companies. You currently have {{ $quota['current'] }}.
        @else
            Your current package allows {{ $quota['maximum'] }} companies. You currently have {{ $quota['current'] }}
            and {{ $quota['remaining'] }} remaining.
        @endif
        @if(!$quota['can_create'])
            <a class="btn btn-primary btn-xs pull-right" href="{{ route('subscription.index') }}">View upgrade options</a>
        @endif
    </div>

    <div class="alert alert-info" role="note">
        <i class="fa fa-shield" aria-hidden="true"></i>
        <strong>This creates a separate company workspace.</strong>
        Customers, sales, rooms, staff, payroll, properties, finances, settings, and reports are not copied or shared with your other companies. You can switch the active company from the top bar.
    </div>

    <div class="box box-primary">
        {!! Form::open(['route' => 'business.additional.store', 'method' => 'post', 'data-onboarding-form' => true, 'data-draft-key' => 'casherp:additional-company:' . auth()->id()]) !!}
        <div class="box-body tw-pb-0">
            <div class="casherp-onboarding-steps" aria-label="Company setup stages">
                <div class="casherp-onboarding-step" data-registration-step="company"><span class="casherp-onboarding-step-number">1</span><div><strong>Company</strong><span>Name and address</span></div></div>
                <div class="casherp-onboarding-step" data-registration-step="operations"><span class="casherp-onboarding-step-number">2</span><div><strong>Operations</strong><span>Industry and business model</span></div></div>
                <div class="casherp-onboarding-step" data-registration-step="owner"><span class="casherp-onboarding-step-number">3</span><div><strong>Ready</strong><span>Separate workspace</span></div></div>
            </div>
            <div data-onboarding-submit-progress aria-hidden="true"><span></span></div>
        </div>
        <div class="box-body row">
            <div class="col-md-6"><div class="form-group">{!! Form::label('name', __('business.business_name').':*') !!}{!! Form::text('name', old('name'), ['class' => 'form-control', 'required', 'autocomplete' => 'organization']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('industry_id', 'Business industry:*') !!}{!! Form::select('industry_id', $industries, old('industry_id'), ['class' => 'form-control select2', 'placeholder' => 'Select your business industry', 'required', 'data-industry-selector' => true, 'aria-describedby' => 'additional_industry_help']) !!}<span class="help-block" id="additional_industry_help">This company can use an industry different from your other companies.</span></div></div>
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
            <div class="col-md-6"><div class="form-group">{!! Form::label('currency_id', __('business.currency').':*') !!}{!! Form::select('currency_id', $currencies, old('currency_id'), ['class' => 'form-control select2', 'placeholder' => __('messages.please_select'), 'required']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('time_zone', __('business.time_zone').':*') !!}{!! Form::select('time_zone', $timezone_list, old('time_zone', config('app.timezone')), ['class' => 'form-control select2', 'required']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('fy_start_month', __('business.fy_start_month').':*') !!}{!! Form::select('fy_start_month', $months, old('fy_start_month'), ['class' => 'form-control select2', 'required']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('accounting_method', __('business.accounting_method').':*') !!}{!! Form::select('accounting_method', $accounting_methods, old('accounting_method'), ['class' => 'form-control select2', 'required']) !!}</div></div>
            <div class="clearfix"></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('country', __('business.country').':*') !!}{!! Form::text('country', old('country'), ['class' => 'form-control', 'required', 'autocomplete' => 'country-name']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('state', __('business.state').':') !!}{!! Form::text('state', old('state'), ['class' => 'form-control', 'autocomplete' => 'address-level1']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('city', __('business.city').':*') !!}{!! Form::text('city', old('city'), ['class' => 'form-control', 'required', 'autocomplete' => 'address-level2']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('zip_code', __('business.zip_code').':') !!}{!! Form::text('zip_code', old('zip_code'), ['class' => 'form-control', 'autocomplete' => 'postal-code']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('landmark', __('business.landmark').':*') !!}{!! Form::text('landmark', old('landmark'), ['class' => 'form-control', 'required', 'autocomplete' => 'street-address']) !!}</div></div>
            <div class="col-md-6"><div class="form-group">{!! Form::label('mobile', __('lang_v1.business_telephone').':') !!}{!! Form::text('mobile', old('mobile'), ['class' => 'form-control', 'autocomplete' => 'tel', 'inputmode' => 'tel']) !!}</div></div>
        </div>
        <div class="box-footer"><button type="submit" class="btn btn-primary pull-right" @disabled(!$quota['can_create'])>Create company</button></div>
        {!! Form::close() !!}
    </div>
</section>
@endsection

@section('javascript')
<script>
    window.CASHERP_ONBOARDING_PROFILES = @json($industryProfiles ?? []);
    window.CASHERP_ONBOARDING_OLD_ANSWERS = @json(old('onboarding', []));
</script>
<script src="{{ asset('js/company_onboarding.js?v=' . config('constants.asset_version')) }}"></script>
<script>
    $(function () {
        $('.select2').select2({ width: '100%' });
    });
</script>
@endsection
