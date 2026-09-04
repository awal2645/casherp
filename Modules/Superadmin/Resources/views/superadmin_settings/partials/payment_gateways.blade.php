<div class="pos-tab-content">
    <div class="row">
        <div class="col-xs-6">
            <div class="form-group">
                <label>
                {!! Form::checkbox('enable_offline_payment', 1,!empty($settings["enable_offline_payment"]), 
                [ 'class' => 'input-icheck']); !!}
                @lang('superadmin::lang.enable_offline_payment')
                </label>
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('offline_payment_details', __('superadmin::lang.offline_payment_details') . ':') !!}
                @show_tooltip(__('superadmin::lang.offline_payment_details_tooltip'))
                {!! Form::textarea('offline_payment_details', !empty($settings["offline_payment_details"]) ? $settings["offline_payment_details"] : null, ['class' => 'form-control','placeholder' => __('superadmin::lang.offline_payment_details'), 'rows' => 3]); !!}
            </div>
        </div>
    </div>
    <div class="row">
    	<h4>Stripe:</h4>
    	<div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('STRIPE_PUB_KEY', __('superadmin::lang.stripe_pub_key') . ':') !!}
            	{!! Form::text('STRIPE_PUB_KEY', $default_values['STRIPE_PUB_KEY'], ['class' => 'form-control','placeholder' => __('superadmin::lang.stripe_pub_key')]); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('STRIPE_SECRET_KEY', __('superadmin::lang.stripe_secret_key') . ':') !!}
            	{!! Form::text('STRIPE_SECRET_KEY', $default_values['STRIPE_SECRET_KEY'], ['class' => 'form-control','placeholder' => __('superadmin::lang.stripe_secret_key')]); !!}
            </div>
        </div>

        <div class="clearfix"></div>
        
        <h4>Paypal:</h4>
        <div class="col-xs-6">
            <div class="form-group">
            	{!! Form::label('PAYPAL_MODE', __('superadmin::lang.paypal_mode') . ':') !!}
            	{!! Form::select('PAYPAL_MODE',['live' => 'Live', 'sandbox' => 'Sandbox'],  $default_values['PAYPAL_MODE'], ['class' => 'form-control','placeholder' => __('messages.please_select')]); !!}
                <b><span class="text-danger">@lang('superadmin::lang.important')</span>@lang('superadmin::lang.paypal_info') </b>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_CLIENT_ID', __('superadmin::lang.paypal_client_id') . ':') !!}
            	{!! Form::text('PAYPAL_CLIENT_ID', $default_values['PAYPAL_CLIENT_ID'], ['class' => 'form-control','placeholder' =>'Paypal client id']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
            	{!! Form::label('PAYPAL_APP_SECRET', __('superadmin::lang.paypal_aap_secret') . ':') !!}
            	{!! Form::text('PAYPAL_APP_SECRET', $default_values['PAYPAL_APP_SECRET'], ['class' => 'form-control','placeholder' =>'Paypal app secret']); !!}
            </div>
        </div>

        <div class="col-xs-4">
            <b>@lang('superadmin::lang.step_for_paypal') :</b><br/>
            1. @lang('superadmin::lang.login_to') <a href="https://developer.paypal.com/home" target="_blank">@lang('superadmin::lang.developer_account') </a>.<br/>
            @lang('superadmin::lang.paypal_step_2')<br/>
            @lang('superadmin::lang.paypal_step_3')<br/>
            @lang('superadmin::lang.paypal_step_4')<br/>
            @lang('superadmin::lang.paypal_step_5')<br/>
            @lang('superadmin::lang.paypal_step_6')<br/>
        </div>
        
        

        <div class="clearfix"></div>
        
        <h4>Razorpay: <small>(For INR India)</small></h4>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('RAZORPAY_KEY_ID', 'Key ID:') !!}
                {!! Form::text('RAZORPAY_KEY_ID', $default_values['RAZORPAY_KEY_ID'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('RAZORPAY_KEY_SECRET', 'Key Secret:') !!}
                {!! Form::text('RAZORPAY_KEY_SECRET', $default_values['RAZORPAY_KEY_SECRET'], ['class' => 'form-control']); !!}
            </div>
        </div>




        <div class="clearfix"></div>
        
        <h4>Pesapal: <small>(For KES currency)</small></h4>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('PESAPAL_CONSUMER_KEY', 'Consumer Key:') !!}
                {!! Form::text('PESAPAL_CONSUMER_KEY', $default_values['PESAPAL_CONSUMER_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('PESAPAL_CONSUMER_SECRET', 'Consumer Secret:') !!}
                {!! Form::text('PESAPAL_CONSUMER_SECRET', $default_values['PESAPAL_CONSUMER_SECRET'], ['class' => 'form-control']); !!}
            </div>
        </div>

        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('PESAPAL_LIVE', 'Is Live?') !!}
                {!! Form::select('PESAPAL_LIVE',['false' => 'False', 'true' => 'True'],  $default_values['PESAPAL_LIVE'], ['class' => 'form-control']); !!}
            </div>
        </div>

        <div class="clearfix"></div>
        
        <h4>Paystack: <small>(For NGN Nigeria, GHS Ghana)</small></h4>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('PAYSTACK_PUBLIC_KEY', 'Public key:') !!}
                {!! Form::text('PAYSTACK_PUBLIC_KEY', $default_values['PAYSTACK_PUBLIC_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('PAYSTACK_SECRET_KEY', 'Secret key:') !!}
                {!! Form::text('PAYSTACK_SECRET_KEY', $default_values['PAYSTACK_SECRET_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>

        <div class="clearfix"></div>
        
        <h4>Flutterwave:</h4>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('FLUTTERWAVE_PUBLIC_KEY', 'Public key:') !!}
                {!! Form::text('FLUTTERWAVE_PUBLIC_KEY', $default_values['FLUTTERWAVE_PUBLIC_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('FLUTTERWAVE_SECRET_KEY', 'Secret key:') !!}
                {!! Form::text('FLUTTERWAVE_SECRET_KEY', $default_values['FLUTTERWAVE_SECRET_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-4">
            <div class="form-group">
                {!! Form::label('FLUTTERWAVE_ENCRYPTION_KEY', 'Encryption key:') !!}
                {!! Form::text('FLUTTERWAVE_ENCRYPTION_KEY', $default_values['FLUTTERWAVE_ENCRYPTION_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-12 mt-0">
            <p class="help-block mt-0">
                <a href="https://flutterwave.com/tz/support/general/what-are-the-currencies-accepted-on-flutterwave" target="_blank">
                    @lang('superadmin::lang.flutterwave_help_text')
                </a>
            </p>
        </div>
        <div class="clearfix"></div>

        <h4>DPO Pay by Network: <small>Hosted card and mobile payments</small></h4>
        <div class="col-xs-3">
            <div class="form-group">
                <label>
                    {!! Form::checkbox('DPO_ENABLED', 1, $default_values['DPO_ENABLED'] === 'true', ['class' => 'input-icheck']) !!}
                    Enable DPO Pay
                </label>
                <p class="help-block">Keep Test mode selected until DPO confirms the integration.</p>
            </div>
        </div>
        <div class="col-xs-3">
            <div class="form-group">
                {!! Form::label('DPO_MODE', 'Mode:') !!}
                {!! Form::select('DPO_MODE', ['test' => 'Test', 'live' => 'Live'], $default_values['DPO_MODE'], ['class' => 'form-control']) !!}
            </div>
        </div>
        <div class="col-xs-3">
            <div class="form-group">
                {!! Form::label('DPO_SERVICE_TYPE', 'Service type:') !!}
                {!! Form::number('DPO_SERVICE_TYPE', $default_values['DPO_SERVICE_TYPE'], ['class' => 'form-control', 'min' => 1, 'step' => 1, 'placeholder' => 'Provided by DPO']) !!}
            </div>
        </div>
        <div class="col-xs-3">
            <div class="form-group">
                {!! Form::label('DPO_SERVICE_DESCRIPTION', 'Service description:') !!}
                {!! Form::text('DPO_SERVICE_DESCRIPTION', $default_values['DPO_SERVICE_DESCRIPTION'], ['class' => 'form-control', 'maxlength' => 100]) !!}
            </div>
        </div>
        <div class="col-xs-6">
            <div class="form-group">
                {!! Form::label('DPO_COMPANY_TOKEN', 'Company token:') !!}
                <input type="password" name="DPO_COMPANY_TOKEN" id="DPO_COMPANY_TOKEN" class="form-control"
                    autocomplete="new-password" placeholder="{{ $default_values['DPO_COMPANY_TOKEN_CONFIGURED'] ? 'Configured - leave blank to keep it' : 'Enter the DPO company token' }}">
                <p class="help-block">The token is stored in the server environment and is never sent to the browser.</p>
            </div>
        </div>
        <div class="col-xs-3">
            <div class="form-group" style="padding-top: 26px;">
                <label>
                    {!! Form::checkbox('CLEAR_DPO_COMPANY_TOKEN', 1, false, ['class' => 'input-icheck']) !!}
                    Clear saved token and disable DPO Pay
                </label>
            </div>
        </div>
        <div class="col-xs-3">
            <p class="help-block" style="padding-top: 24px;">
                <a href="https://docs.dpopay.com/dpo-pay-by-network/reference/create-token" target="_blank" rel="noopener noreferrer">DPO Pay integration documentation</a>
            </p>
        </div>
        <div class="clearfix"></div>

        <h4>@lang('superadmin::lang.my_fatoorah'): </h4>
        <div class="col-xs-3">
            <div class="form-group">
                {!! Form::label('MY_FATOORAH_API_KEY', 'Api key:') !!}
                {!! Form::text('MY_FATOORAH_API_KEY', $default_values['MY_FATOORAH_API_KEY'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-3">
            <div class="form-group">
                {!! Form::label('MY_FATOORAH_COUNTRY_ISO', 'Country iso:') !!}
                {!! Form::select('MY_FATOORAH_COUNTRY_ISO',["KWT" => "KWT", "SAU" => "SAU", "ARE" => "ARE", "QAT" => "QAT", "BHR" => "BHR", "OMN" => "OMN", "JOD" => "JOD", "EGY" => "EGY"],  $default_values['MY_FATOORAH_COUNTRY_ISO'], ['class' => 'form-control']); !!}

            </div>
        </div>
        <div class="col-xs-3">
            <div class="form-group">
                {!! Form::label('MY_FATOORAH_IS_TEST', 'Is Test?') !!}
                {!! Form::select('MY_FATOORAH_IS_TEST',['false' => 'False', 'true' => 'True'],  $default_values['MY_FATOORAH_IS_TEST'], ['class' => 'form-control']); !!}
            </div>
        </div>
        <div class="col-xs-3">
            <a href="https://docs.myfatoorah.com/docs/overview" target="_blank">@lang('superadmin::lang.api_key_document')</a> <br>
            <b>@lang('superadmin::lang.fatoorah_heahing')</b><br/>
            1. @lang('superadmin::lang.login_to') <a href="https://portal.myfatoorah.com/En/All/Account/LogIn" target="_blank">@lang('superadmin::lang.myfatoorah_account')</a> @lang('superadmin::lang.using_your_super_master_account')<br/>
            @lang('superadmin::lang.fatoorah_step_2')<br/>
            @lang('superadmin::lang.fatoorah_step_3')<br/>
            @lang('superadmin::lang.fatoorah_step_4')<br/>
            @lang('superadmin::lang.fatoorah_step_5')<br/>
        </div>
        <div class="col-xs-12">
            <br/>
            <p class="help-block"><i>@lang('superadmin::lang.payment_gateway_help')</i></p>
        </div>
    </div>
</div>
