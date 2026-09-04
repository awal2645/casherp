@php

	$random_reference = Pesapal::random_reference();
	session(['pesapal' => [
		'ref' => $random_reference,
		'package_id' => $package->id,
		'business_id' => $user['business_id'],
		'user_id' => $user['id'],
		'coupon_code' => $checkoutCouponCode,
		'amount' => $checkoutAmount,
		'currency' => $checkoutCurrency,
	]]);

	$details = [
	        'amount' => $checkoutAmount,
	        'description' => $package->name,
	        'type' => 'MERCHANT',
	        'first_name' => $user['first_name'],
	        'last_name' => $user['last_name'],
	        'email' => $user['email'],
	        'phonenumber' => '',
	        'height' => '400px',
	        'reference' => $random_reference,
    	];
    $iframe = Pesapal::makePayment($details);
@endphp

<div class="col-md-12">
	{!! $iframe !!}
</div>
