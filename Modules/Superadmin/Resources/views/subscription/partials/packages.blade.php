@php
	$count = 0;
@endphp
@foreach ($packages as $package)
	@php($count++)
	@include('superadmin::subscription.partials.package_card')
	
@endforeach
@if($count === 0)
    <div class="tw-w-full tw-bg-white tw-rounded-2xl tw-p-8 tw-text-center tw-shadow-sm">
        <h3 class="tw-text-lg tw-font-semibold tw-text-gray-900">No plans are available for this billing period.</h3>
        <p class="tw-text-sm tw-text-gray-500 tw-mb-0">Choose another billing period or contact CashERP for a tailored plan.</p>
    </div>
@endif
