<div class="col-md-12">
@php
$currency_code = strtolower($system_currency->code);
@endphp
    <form method="post" action="{{route('subscription.myfatoorah')}}">
        {{ csrf_field() }}
        <!-- customer details -->
        <!-- Package and coupon are identifiers; price, currency and customer are resolved server-side. -->
        <input type="hidden" name="coupon_code" value="{{$checkoutCouponCode}}">
        <input type="hidden" name="package_id" value="{{ $package->id }}">


        <!-- additional info -->

        <!-- transaction ref -->
        <button class="btn btn-sm text-white" type="submit" style="background: #08A5DB;border-color: #08A5DB;">
            <i class="fas fa-align-left text-white"></i>
            {{$v}}
        </button>
    </form>
</div>
