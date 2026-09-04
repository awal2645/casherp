<?php

// use App\Http\Controllers\BusinessController;
// use App\Http\Controllers\Modules;
// use Illuminate\Support\Facades\Route;

Route::get('/pricing', [Modules\Superadmin\Http\Controllers\PricingController::class, 'index'])->name('pricing');
Route::get('/package-duration-update', [Modules\Superadmin\Http\Controllers\PricingController::class, 'package_duration_update'])->name('package_duration_update');

// DPO Pay returns through the browser and may do so after the login session
// expires. The signed local reference plus server-to-server verifyToken call
// authenticate the payment; no browser result activates a subscription.
Route::middleware('web', 'throttle:30,1')->get(
    '/subscription/dpo/callback',
    [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'dpoCallback']
)->name('subscription.dpo.callback');

Route::middleware('web', 'auth', 'language', 'AdminSidebarMenu', 'superadmin')->prefix('superadmin')->group(function () {
    Route::get('/install', [Modules\Superadmin\Http\Controllers\InstallController::class, 'index']);
    Route::post('/install', [Modules\Superadmin\Http\Controllers\InstallController::class, 'install']);
    Route::get('/install/update', [Modules\Superadmin\Http\Controllers\InstallController::class, 'update']);
    Route::get('/install/uninstall', [Modules\Superadmin\Http\Controllers\InstallController::class, 'uninstall']);
    Route::post('/install/update', [Modules\Superadmin\Http\Controllers\InstallController::class, 'updateExecute']);

    Route::get('/', [Modules\Superadmin\Http\Controllers\SuperadminController::class, 'index']);
    Route::get('/stats', [Modules\Superadmin\Http\Controllers\SuperadminController::class, 'stats']);

    Route::get('/{business_id}/toggle-active/{is_active}', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'toggleActive']);

    Route::get('/users/{business_id}', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'usersList']);
    Route::post('/update-password', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'updatePassword']);

    Route::resource('/business', Modules\Superadmin\Http\Controllers\BusinessController::class);
    Route::get('/business/{id}/destroy', [Modules\Superadmin\Http\Controllers\BusinessController::class, 'destroy']);

    Route::resource('/packages', 'Modules\Superadmin\Http\Controllers\PackagesController');

    Route::resource('/coupons', 'Modules\Superadmin\Http\Controllers\CouponController');
    
    Route::get('/settings', [Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'edit']);
    Route::put('/settings', [Modules\Superadmin\Http\Controllers\SuperadminSettingsController::class, 'update']);
    Route::get('/industry-features', [\App\Http\Controllers\Superadmin\IndustryFeatureController::class, 'index'])->name('superadmin.industry-features.index');
    Route::put('/industry-features/{industry}', [\App\Http\Controllers\Superadmin\IndustryFeatureController::class, 'update'])->name('superadmin.industry-features.update');
    Route::get('/industry-documents', [\App\Http\Controllers\Superadmin\IndustryDocumentProfileController::class, 'index'])->name('superadmin.industry-documents.index');
    Route::put('/industry-documents/{industry}', [\App\Http\Controllers\Superadmin\IndustryDocumentProfileController::class, 'update'])->name('superadmin.industry-documents.update');
    Route::get('/premium-modules', [\App\Http\Controllers\Superadmin\PremiumModulePlanController::class, 'index'])->name('superadmin.premium-modules.index');
    Route::post('/premium-modules', [\App\Http\Controllers\Superadmin\PremiumModulePlanController::class, 'store'])->name('superadmin.premium-modules.store');
    Route::put('/premium-modules/{premiumModulePlan}', [\App\Http\Controllers\Superadmin\PremiumModulePlanController::class, 'update'])->name('superadmin.premium-modules.update');
    Route::put('/premium-modules/{premiumModulePlan}/packages', [\App\Http\Controllers\Superadmin\PremiumModulePlanController::class, 'updatePackages'])->name('superadmin.premium-modules.packages.update');
    Route::post('/premium-module-orders/{order}/approve', [\App\Http\Controllers\Superadmin\PremiumModulePlanController::class, 'approve'])->name('superadmin.premium-module-orders.approve');
    Route::post('/premium-module-orders/{order}/decline', [\App\Http\Controllers\Superadmin\PremiumModulePlanController::class, 'decline'])->name('superadmin.premium-module-orders.decline');
    Route::get('/edit-subscription/{id}', [Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'editSubscription']);
    Route::post('/update-subscription', [Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController::class, 'updateSubscription']);
    Route::resource('/superadmin-subscription', 'Modules\Superadmin\Http\Controllers\SuperadminSubscriptionsController');

    Route::get('/communicator', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'index']);
    Route::post('/communicator/send', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'send']);
    Route::get('/communicator/get-history', [Modules\Superadmin\Http\Controllers\CommunicatorController::class, 'getHistory']);

    Route::resource('/frontend-pages', 'Modules\Superadmin\Http\Controllers\PageController');
});

Route::middleware('web', 'SetSessionData', 'auth', 'language', 'timezone', 'AdminSidebarMenu')->group(function () {
    //Routes related to paypal checkout
    Route::post('/paypal-express-checkout', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'paypalExpressCheckout'])->name('paypalExpressCheckout');

    Route::post('/capture-paypal-order', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'capturePaypalOrder'])->name('capturePaypalOrder');


    Route::get('/subscription/post-flutterwave-payment', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postFlutterwavePaymentCallback']);

    Route::post('/subscription/pay-stack', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'getRedirectToPaystack']);
    Route::get('/subscription/post-payment-pay-stack-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'postPaymentPaystackCallback']);

    //Routes related to pesapal checkout
    Route::get('/subscription/{package_id}/pesapal-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pesapalCallback'])->name('pesapalCallback');

    Route::get('/subscription/{package_id}/pay', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'pay']);
    Route::post('/subscription/{package_id}/dpo/create', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'createDpoCheckout'])->name('subscription.dpo.create');
    Route::post('/subscription/dpo/attempt/{attempt_id}/verify', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'verifyDpoAttempt'])->name('subscription.dpo.verify');
    Route::post('/subscription/{package_id}/confirm', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'confirm'])->name('subscription-confirm');
    Route::get('/all-subscriptions', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'allSubscriptions']);

    Route::get('/subscription/{package_id}/register-pay', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'registerPay'])->name('register-pay');

    Route::resource('/subscription', 'Modules\Superadmin\Http\Controllers\SubscriptionController');

    Route::post('/subscription/{subcription_id}/force-active', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'forceActive'])->name('force-active');
    Route::post('/subscription/myfatoorah', [\App\Http\Controllers\MyFatoorahController::class, 'index'])->name('subscription.myfatoorah');
    Route::get('/myfatoorah-callback', [Modules\Superadmin\Http\Controllers\SubscriptionController::class, 'myfatoorahcallback'])->name('myfatoorah_callback');

});

Route::get('/page/{slug}', [Modules\Superadmin\Http\Controllers\PageController::class, 'showPage'])->name('frontend-pages');
