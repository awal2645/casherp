@php
    $restaurantOperationPermissions = config('restaurant_operations.permissions', []);
    $restaurantOperationsEnabled = app(\App\Services\FeatureAccessService::class)
        ->enabled('restaurant_operations', (int) session('user.business_id'));
@endphp
@if(!empty($restaurantOperationPermissions) && $restaurantOperationsEnabled)
    <div class="row check_group">
        <div class="col-md-3">
            <h4>Restaurant Operations</h4>
            <p class="help-block">Kitchen, recipes, fulfilment, reservations, waiter requests, and register controls.</p>
        </div>
        <div class="col-md-2">
            <div class="checkbox">
                <label><input type="checkbox" class="check_all input-icheck"> {{ __('role.select_all') }}</label>
            </div>
        </div>
        <div class="col-md-7">
            @foreach($restaurantOperationPermissions as $permission => $label)
                <div class="checkbox">
                    <label>
                        {!! Form::checkbox(
                            'permissions[]',
                            $permission,
                            isset($role_permissions) && in_array($permission, $role_permissions),
                            ['class' => 'input-icheck']
                        ) !!}
                        {{ $label }}
                    </label>
                </div>
            @endforeach
        </div>
    </div>
    <hr>
@endif
