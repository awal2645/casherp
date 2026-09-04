<div class="pos-tab-content">
    <div class="row">
        <div class="col-xs-12">
            <h4>Google sign-in and sign-up</h4>
            <p class="help-block">
                Use an OAuth 2.0 Web application in Google Cloud. Add the exact callback below as an authorised redirect URI.
                Secrets are written to the server environment and are never rendered back into this page.
            </p>
        </div>
        <div class="col-md-4">
            <div class="form-group">
                <label>
                    {!! Form::checkbox('GOOGLE_SOCIAL_LOGIN_ENABLED', 1, $default_values['GOOGLE_SOCIAL_LOGIN_ENABLED'] === 'true', ['class' => 'input-icheck']) !!}
                    Enable Google sign-in
                </label>
                <p class="help-block">Keep disabled until the client ID, client secret and HTTPS callback are configured.</p>
            </div>
        </div>
        <div class="col-md-8">
            <div class="form-group">
                {!! Form::label('GOOGLE_OAUTH_REDIRECT_URI', 'Authorised callback URI:') !!}
                {!! Form::url('GOOGLE_OAUTH_REDIRECT_URI', $default_values['GOOGLE_OAUTH_REDIRECT_URI'], ['class' => 'form-control', 'required', 'maxlength' => 2048]) !!}
                <p class="help-block">Production value: https://www.casherp.com/auth/google/callback</p>
            </div>
        </div>
        <div class="clearfix"></div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('GOOGLE_OAUTH_CLIENT_ID', 'Google OAuth client ID:') !!}
                {!! Form::text('GOOGLE_OAUTH_CLIENT_ID', $default_values['GOOGLE_OAUTH_CLIENT_ID'], ['class' => 'form-control', 'autocomplete' => 'off', 'maxlength' => 512]) !!}
            </div>
        </div>
        <div class="col-md-6">
            <div class="form-group">
                {!! Form::label('GOOGLE_OAUTH_CLIENT_SECRET', 'Google OAuth client secret:') !!}
                {!! Form::password('GOOGLE_OAUTH_CLIENT_SECRET', [
                    'class' => 'form-control',
                    'autocomplete' => 'new-password',
                    'maxlength' => 4096,
                    'placeholder' => $default_values['GOOGLE_OAUTH_CLIENT_SECRET_CONFIGURED']
                        ? 'Configured - leave blank to keep it'
                        : 'Enter the Google OAuth client secret',
                ]) !!}
                @if($default_values['GOOGLE_OAUTH_CLIENT_SECRET_CONFIGURED'])
                    <label class="tw-mt-2">
                        {!! Form::checkbox('CLEAR_GOOGLE_OAUTH_CLIENT_SECRET', 1, false, ['class' => 'input-icheck']) !!}
                        Clear the saved client secret and disable Google sign-in
                    </label>
                @endif
            </div>
        </div>
    </div>
</div>
