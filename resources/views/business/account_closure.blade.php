@extends('layouts.app')
@section('title', 'Company Account & Data')

@section('content')
<section class="content-header">
    <h1>Company Account & Data <small>Secure closure and recovery controls</small></h1>
</section>

<section class="content">
    @if($errors->any())
        <div class="alert alert-danger" role="alert">
            <strong>The request was not completed.</strong>
            <ul class="tw-mb-0 tw-mt-2">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        </div>
    @endif

    <div class="box box-default">
        <div class="box-header with-border"><h3 class="box-title">{{ $business->name }}</h3></div>
        <div class="box-body">
            <div class="row">
                <div class="col-md-4"><strong>Industry</strong><p>{{ optional($business->industry)->name ?: 'Not set' }}</p></div>
                <div class="col-md-4"><strong>Locations</strong><p>{{ $business->locations()->count() }}</p></div>
                <div class="col-md-4"><strong>Status</strong><p>{{ $business->is_active ? 'Active' : 'Inactive' }}</p></div>
            </div>
            <p class="text-muted tw-mb-0">Closing this company never affects another company under the same login. Company records are isolated by business ID.</p>
        </div>
    </div>

    @if($closure)
        <div class="box box-warning">
            <div class="box-header with-border"><h3 class="box-title">Closure scheduled</h3></div>
            <div class="box-body">
                <p>Access will be closed after <strong>{{ @format_datetime($closure->scheduled_for) }}</strong>.</p>
                <p class="text-muted">The recovery window protects against accidental or unauthorised deletion. Financial and statutory records may be retained after access closes where applicable law or the company’s configured retention policy requires it.</p>
                {!! Form::open(['route' => 'business.account-closure.cancel', 'method' => 'delete', 'class' => 'form-inline']) !!}
                    <div class="form-group">
                        <label for="cancel_password">Confirm your password:</label>
                        <input id="cancel_password" class="form-control" type="password" name="password" required autocomplete="current-password">
                    </div>
                    <button class="btn btn-success"><i class="fa fa-undo"></i> Cancel closure</button>
                {!! Form::close() !!}
            </div>
        </div>
    @else
        <div class="box box-danger">
            <div class="box-header with-border"><h3 class="box-title">Close this company</h3></div>
            {!! Form::open(['route' => 'business.account-closure.schedule', 'method' => 'post']) !!}
            <div class="box-body">
                <div class="alert alert-warning">
                    <strong>This affects the active company only.</strong> After the {{ $recoveryDays }}-day recovery window, the company is deactivated, subscriptions end, and public document-sharing links are revoked. Other companies under the login remain separate and active.
                </div>
                <div class="form-group">
                    <label for="closure_reason">Reason (optional)</label>
                    <textarea id="closure_reason" name="reason" class="form-control" rows="3" maxlength="2000">{{ old('reason') }}</textarea>
                </div>
                <div class="form-group">
                    <label for="confirmation_name">Type <strong>{{ $business->name }}</strong> exactly to confirm</label>
                    <input id="confirmation_name" class="form-control" name="confirmation_name" value="{{ old('confirmation_name') }}" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label for="closure_password">Your password</label>
                    <input id="closure_password" class="form-control" type="password" name="password" required autocomplete="current-password">
                </div>
                <div class="checkbox">
                    <label><input type="checkbox" name="acknowledge" value="1" required> I understand this closes the selected company and does not close my other companies.</label>
                </div>
            </div>
            <div class="box-footer">
                <a class="btn btn-default" href="{{ route('business.getBusinessSettings') }}">Cancel</a>
                <button class="btn btn-danger pull-right" type="submit" onclick="return confirm('Schedule this company for closure?')">
                    <i class="fa fa-trash"></i> Schedule company closure
                </button>
            </div>
            {!! Form::close() !!}
        </div>
    @endif
</section>
@endsection
