@php
    $roleWiseLogins = [
        ['label' => 'Admin', 'username' => 'admin', 'hint' => 'Main company'],
        ['label' => 'Cashier', 'username' => 'cashier', 'hint' => 'POS / sales'],
        ['label' => 'Superadmin', 'username' => 'superadmin', 'hint' => 'SaaS admin'],
        ['label' => 'Pharmacy', 'username' => 'admin-pharmacy', 'hint' => 'Awesome Pharmacy'],
        ['label' => 'Electronics', 'username' => 'admin-electronics', 'hint' => 'Ultimate Electronics'],
        ['label' => 'Services', 'username' => 'admin-services', 'hint' => 'Awesome Services'],
        ['label' => 'Restaurant', 'username' => 'admin-restaurant', 'hint' => 'Awesome Restaurant'],
        ['label' => 'Manufacturer', 'username' => 'manufacturer-demo', 'hint' => 'Manufacturers Demo'],
    ];
@endphp

@if(config('constants.enable_demo_quick_login', true))
    <div class="role-wise-login" data-testid="role-wise-login">
        <p class="role-wise-login-title">Role-wise login</p>
        <p class="role-wise-login-copy">Click a role to sign in immediately. Demo password is <strong>123456</strong>.</p>
        <div class="role-wise-login-grid">
            @foreach($roleWiseLogins as $demoLogin)
                <form method="POST" action="{{ route('login') }}" class="role-wise-login-form">
                    {{ csrf_field() }}
                    <input type="hidden" name="username" value="{{ $demoLogin['username'] }}">
                    <input type="hidden" name="password" value="123456">
                    <button type="submit" class="role-login-btn" data-username="{{ $demoLogin['username'] }}">
                        <span>{{ $demoLogin['label'] }}</span>
                        <small>{{ $demoLogin['hint'] }}</small>
                    </button>
                </form>
            @endforeach
        </div>
        <div class="or-sep"><span>OR ENTER DETAILS</span></div>
    </div>
@endif
