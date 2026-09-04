@extends('layouts.auth')
@section('title', __('superadmin::lang.pricing'))
@section('css')
    <link rel="stylesheet" href="{{ asset('css/casherp_onboarding.css?v=' . config('constants.asset_version')) }}">
@endsection

@section('content')
    <div class="">
        @include('superadmin::layouts.partials.currency')
        <div class="pricing casherp-pricing-shell">
            <div class="tw-mt-14 tw-px-4">
                <div class="tw-flex tw-flex-col tw-items-center">

                    <div class="tw-flex tw-flex-col tw-gap-2 tw-text-center">
                        <span class="tw-text-xs tw-uppercase tw-tracking-widest tw-font-semibold tw-text-blue-200">Simple, transparent ERP pricing</span>
                        <h2 class="tw-font-bold tw-text-3xl md:tw-text-4xl tw-text-white">A plan that grows with every company</h2>
                        <h3 class="tw-text-sm md:tw-text-base tw-font-normal tw-text-blue-100 tw-max-w-2xl">
                            Compare companies, locations, users, invoices, and premium-module allowances before registering. Upgrade capacity without moving or mixing company data.
                        </h3>
                    </div>
                    <!-- Montly/annual-->
                    <div class="tw-flex tw-gap-2 mt-5 md:tw-mt-5">
                        <span class="tw-text-white">Monthly</span>
                        <input type="checkbox" id="durationCheck" class="tw-dw-toggle tw-dw-toggle-secondary duration_check"
                            style="margin: 0px" />

                        <span class="tw-flex tw-flex-col tw-text-white"> Annual </span>
                    </div>
                </div>

                {{-- <div class="box-body tw-mt-6"> --}}
                <div class="tw-flex tw-flex-col md:tw-flex-row tw-gap-5 md:tw-gap-0 tw-mt-5 md:tw-mt-7 tw-mb-10 tw-h-auto"
                    id="packages" aria-live="polite" aria-busy="true">
                    {{-- @include('superadmin::subscription.partials.packages', [
                            'action_type' => 'register',
                        ]) --}}
                </div>
                @if($premiumModules->isNotEmpty())
                    <div class="tw-max-w-6xl tw-mx-auto tw-mb-12">
                        <div class="tw-text-center tw-mb-5">
                            <h2 class="tw-text-2xl tw-font-bold tw-text-white">Optional premium modules</h2>
                            <p class="tw-text-blue-100">Start with the package allowance and add clear capacity blocks when your team grows.</p>
                        </div>
                        <div class="tw-grid tw-grid-cols-1 md:tw-grid-cols-2 lg:tw-grid-cols-4 tw-gap-4">
                            @foreach($premiumModules as $module)
                                <div class="tw-bg-white tw-rounded-2xl tw-p-5 tw-shadow-lg tw-border tw-border-white/20">
                                    <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">{{ $module->name }}</h3>
                                    <p class="tw-text-sm tw-text-gray-500 tw-min-h-[40px]">{{ $module->description }}</p>
                                    <p class="tw-font-semibold tw-text-gray-900">
                                        {{ (int)$module->included_allowance === 0 ? 'Unlimited' : number_format($module->included_allowance) }} {{ $module->allowance_name }}
                                        @if($module->allowance_reset === 'monthly') <small>per month</small> @endif
                                    </p>
                                    @if($module->capacity_increment > 0)
                                        <p class="tw-text-sm tw-text-gray-600 tw-mb-0">Extra: +{{ number_format($module->capacity_increment) }} for <span class="display_currency" data-use_page_currency="true" data-currency_symbol="true">{{ $module->capacity_price }}</span></p>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
                {{-- </div> --}}
            </div>
        </div>
    </div>
@stop

@section('javascript')
    <script type="text/javascript">
        $(document).ready(function() {
            $('.change_lang').click(function() {
                window.location = "{{ route('pricing')}}?lang=" + $(this).attr('value');
            });

            $('#durationCheck').off('change').on('change', function() {
                var interval = $(this).is(':checked') ? 'years' : 'months';
                set_packages(interval);
            });

            function set_packages(interval) {
                $('#packages').attr('aria-busy', 'true').html('<div class="tw-w-full tw-text-center tw-text-white tw-py-10"><i class="fa fa-spinner fa-spin" aria-hidden="true"></i> Loading packages...</div>');
                $.ajax({
                    method: 'get',
                    url: "{{ route('package_duration_update') }}",
                    dataType: 'html',
                    data: {
                        interval: interval
                    },
                    success: function(response) {
                        $('#packages').html(response);
                        $('#packages').attr('aria-busy', 'false');
                        // this function use for formate currency
                        __currency_convert_recursively($('.price_card'))
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        $('#packages').attr('aria-busy', 'false').html('<div class="alert alert-danger tw-w-full">Packages could not be loaded. Please refresh and try again.</div>');
                    },
                });
            }
            set_packages('months');
        })
    </script>
@endsection
