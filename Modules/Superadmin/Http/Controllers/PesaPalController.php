<?php

namespace Modules\Superadmin\Http\Controllers;

use Modules\Superadmin\Entities\Subscription;
use App\Services\SubscriptionPricingService;
use Illuminate\Support\Facades\DB;

class PesaPalController extends BaseController
{
    //This method get called from app/Http/Controllers/PesaPalController
    public function pesaPalPaymentConfirmation($transaction_id, $status, $payment_method, $merchant_reference)
    {
        \Log::info('PesaPal subscription status received', [
            'transaction_id' => $transaction_id,
            'status' => $status,
            'payment_method' => $payment_method,
        ]);

        DB::transaction(function () use ($transaction_id, $status) {
            $subscription = Subscription::where('paid_via', 'pesapal')
                ->where('payment_transaction_id', $transaction_id)
                ->lockForUpdate()
                ->first();
            if (! $subscription) {
                \Log::warning('PesaPal subscription transaction was not found', ['transaction_id' => $transaction_id]);
                return;
            }

            if (strtoupper((string) $status) === 'COMPLETED') {
                if ($subscription->status !== 'approved') {
                    app(SubscriptionPricingService::class)->assertResourceCompatibility(
                        $subscription->package,
                        (int) $subscription->business_id
                    );
                    $dates = $this->_get_package_dates($subscription->business_id, $this->packageTermSnapshot($subscription));
                    $subscription->update([
                        'status' => 'approved',
                        'start_date' => $dates['start'],
                        'end_date' => $dates['end'],
                        'trial_end_date' => $dates['trial'],
                    ]);
                }
                return;
            }

            $subscription->update([
                'status' => 'waiting',
                'start_date' => null,
                'end_date' => null,
                'trial_end_date' => null,
            ]);
        });
    }
}
