<form method="post" action="{{ route('subscription.dpo.create', ['package_id' => $package->id]) }}">
    @csrf
    @if(!empty($checkoutCouponCode))
        <input type="hidden" name="coupon_code" value="{{ $checkoutCouponCode }}">
    @endif
    <button type="submit" class="tw-dw-btn tw-dw-btn-primary tw-text-white tw-dw-btn-sm">
        <i class="fa fa-lock" aria-hidden="true"></i> Pay securely with DPO Pay
    </button>
    <p class="help-block mb-0">
        You will complete card or supported mobile payment on DPO Pay's secure hosted checkout.
    </p>
</form>
