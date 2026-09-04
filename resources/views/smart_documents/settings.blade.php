@extends('layouts.app')
@section('title', 'Document Settings')

@section('content')
<section class="content-header">
    <h1>Document Settings <small>Company-level names, numbering and approved terms</small></h1>
</section>
<section class="content">
    <div class="callout callout-info">
        <h4><i class="fa fa-shield"></i> Controlled company defaults</h4>
        <p>Your industry determines the available document families. You may disable an unused type, change its display name and prefix, add company terms, and restrict it to selected users, company roles, or HR departments. If no access restriction is selected, access follows the document-type permissions assigned on the Roles page. Agreement terms must be reviewed by an authorized company administrator before an agreement can be issued.</p>
    </div>
    {!! Form::open(['route' => 'smart-documents.settings.update', 'method' => 'put']) !!}
    <div class="box box-primary">
        <div class="box-body table-responsive">
            <table class="table table-bordered">
                <thead><tr><th style="width:90px">Enabled</th><th>Industry document</th><th style="width:240px">Company display name</th><th style="width:140px">Number prefix</th><th>Default terms / conditions</th><th style="width:150px">Legal review</th></tr></thead>
                <tbody>
                    @foreach($types as $type)
                        @php
                            $setting = $settings->get($type->id);
                            $access = (array) data_get(optional($setting)->settings, 'access', []);
                            $selectedRoles = old("settings.{$type->id}.allowed_role_ids", $access['role_ids'] ?? []);
                            $selectedDepartments = old("settings.{$type->id}.allowed_department_ids", $access['department_ids'] ?? []);
                            $selectedUsers = old("settings.{$type->id}.allowed_user_ids", $access['user_ids'] ?? []);
                        @endphp
                        <tr>
                            <td class="text-center"><input type="hidden" name="settings[{{ $type->id }}][is_enabled]" value="0"><input type="checkbox" name="settings[{{ $type->id }}][is_enabled]" value="1" @checked(old("settings.{$type->id}.is_enabled", optional($setting)->is_enabled))></td>
                            <td><strong>{{ $type->pivot->display_name ?: $type->name }}</strong><br><small class="text-muted">{{ ucfirst($type->category) }} · {{ ucfirst(str_replace('_', ' ', $type->scenario_code)) }}</small></td>
                            <td><input class="form-control" name="settings[{{ $type->id }}][display_name]" value="{{ old("settings.{$type->id}.display_name", optional($setting)->display_name) }}" maxlength="255" placeholder="Use industry default"></td>
                            <td><input class="form-control text-uppercase" name="settings[{{ $type->id }}][prefix]" value="{{ old("settings.{$type->id}.prefix", optional($setting)->prefix ?: $type->default_prefix) }}" maxlength="20" required pattern="[A-Za-z0-9-]{2,20}"></td>
                            <td><textarea class="form-control" rows="4" name="settings[{{ $type->id }}][default_terms]" placeholder="Company-approved terms for this document type">{{ old("settings.{$type->id}.default_terms", optional($setting)->default_terms) }}</textarea></td>
                            <td>
                                @if($type->requires_acceptance)
                                    <input type="hidden" name="settings[{{ $type->id }}][terms_reviewed]" value="0">
                                    <label class="checkbox-inline"><input type="checkbox" name="settings[{{ $type->id }}][terms_reviewed]" value="1" @checked(old("settings.{$type->id}.terms_reviewed", !empty(optional($setting)->terms_reviewed_at)))> Reviewed</label>
                                    @if(optional($setting)->terms_reviewed_at)<br><small class="text-success">Reviewed {{ optional($setting)->terms_reviewed_at->format('Y-m-d') }}</small>@endif
                                @else
                                    <span class="text-muted">Not required</span>
                                @endif
                            </td>
                        </tr>
                        <tr class="active">
                            <td></td>
                            <td colspan="5">
                                <div class="row">
                                    <div class="col-md-4"><div class="form-group"><label>Restrict to company roles</label>{!! Form::select("settings[{$type->id}][allowed_role_ids][]", $roles, $selectedRoles, ['class' => 'form-control select2', 'multiple' => true, 'style' => 'width:100%', 'data-placeholder' => 'No role restriction']) !!}</div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Restrict to HR departments</label>{!! Form::select("settings[{$type->id}][allowed_department_ids][]", $departments, $selectedDepartments, ['class' => 'form-control select2', 'multiple' => true, 'style' => 'width:100%', 'data-placeholder' => 'No department restriction']) !!}</div></div>
                                    <div class="col-md-4"><div class="form-group"><label>Restrict to named users</label>{!! Form::select("settings[{$type->id}][allowed_user_ids][]", $users, $selectedUsers, ['class' => 'form-control select2', 'multiple' => true, 'style' => 'width:100%', 'data-placeholder' => 'No user restriction']) !!}</div></div>
                                </div>
                                <small class="text-muted"><i class="fa fa-lock"></i> When any selection is made, a staff member may use this document type only if they match at least one selected user, role, or department. Action permissions such as View, Create, Issue, Void, and Share are still enforced.</small>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <div class="box-header with-border"><h3 class="box-title">Company payment methods</h3></div>
        <div class="box-body">
            <p class="text-muted">These names and choices apply only to this company. Credit and Complimentary are commercial arrangements, not money received, so they never appear in receipt or deposit posting forms.</p>
            <div class="row">
                @foreach($paymentMethods as $method)
                    <div class="col-md-4"><div class="form-group">
                        <input type="hidden" name="payment_methods[{{ $method->code }}][is_enabled]" value="0">
                        <label><input type="checkbox" name="payment_methods[{{ $method->code }}][is_enabled]" value="1" @checked($method->is_enabled)> {{ $method->accepts_money ? 'Accept method' : 'Allow arrangement' }}</label>
                        <input class="form-control" name="payment_methods[{{ $method->code }}][name]" value="{{ old('payment_methods.'.$method->code.'.name', $method->name) }}" maxlength="100" required>
                        <small class="text-muted">{{ ucfirst(str_replace('_',' ',$method->kind)) }}{{ $method->accepts_money ? '' : ' — no money received' }}</small>
                    </div></div>
                @endforeach
            </div>
        </div>
        <div class="box-footer"><a href="{{ route('smart-documents.index') }}" class="btn btn-default">Back</a><button class="btn btn-primary pull-right"><i class="fa fa-save"></i> Save company document &amp; payment settings</button></div>
    </div>
    {!! Form::close() !!}
</section>
@endsection
