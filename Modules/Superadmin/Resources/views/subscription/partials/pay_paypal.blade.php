		
		<div id="paypal-button-container" style="padding:30px;"></div>
     
		<script src="https://www.paypal.com/sdk/js?client-id={{ config('paypal.client_id') }}&currency={{ $system_currency->code }}"></script>


<script>
paypal.Buttons({
	// Order is created on the server and the order id is returned
	createOrder() {
		const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
		return fetch("{{ route('paypalExpressCheckout') }}", {
			method: "POST",
			headers: {
			"Content-Type": "application/json",
			"X-CSRF-TOKEN": csrfToken,
			},
		// use the "body" param to optionally pass additional order information
		// like product skus and quantities
		body: JSON.stringify({
		  package_id: '{{ $package->id }}',
		  coupon_code: '{{ $checkoutCouponCode }}',
		}),
	  })
	  .then((response) => response.json())
	  .then((order) => order.id);
	},
	// Finalize the transaction on the server after payer approval
	onApprove(data) {
		
		const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
	  	return fetch("{{ route('capturePaypalOrder') }}", {
		method: "POST",
		headers: {
		  "Content-Type": "application/json",
		  "X-CSRF-TOKEN": csrfToken,
		},
		body: JSON.stringify({
			orderID: data.orderID,
			package_id: "{{$package->id}}",
			coupon_code: "{{ $checkoutCouponCode }}",
		})
	  })
	  .then((response) => response.json())
	  .then((responseData) => {
		if(responseData.success){
			toastr.success(responseData.msg);
			window.location.href = "{{ route('subscription.index') }}";
		}else{
			toastr.error(responseData.msg);
		}
	  });
	}
  }).render('#paypal-button-container');
</script>
