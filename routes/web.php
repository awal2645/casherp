<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountingWorkspaceController;
use App\Http\Controllers\AdditionalBusinessController;
use App\Http\Controllers\AccountReportsController;
use App\Http\Controllers\AccountTypeController;
// use App\Http\Controllers\Auth;
use App\Http\Controllers\BackUpController;
use App\Http\Controllers\BarcodeController;
use App\Http\Controllers\BrandController;
use App\Http\Controllers\BusinessDocumentController;
use App\Http\Controllers\BusinessController;
use App\Http\Controllers\BusinessClosureController;
use App\Http\Controllers\BusinessSwitcherController;
use App\Http\Controllers\BusinessLocationController;
use App\Http\Controllers\BusinessSmtpSettingsController;
use App\Http\Controllers\CashRegisterController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CombinedPurchaseReturnController;
use App\Http\Controllers\CompanyHubController;
use App\Http\Controllers\CompanyHubEventController;
use App\Http\Controllers\CompanyHubResourceController;
use App\Http\Controllers\CommercialWorkspaceController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\CustomerGroupController;
use App\Http\Controllers\DashboardConfiguratorController;
use App\Http\Controllers\DataImportController;
use App\Http\Controllers\DashboardWorkspaceController;
use App\Http\Controllers\DiscountController;
use App\Http\Controllers\DocumentAndNoteController;
use App\Http\Controllers\ExpenseCategoryController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GroupTaxController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ImportOpeningStockController;
use App\Http\Controllers\ImportProductsController;
use App\Http\Controllers\ImportSalesController;
use App\Http\Controllers\InAppNotificationController;
use App\Http\Controllers\Install;
use App\Http\Controllers\InvoiceLayoutController;
use App\Http\Controllers\InvoiceSchemeController;
use App\Http\Controllers\LandingRegistrationController;
use App\Http\Controllers\LabelsController;
use App\Http\Controllers\LedgerDiscountController;
use App\Http\Controllers\LocationSettingsController;
use App\Http\Controllers\ManageUserController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\NotificationTemplateController;
use App\Http\Controllers\OpeningStockController;
use App\Http\Controllers\PrinterController;
use App\Http\Controllers\PremiumModuleController;
use App\Http\Controllers\ProcurementWorkflowController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PropertyManagementController;
use App\Http\Controllers\PropertyAccessController;
use App\Http\Controllers\PropertyViewingController;
use App\Http\Controllers\PublicBusinessDocumentController;
use App\Http\Controllers\PurchaseController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\PurchaseRequisitionController;
use App\Http\Controllers\PurchaseReturnController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\Restaurant;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SalesCommissionAgentController;
use App\Http\Controllers\SalesOrderController;
use App\Http\Controllers\SellController;
use App\Http\Controllers\SellingPriceGroupController;
use App\Http\Controllers\SellPosController;
use App\Http\Controllers\SellReturnController;
use App\Http\Controllers\StockAdjustmentController;
use App\Http\Controllers\StockTransferController;
use App\Http\Controllers\TaxonomyController;
use App\Http\Controllers\TaxRateController;
use App\Http\Controllers\TransactionPaymentController;
use App\Http\Controllers\TypesOfServiceController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VariationTemplateController;
use App\Http\Controllers\WarrantyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

include_once 'install_r.php';

Route::middleware(['setData'])->group(function () {
    Route::get('/', function () {
        return view('welcome');
    });

    /*
     * Public CashERP marketing frontage supplied by the www.casherp.com
     * developer. Pricing remains controller-backed in the native Superadmin
     * module so that the visual page uses the authoritative SaaS package,
     * allowance and premium-module data instead of hard-coded plan values.
     */
    Route::view('/about', 'landing.about')->name('landing.about');
    Route::view('/industries', 'landing.industries')->name('landing.industries');
    Route::view('/partners', 'landing.partners')->name('landing.partners');
    Route::view('/careers', 'landing.careers')->name('landing.careers');
    Route::view('/resources', 'landing.resources')->name('landing.resources');
    Route::get('/get-started', [LandingRegistrationController::class, 'show'])->name('landing.get-started');
    Route::post('/get-started', [LandingRegistrationController::class, 'store'])
        ->middleware('throttle:6,1')
        ->name('landing.get-started.store');
    Route::get('/registration-resume/{intent}', [LandingRegistrationController::class, 'resume'])
        ->middleware(['signed', 'throttle:12,1'])
        ->name('landing.registration.resume');
    Route::get('/registration-reminders/{intent}/stop', [LandingRegistrationController::class, 'stopReminders'])
        ->middleware(['signed', 'throttle:12,1'])
        ->name('landing.registration-reminders.stop');
    Route::view('/contact', 'landing.contact')->name('landing.contact');
    Route::view('/guides', 'landing.guides')->name('landing.guides');

    /*
     * The scaffolded register routes are switched off: this application signs
     * up a business, not a bare user, and nothing links to them.
     */
    Auth::routes(['register' => false]);

    /*
     * Public registration entry point, kept at /register so affiliate referral
     * links of the form /register?ref=CODE work. The code is picked up by the
     * CaptureAffiliateReferral middleware on the way through and stored in a
     * cookie, which is what attribution reads; forwarding the query string
     * carries the rest of it over, notably ?package=.
     */
    Route::get('/register', function () {
        return redirect()->route('business.getRegister', request()->query());
    })->middleware('guest')->name('register');

    //Registration is for guests only, an already logged in user is sent to the home page.
    Route::middleware('guest')->group(function () {
        Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
            ->middleware('throttle:20,1')
            ->name('auth.google.redirect');
        Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
            ->middleware('throttle:20,1')
            ->name('auth.google.callback');
        Route::get('/business/register', [BusinessController::class, 'getRegister'])->name('business.getRegister');
        Route::post('/business/register', [BusinessController::class, 'postRegister'])
            ->middleware('throttle:5,1')
            ->name('business.postRegister');
    });

    Route::post('/business/register/check-username', [BusinessController::class, 'postCheckUsername'])
        ->middleware('throttle:30,1')
        ->name('business.postCheckUsername');
    Route::post('/business/register/check-email', [BusinessController::class, 'postCheckEmail'])
        ->middleware('throttle:30,1')
        ->name('business.postCheckEmail');

    Route::get('/invoice/{token}', [SellPosController::class, 'showInvoice'])
        ->middleware('throttle:60,1')
        ->name('show_invoice');
    Route::get('/quote/{token}', [SellPosController::class, 'showInvoice'])
        ->middleware('throttle:60,1')
        ->name('show_quote');

    Route::get('/d/{token}', [PublicBusinessDocumentController::class, 'show'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:60,1')
        ->name('public.smart-document.show');
    Route::get('/d/{token}/pdf', [PublicBusinessDocumentController::class, 'pdf'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:30,1')
        ->name('public.smart-document.pdf');
    Route::get('/d/{token}/print', [PublicBusinessDocumentController::class, 'print'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:30,1')
        ->name('public.smart-document.print');
    Route::get('/d/{token}/download', [PublicBusinessDocumentController::class, 'download'])
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:20,1')
        ->name('public.smart-document.download');
    Route::get('/invoice/{token}/pdf', [SellPosController::class, 'publicDocumentPdf'])
        ->middleware('throttle:20,1')
        ->name('public.sell-document.pdf');
    Route::get('/quote/{token}/pdf', [SellPosController::class, 'publicDocumentPdf'])
        ->middleware('throttle:20,1')
        ->name('public.quote-document.pdf');

    Route::get('/pay/{token}', [SellPosController::class, 'invoicePayment'])
        ->name('invoice_payment');
    Route::post('/confirm-payment/{id}', [SellPosController::class, 'confirmPayment'])
        ->name('confirm_payment');
});

//Routes for authenticated users only
Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone', 'AdminSidebarMenu', 'CheckUserLogin'])->group(function () {
    Route::get('/commercial', [CommercialWorkspaceController::class, 'shell'])->name('commercial.index');
    Route::get('/commercial/workspace', [CommercialWorkspaceController::class, 'workspace'])->name('commercial.workspace');
    Route::post('/commercial/customers', [CommercialWorkspaceController::class, 'storeCustomer'])->name('commercial.customers.store');
    Route::get('/sales', [CommercialWorkspaceController::class, 'shell'])->name('sales.workspace');
    Route::get('/sales/workspace', [CommercialWorkspaceController::class, 'workspace'])->name('sales.workspace.data');
    Route::post('/sales/customers', [CommercialWorkspaceController::class, 'storeCustomer'])->name('sales.customers.store');
    Route::get('/accounting', [AccountingWorkspaceController::class, 'shell'])->name('accounting.workspace');
    Route::get('/accounting/workspace', [AccountingWorkspaceController::class, 'workspace'])->name('accounting.workspace.data');

    Route::middleware('business.feature:company_hub')->prefix('company-hub')->name('company-hub.')->group(function () {
        Route::get('/', [CompanyHubController::class, 'index'])->name('index');
        Route::post('/posts', [CompanyHubController::class, 'storePost'])->name('posts.store');
        Route::post('/posts/{uuid}/acknowledge', [CompanyHubController::class, 'acknowledge'])->name('posts.acknowledge');
        Route::post('/posts/{uuid}/opened', [CompanyHubController::class, 'markOpened'])->name('posts.opened');
        Route::post('/posts/{uuid}/comments', [CompanyHubController::class, 'storeComment'])->name('comments.store');
        Route::post('/channels', [CompanyHubController::class, 'storeChannel'])->name('channels.store');
        Route::patch('/channels/{uuid}/archive', [CompanyHubController::class, 'archiveChannel'])->name('channels.archive');
        Route::get('/directory', [CompanyHubController::class, 'directory'])->name('directory');
        Route::get('/settings', [CompanyHubController::class, 'settings'])->name('settings');
        Route::put('/settings', [CompanyHubController::class, 'updateSettings'])->name('settings.update');

        Route::get('/resources', [CompanyHubResourceController::class, 'index'])->name('resources.index');
        Route::post('/resources', [CompanyHubResourceController::class, 'store'])->name('resources.store');
        Route::post('/resources/{uuid}/revisions', [CompanyHubResourceController::class, 'revise'])->name('resources.revise');
        Route::get('/resources/{uuid}/download', [CompanyHubResourceController::class, 'download'])->name('resources.download');

        Route::get('/events', [CompanyHubEventController::class, 'index'])->name('events.index');
        Route::post('/events', [CompanyHubEventController::class, 'store'])->name('events.store');
        Route::patch('/events/{uuid}/cancel', [CompanyHubEventController::class, 'cancel'])->name('events.cancel');
    });

    Route::get('pos/payment/{id}', [SellPosController::class, 'edit'])->name('edit-pos-payment');
    Route::get('service-staff-availability', [SellPosController::class, 'showServiceStaffAvailibility']);
    Route::get('pause-resume-service-staff-timer/{user_id}', [SellPosController::class, 'pauseResumeServiceStaffTimer']);
    Route::get('mark-as-available/{user_id}', [SellPosController::class, 'markAsAvailable']);

    Route::middleware('business.feature:procurement')->group(function () {
        Route::resource('purchase-requisition', PurchaseRequisitionController::class);
        Route::get('purchase-requisition/{purchase_requisition}/print', [PurchaseRequisitionController::class, 'printDocument'])
            ->name('purchase-requisition.print');
        Route::get('purchase-requisition/{purchase_requisition}/download', [PurchaseRequisitionController::class, 'downloadDocument'])
            ->name('purchase-requisition.download');
        Route::post('/get-requisition-products', [PurchaseRequisitionController::class, 'getRequisitionProducts'])->name('get-requisition-products');
        Route::get('get-purchase-requisitions/{location_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitions']);
        Route::get('get-purchase-requisition-lines/{purchase_requisition_id}', [PurchaseRequisitionController::class, 'getPurchaseRequisitionLines']);
        Route::get('/procurement/workflows/{transaction}', [ProcurementWorkflowController::class, 'show'])->name('procurement.workflow.show');
        Route::post('/procurement/workflows/{transaction}/quotes', [ProcurementWorkflowController::class, 'storeQuote'])->name('procurement.quotes.store');
        Route::post('/procurement/workflows/{transaction}/quotes/{quote}/select', [ProcurementWorkflowController::class, 'selectQuote'])->name('procurement.quotes.select');
        Route::post('/procurement/workflows/{transaction}/approve', [ProcurementWorkflowController::class, 'approve'])->name('procurement.workflow.approve');
        Route::post('/procurement/workflows/{transaction}/reject', [ProcurementWorkflowController::class, 'reject'])->name('procurement.workflow.reject');
        Route::post('/procurement/workflows/{transaction}/resubmit', [ProcurementWorkflowController::class, 'resubmit'])->name('procurement.workflow.resubmit');
    });

    Route::get('/sign-in-as-user/{id}', [ManageUserController::class, 'signInAsUser'])->name('sign-in-as-user');

    Route::get('/home', [HomeController::class, 'index'])->name('home');
    Route::get('/home/workspace', [DashboardWorkspaceController::class, 'index'])->name('home.workspace');
    Route::put('/home/workspace/preferences', [DashboardWorkspaceController::class, 'updatePreferences'])->name('home.workspace.preferences');
    Route::get('/home/get-totals', [HomeController::class, 'getTotals']);
    Route::get('/home/product-stock-alert', [HomeController::class, 'getProductStockAlert']);
    Route::get('/home/purchase-payment-dues', [HomeController::class, 'getPurchasePaymentDues']);
    Route::get('/home/sales-payment-dues', [HomeController::class, 'getSalesPaymentDues']);
    Route::post('/attach-medias-to-model', [HomeController::class, 'attachMediasToGivenModel'])->name('attach.medias.to.model');
    Route::get('/calendar', [HomeController::class, 'getCalendar'])->name('calendar');
    Route::get('/notifications/in-app', [InAppNotificationController::class, 'index'])->name('notifications.in-app.index');
    Route::get('/notifications/preferences', [InAppNotificationController::class, 'preferences'])->name('notifications.preferences.show');
    Route::put('/notifications/preferences', [InAppNotificationController::class, 'updatePreferences'])->name('notifications.preferences.update');
    Route::get('/notifications/settings', [InAppNotificationController::class, 'settings'])->name('notifications.settings.show');
    Route::put('/notifications/settings', [InAppNotificationController::class, 'updateSettings'])->name('notifications.settings.update');
    Route::patch('/notifications/in-app/read-all', [InAppNotificationController::class, 'markAllRead'])->name('notifications.in-app.read-all');
    Route::patch('/notifications/in-app/{notification}/read', [InAppNotificationController::class, 'markRead'])->name('notifications.in-app.read');

    Route::prefix('data-imports')->name('data-imports.')->group(function () {
        Route::get('/', [DataImportController::class, 'index'])->name('index');
        Route::get('/create', [DataImportController::class, 'create'])->name('create');
        Route::post('/', [DataImportController::class, 'store'])->name('store');
        Route::get('/templates/{dataset}', [DataImportController::class, 'template'])->name('template');
        Route::get('/{data_import}', [DataImportController::class, 'show'])->name('show');
        Route::get('/{data_import}/progress', [DataImportController::class, 'progress'])->name('progress');
        Route::get('/{data_import}/source', [DataImportController::class, 'source'])->name('source');
        Route::get('/{data_import}/errors', [DataImportController::class, 'errors'])->name('errors');
        Route::post('/{data_import}/commit', [DataImportController::class, 'commit'])->name('commit');
        Route::post('/{data_import}/cancel', [DataImportController::class, 'cancel'])->name('cancel');
        Route::post('/{data_import}/rollback', [DataImportController::class, 'rollback'])->name('rollback');
    });

    Route::middleware('business.feature:smart_documents')->group(function () {
        Route::get('/smart-documents/recommendations', [BusinessDocumentController::class, 'recommendations'])
            ->name('smart-documents.recommendations');
        Route::get('/smart-documents/settings', [BusinessDocumentController::class, 'settings'])
            ->name('smart-documents.settings');
        Route::put('/smart-documents/settings', [BusinessDocumentController::class, 'updateSettings'])
            ->name('smart-documents.settings.update');
        Route::get('/smart-documents/{smart_document}/pdf', [BusinessDocumentController::class, 'pdf'])
            ->name('smart-documents.pdf');
        Route::get('/smart-documents/{smart_document}/preview', [BusinessDocumentController::class, 'preview'])
            ->name('smart-documents.preview');
        Route::get('/smart-documents/{smart_document}/print', [BusinessDocumentController::class, 'print'])
            ->name('smart-documents.print');
        Route::get('/smart-documents/{smart_document}/download', [BusinessDocumentController::class, 'download'])
            ->name('smart-documents.download');
        Route::post('/smart-documents/{smart_document}/transition', [BusinessDocumentController::class, 'transition'])
            ->name('smart-documents.transition');
        Route::post('/smart-documents/{smart_document}/convert', [BusinessDocumentController::class, 'convert'])
            ->name('smart-documents.convert');
        Route::post('/smart-documents/{smart_document}/share', [BusinessDocumentController::class, 'share'])
            ->name('smart-documents.share');
        Route::delete('/smart-documents/{smart_document}/share', [BusinessDocumentController::class, 'revokeShare'])
            ->name('smart-documents.share.revoke');
        Route::post('/smart-documents/{smart_document}/payments', [BusinessDocumentController::class, 'recordPayment'])
            ->name('smart-documents.payments.store');
        Route::post('/smart-documents/{smart_document}/payments/{payment}/reverse', [BusinessDocumentController::class, 'reversePayment'])
            ->name('smart-documents.payments.reverse');
        Route::put('/smart-documents/{smart_document}/security-deposit', [BusinessDocumentController::class, 'updateEventSecurityDeposit'])->name('smart-documents.security-deposit.requirement');
        Route::post('/smart-documents/{smart_document}/security-deposit/receipts', [BusinessDocumentController::class, 'receiptEventSecurityDeposit'])->name('smart-documents.security-deposit.receipt');
        Route::post('/smart-documents/{smart_document}/security-deposit/damages', [BusinessDocumentController::class, 'damageEventSecurityDeposit'])->name('smart-documents.security-deposit.damage');
        Route::post('/smart-documents/{smart_document}/security-deposit/refunds', [BusinessDocumentController::class, 'refundEventSecurityDeposit'])->name('smart-documents.security-deposit.refund');
        Route::post('/smart-document-security-deposit-refunds/{entry}/approve', [BusinessDocumentController::class, 'approveEventSecurityDepositRefund'])->name('smart-documents.security-deposit.refunds.approve');
        Route::post('/smart-document-security-deposit-refunds/{entry}/pay', [BusinessDocumentController::class, 'payEventSecurityDepositRefund'])->name('smart-documents.security-deposit.refunds.pay');
        Route::post('/smart-document-security-deposit-entries/{entry}/void', [BusinessDocumentController::class, 'voidEventSecurityDepositEntry'])->name('smart-documents.security-deposit.entries.void');
        Route::post('/smart-documents/{smart_document}/security-deposit/waive', [BusinessDocumentController::class, 'waiveEventSecurityDeposit'])->name('smart-documents.security-deposit.waive');
        Route::resource('smart-documents', BusinessDocumentController::class)
            ->parameters(['smart-documents' => 'smart_document'])
            ->except(['destroy']);
    });

    Route::middleware('business.feature:property_management')->prefix('property-management')->name('property.')->group(function () {
        Route::get('/', [PropertyManagementController::class, 'index'])->name('index');
        Route::get('/access', [PropertyAccessController::class, 'index'])->name('access.index');
        Route::post('/access', [PropertyAccessController::class, 'store'])->name('access.store');
        Route::delete('/access/{grant}', [PropertyAccessController::class, 'destroy'])->name('access.destroy');
        Route::put('/dashboard-preferences', [PropertyManagementController::class, 'updateDashboardPreferences'])->name('dashboard.preferences.update');
        Route::delete('/dashboard-preferences', [PropertyManagementController::class, 'resetDashboardPreferences'])->name('dashboard.preferences.reset');
        Route::get('/properties/create', [PropertyManagementController::class, 'createProperty'])->name('properties.create');
        Route::post('/properties', [PropertyManagementController::class, 'storeProperty'])->name('properties.store');
        Route::get('/units', [PropertyManagementController::class, 'units'])->name('units.index');
        Route::get('/properties/{property}/units/create', [PropertyManagementController::class, 'createUnit'])->name('units.create');
        Route::post('/properties/{property}/units', [PropertyManagementController::class, 'storeUnit'])->name('units.store');
        Route::get('/units/{unit}/edit', [PropertyManagementController::class, 'editUnit'])->name('units.edit');
        Route::put('/units/{unit}', [PropertyManagementController::class, 'updateUnit'])->name('units.update');
        Route::get('/leases/create', [PropertyManagementController::class, 'createLease'])->name('leases.create');
        Route::post('/leases', [PropertyManagementController::class, 'storeLease'])->name('leases.store');
        Route::post('/leases/{lease}/end', [PropertyManagementController::class, 'endLease'])->name('leases.end');
        Route::get('/deposits', [\App\Http\Controllers\PropertyDepositController::class, 'index'])->name('deposits.index');
        Route::get('/leases/{lease}/deposits', [\App\Http\Controllers\PropertyDepositController::class, 'show'])->name('deposits.show');
        Route::put('/leases/{lease}/security-deposit', [\App\Http\Controllers\PropertyDepositController::class, 'updateRequirement'])->name('deposits.requirement');
        Route::post('/leases/{lease}/security-deposit/receipts', [\App\Http\Controllers\PropertyDepositController::class, 'receipt'])->name('deposits.receipt');
        Route::post('/leases/{lease}/security-deposit/damages', [\App\Http\Controllers\PropertyDepositController::class, 'damage'])->name('deposits.damage');
        Route::post('/leases/{lease}/security-deposit/refunds', [\App\Http\Controllers\PropertyDepositController::class, 'requestRefund'])->name('deposits.refund');
        Route::post('/security-deposit-refunds/{entry}/approve', [\App\Http\Controllers\PropertyDepositController::class, 'approveRefund'])->name('deposits.refunds.approve');
        Route::post('/security-deposit-refunds/{entry}/pay', [\App\Http\Controllers\PropertyDepositController::class, 'payRefund'])->name('deposits.refunds.pay');
        Route::post('/security-deposit-entries/{entry}/void', [\App\Http\Controllers\PropertyDepositController::class, 'voidEntry'])->name('deposits.entries.void');
        Route::post('/leases/{lease}/security-deposit/waive', [\App\Http\Controllers\PropertyDepositController::class, 'waive'])->name('deposits.waive');
        Route::post('/leases/{lease}/payment-deposits', [\App\Http\Controllers\PropertyDepositController::class, 'paymentDeposit'])->name('deposits.payment');
        Route::get('/viewings', [PropertyViewingController::class, 'index'])->name('viewings.index');
        Route::post('/viewings', [PropertyViewingController::class, 'store'])->name('viewings.store');
        Route::post('/viewings/{viewing}/decision', [PropertyViewingController::class, 'decide'])->name('viewings.decide');
        Route::get('/rents', [PropertyManagementController::class, 'rents'])->name('rents');
        Route::post('/rents/generate', [PropertyManagementController::class, 'generateRents'])->name('rents.generate');
        Route::get('/rents/{due}/payment', [PropertyManagementController::class, 'createPayment'])->name('rents.payment.create');
        Route::post('/rents/{due}/payment', [PropertyManagementController::class, 'storePayment'])->name('rents.payment.store');
        Route::get('/maintenance', [PropertyManagementController::class, 'maintenance'])->name('maintenance');
        Route::get('/maintenance/create', [PropertyManagementController::class, 'createMaintenance'])->name('maintenance.create');
        Route::post('/maintenance', [PropertyManagementController::class, 'storeMaintenance'])->name('maintenance.store');
        Route::get('/maintenance/{ticket}/complete', [PropertyManagementController::class, 'completeMaintenance'])->name('maintenance.complete');
        Route::post('/maintenance/{ticket}/complete', [PropertyManagementController::class, 'storeMaintenanceCompletion'])->name('maintenance.complete.store');
        Route::get('/accounting-settings', [PropertyManagementController::class, 'accountingSettings'])->name('accounting.settings');
        Route::post('/accounting-settings', [PropertyManagementController::class, 'saveAccountingSettings'])->name('accounting.settings.save');
        Route::get('/reports', [PropertyManagementController::class, 'reports'])->name('reports');
        Route::post('/accounting-postings/{posting}/reverse', [PropertyManagementController::class, 'reversePosting'])->name('accounting.postings.reverse');
    });

    Route::get('/business/smtp-settings', [BusinessSmtpSettingsController::class, 'show'])
        ->name('business.smtp-settings.show');
    Route::put('/business/smtp-settings', [BusinessSmtpSettingsController::class, 'update'])
        ->middleware('throttle:20,1')
        ->name('business.smtp-settings.update');
    Route::post('/business/smtp-settings/test', [BusinessSmtpSettingsController::class, 'test'])
        ->middleware('throttle:5,10')
        ->name('business.smtp-settings.test');
    Route::post('/test-email', [BusinessSmtpSettingsController::class, 'test'])
        ->middleware('throttle:5,10')
        ->name('business.smtp-settings.legacy-test');
    Route::post('/test-sms', [BusinessController::class, 'testSmsConfiguration']);
    Route::get('/business/settings', [BusinessController::class, 'getBusinessSettings'])->name('business.getBusinessSettings');
    Route::post('/business/update', [BusinessController::class, 'postBusinessSettings'])->name('business.postBusinessSettings');
    Route::post('/business/switch', [BusinessSwitcherController::class, 'switch'])->name('business.switch');
    Route::get('/business/additional/create', [AdditionalBusinessController::class, 'create'])->name('business.additional.create');
    Route::post('/business/additional', [AdditionalBusinessController::class, 'store'])->name('business.additional.store');
    Route::get('/business/account-closure', [BusinessClosureController::class, 'show'])->name('business.account-closure.show');
    Route::post('/business/account-closure', [BusinessClosureController::class, 'schedule'])->middleware('throttle:3,10')->name('business.account-closure.schedule');
    Route::delete('/business/account-closure', [BusinessClosureController::class, 'cancel'])->middleware('throttle:5,10')->name('business.account-closure.cancel');
    Route::get('/premium-modules', [PremiumModuleController::class, 'index'])->name('premium-modules.index');
    Route::post('/premium-modules/orders', [PremiumModuleController::class, 'store'])->middleware('throttle:10,10')->name('premium-modules.orders.store');
    Route::delete('/premium-modules/orders/{order}', [PremiumModuleController::class, 'cancel'])->name('premium-modules.orders.cancel');
    Route::get('/user/profile', [UserController::class, 'getProfile'])->name('user.getProfile');
    Route::post('/user/update', [UserController::class, 'updateProfile'])->name('user.updateProfile');
    Route::post('/user/update-password', [UserController::class, 'updatePassword'])->name('user.updatePassword');

    Route::resource('brands', BrandController::class);

    Route::resource('tax-rates', TaxRateController::class);

    Route::resource('units', UnitController::class);

    Route::resource('ledger-discount', LedgerDiscountController::class)->only('edit', 'destroy', 'store', 'update');

    Route::post('check-mobile', [ContactController::class, 'checkMobile']);
    Route::get('/get-contact-due/{contact_id}', [ContactController::class, 'getContactDue']);
    Route::get('/contacts/payments/{contact_id}', [ContactController::class, 'getContactPayments']);
    Route::get('/contacts/map', [ContactController::class, 'contactMap']);
    Route::get('/contacts/update-status/{id}', [ContactController::class, 'updateStatus']);
    Route::get('/contacts/stock-report/{supplier_id}', [ContactController::class, 'getSupplierStockReport']);
    Route::get('/contacts/ledger', [ContactController::class, 'getLedger']);
    Route::post('/contacts/send-ledger', [ContactController::class, 'sendLedger']);
    Route::get('/contacts/import', [ContactController::class, 'getImportContacts'])->name('contacts.import');
    Route::post('/contacts/import', [ContactController::class, 'postImportContacts']);
    Route::post('/contacts/check-contacts-id', [ContactController::class, 'checkContactId']);

    Route::post('/contacts/check-tax-number', [ContactController::class, 'checkTaxNumber']);

    Route::get('/contacts/customers', [ContactController::class, 'getCustomers']);
    Route::resource('contacts', ContactController::class);

    Route::get('taxonomies-ajax-index-page', [TaxonomyController::class, 'getTaxonomyIndexPage']);
    Route::resource('taxonomies', TaxonomyController::class);

    Route::resource('variation-templates', VariationTemplateController::class);

    Route::get('/products/download-excel', [ProductController::class, 'downloadExcel']);

    Route::get('/products/stock-history/{id}', [ProductController::class, 'productStockHistory']);
    Route::get('/delete-media/{media_id}', [ProductController::class, 'deleteMedia']);
    Route::post('/products/mass-deactivate', [ProductController::class, 'massDeactivate']);
    Route::get('/products/activate/{id}', [ProductController::class, 'activate']);
    Route::get('/products/view-product-group-price/{id}', [ProductController::class, 'viewGroupPrice']);
    Route::get('/products/add-selling-prices/{id}', [ProductController::class, 'addSellingPrices']);
    Route::post('/products/save-selling-prices', [ProductController::class, 'saveSellingPrices']);
    Route::post('/products/mass-delete', [ProductController::class, 'massDestroy']);
    Route::get('/products/view/{id}', [ProductController::class, 'view']);
    Route::get('/products/list', [ProductController::class, 'getProducts']);
    Route::get('/products/list-no-variation', [ProductController::class, 'getProductsWithoutVariations']);
    Route::post('/products/bulk-edit', [ProductController::class, 'bulkEdit']);
    Route::post('/products/bulk-update', [ProductController::class, 'bulkUpdate']);
    Route::post('/products/bulk-update-location', [ProductController::class, 'updateProductLocation']);
    Route::get('/products/get-product-to-edit/{product_id}', [ProductController::class, 'getProductToEdit']);

    Route::post('/products/get_sub_categories', [ProductController::class, 'getSubCategories']);
    Route::get('/products/get_sub_units', [ProductController::class, 'getSubUnits']);
    Route::post('/products/product_form_part', [ProductController::class, 'getProductVariationFormPart']);
    Route::post('/products/get_product_variation_row', [ProductController::class, 'getProductVariationRow']);
    Route::post('/products/get_variation_template', [ProductController::class, 'getVariationTemplate']);
    Route::get('/products/get_variation_value_row', [ProductController::class, 'getVariationValueRow']);
    Route::post('/products/check_product_sku', [ProductController::class, 'checkProductSku']);
    Route::post('/products/check_product_name', [ProductController::class, 'checkProductName']);
    Route::post('/products/validate_variation_skus', [ProductController::class, 'validateVaritionSkus']); //validates multiple skus at once
    Route::get('/products/quick_add', [ProductController::class, 'quickAdd']);
    Route::post('/products/save_quick_product', [ProductController::class, 'saveQuickProduct']);
    Route::get('/products/get-combo-product-entry-row', [ProductController::class, 'getComboProductEntryRow']);
    Route::post('/products/toggle-woocommerce-sync', [ProductController::class, 'toggleWooCommerceSync']);

    Route::resource('products', ProductController::class);
    Route::get('/toggle-subscription/{id}', 'SellPosController@toggleRecurringInvoices');
    Route::post('/sells/pos/get-types-of-service-details', 'SellPosController@getTypesOfServiceDetails');
    Route::get('/sells/subscriptions', 'SellPosController@listSubscriptions');
    Route::get('/sells/duplicate/{id}', 'SellController@duplicateSell');
    Route::get('/sells/drafts', 'SellController@getDrafts');
    Route::get('/sells/convert-to-draft/{id}', 'SellPosController@convertToInvoice');
    Route::get('/sells/convert-to-proforma/{id}', 'SellPosController@convertToProforma');
    Route::get('/sells/quotations', 'SellController@getQuotations');
    Route::get('/sells/draft-dt', 'SellController@getDraftDatables');
    Route::resource('sells', 'SellController')->except(['show']);
    Route::get('/sells/copy-quotation/{id}', [SellPosController::class, 'copyQuotation']);

    Route::post('/import-purchase-products', [PurchaseController::class, 'importPurchaseProducts']);
    Route::post('/purchases/update-status', [PurchaseController::class, 'updateStatus']);
    Route::get('/purchases/get_products', [PurchaseController::class, 'getProducts']);
    Route::get('/purchases/get_suppliers', [PurchaseController::class, 'getSuppliers']);
    Route::post('/purchases/get_purchase_entry_row', [PurchaseController::class, 'getPurchaseEntryRow']);
    Route::post('/purchases/check_ref_number', [PurchaseController::class, 'checkRefNumber']);
    Route::resource('purchases', PurchaseController::class)->except(['show']);

    Route::get('/toggle-subscription/{id}', [SellPosController::class, 'toggleRecurringInvoices']);
    Route::post('/sells/pos/get-types-of-service-details', [SellPosController::class, 'getTypesOfServiceDetails']);
    Route::get('/sells/subscriptions', [SellPosController::class, 'listSubscriptions']);
    Route::get('/sells/duplicate/{id}', [SellController::class, 'duplicateSell']);
    Route::get('/sells/drafts', [SellController::class, 'getDrafts']);
    Route::get('/sells/convert-to-draft/{id}', [SellPosController::class, 'convertToInvoice']);
    Route::get('/sells/convert-to-proforma/{id}', [SellPosController::class, 'convertToProforma']);
    Route::get('/sells/quotations', [SellController::class, 'getQuotations']);
    Route::get('/sells/draft-dt', [SellController::class, 'getDraftDatables']);
    Route::resource('sells', SellController::class)->except(['show']);

    Route::get('/import-sales', [ImportSalesController::class, 'index']);
    Route::post('/import-sales/preview', [ImportSalesController::class, 'preview']);
    Route::post('/import-sales', [ImportSalesController::class, 'import']);
    Route::get('/revert-sale-import/{batch}', [ImportSalesController::class, 'revertSaleImport']);

    Route::get('/sells/pos/get_product_row/{variation_id}/{location_id}', [SellPosController::class, 'getProductRow']);
    Route::post('/sells/pos/get_payment_row', [SellPosController::class, 'getPaymentRow']);
    Route::post('/sells/pos/get-reward-details', [SellPosController::class, 'getRewardDetails']);
    Route::get('/sells/pos/get-recent-transactions', [SellPosController::class, 'getRecentTransactions']);
    Route::get('/sells/pos/get-product-suggestion', [SellPosController::class, 'getProductSuggestion']);
    Route::get('/sells/pos/get-featured-products/{location_id}', [SellPosController::class, 'getFeaturedProducts']);
    Route::get('/reset-mapping', [SellController::class, 'resetMapping']);
    // pos display screen route
    Route::get('/customer-display', [SellPosController::class, 'posDisplay'])->name('pos_display');

    Route::get('/pos/variations/bulk', [\App\Http\Controllers\ProductController::class, 'getVariationDetailsBulk']);
    // end pos display screen route
    Route::resource('pos', SellPosController::class);

    Route::resource('roles', RoleController::class);

    Route::resource('users', ManageUserController::class);

    Route::resource('group-taxes', GroupTaxController::class);

    Route::get('/barcodes/set_default/{id}', [BarcodeController::class, 'setDefault']);
    Route::resource('barcodes', BarcodeController::class);

    //Invoice schemes..
    Route::get('/invoice-schemes/set_default/{id}', [InvoiceSchemeController::class, 'setDefault']);
    Route::resource('invoice-schemes', InvoiceSchemeController::class);

    //Print Labels
    Route::get('/labels/show', [LabelsController::class, 'show']);
    Route::get('/labels/add-product-row', [LabelsController::class, 'addProductRow']);
    Route::get('/labels/preview', [LabelsController::class, 'preview']);
    Route::get('/labels/print-pdf', [LabelsController::class, 'printPdf']);

    //Reports...
    Route::get('/reports/gst-purchase-report', [ReportController::class, 'gstPurchaseReport']);
    Route::get('/reports/gst-sales-report', [ReportController::class, 'gstSalesReport']);
    Route::get('/reports/get-stock-by-sell-price', [ReportController::class, 'getStockBySellingPrice']);
    Route::get('/reports/purchase-report', [ReportController::class, 'purchaseReport']);
    Route::get('/reports/sale-report', [ReportController::class, 'saleReport']);
    Route::get('/reports/service-staff-report', [ReportController::class, 'getServiceStaffReport']);
    Route::get('/reports/service-staff-line-orders', [ReportController::class, 'serviceStaffLineOrders']);
    Route::get('/reports/table-report', [ReportController::class, 'getTableReport']);
    Route::get('/reports/profit-loss', [ReportController::class, 'getProfitLoss']);
    Route::get('/reports/z-report', [ReportController::class, 'getZReport']);
    Route::get('/reports/get-opening-stock', [ReportController::class, 'getOpeningStock']);
    Route::get('/reports/purchase-sell', [ReportController::class, 'getPurchaseSell']);
    Route::get('/reports/customer-supplier', [ReportController::class, 'getCustomerSuppliers']);
    Route::get('/reports/stock-report', [ReportController::class, 'getStockReport']);
    Route::get('/reports/stock-details', [ReportController::class, 'getStockDetails']);
    Route::get('/reports/tax-report', [ReportController::class, 'getTaxReport']);
    Route::get('/reports/tax-details', [ReportController::class, 'getTaxDetails']);
    Route::get('/reports/trending-products', [ReportController::class, 'getTrendingProducts']);
    Route::get('/reports/expense-report', [ReportController::class, 'getExpenseReport']);
    Route::get('/reports/stock-adjustment-report', [ReportController::class, 'getStockAdjustmentReport']);
    Route::get('/reports/register-report', [ReportController::class, 'getRegisterReport']);
    Route::get('/reports/sales-representative-report', [ReportController::class, 'getSalesRepresentativeReport']);
    Route::get('/reports/sales-representative-total-expense', [ReportController::class, 'getSalesRepresentativeTotalExpense']);
    Route::get('/reports/sales-representative-total-sell', [ReportController::class, 'getSalesRepresentativeTotalSell']);
    Route::get('/reports/sales-representative-total-commission', [ReportController::class, 'getSalesRepresentativeTotalCommission']);
    Route::get('/reports/stock-expiry', [ReportController::class, 'getStockExpiryReport']);
    Route::get('/reports/stock-expiry-edit-modal/{purchase_line_id}', [ReportController::class, 'getStockExpiryReportEditModal']);
    Route::post('/reports/stock-expiry-update', [ReportController::class, 'updateStockExpiryReport'])->name('updateStockExpiryReport');
    Route::get('/reports/customer-group', [ReportController::class, 'getCustomerGroup']);
    Route::get('/reports/product-purchase-report', [ReportController::class, 'getproductPurchaseReport']);
    Route::get('/reports/product-sell-grouped-by', [ReportController::class, 'productSellReportBy']);
    Route::get('/reports/product-sell-report', [ReportController::class, 'getproductSellReport']);
    Route::get('/reports/purchase-sale-product', [ReportController::class, 'purchaseSaleProductReport']);
    Route::get('/reports/purchase-sale-product-data', [ReportController::class, 'purchaseSaleProductReportData']);
    Route::get('/reports/product-sell-report-with-purchase', [ReportController::class, 'getproductSellReportWithPurchase']);
    Route::get('/reports/product-sell-grouped-report', [ReportController::class, 'getproductSellGroupedReport']);
    Route::get('/reports/lot-report', [ReportController::class, 'getLotReport']);
    Route::get('/reports/purchase-payment-report', [ReportController::class, 'purchasePaymentReport']);
    Route::get('/reports/sell-payment-report', [ReportController::class, 'sellPaymentReport']);
    Route::get('/reports/payment-by-age-report', [ReportController::class, 'paymentByAgeReport']);
    Route::get('/reports/product-stock-details', [ReportController::class, 'productStockDetails']);
    Route::get('/reports/adjust-product-stock', [ReportController::class, 'adjustProductStock']);
    Route::get('/reports/get-profit/{by?}', [ReportController::class, 'getProfit']);
    Route::get('/reports/items-report', [ReportController::class, 'itemsReport']);
    Route::get('/reports/get-stock-value', [ReportController::class, 'getStockValue']);

    Route::get('business-location/activate-deactivate/{location_id}', [BusinessLocationController::class, 'activateDeactivateLocation']);

    //Business Location Settings...
    Route::prefix('business-location/{location_id}')->name('location.')->group(function () {
        Route::get('settings', [LocationSettingsController::class, 'index'])->name('settings');
        Route::post('settings', [LocationSettingsController::class, 'updateSettings'])->name('settings_update');
    });

    //Business Locations...
    Route::post('business-location/check-location-id', [BusinessLocationController::class, 'checkLocationId']);
    Route::resource('business-location', BusinessLocationController::class);

    //Invoice layouts..
    Route::resource('invoice-layouts', InvoiceLayoutController::class);

    Route::post('get-expense-sub-categories', [ExpenseCategoryController::class, 'getSubCategories']);

    //Expense Categories...
    Route::resource('expense-categories', ExpenseCategoryController::class);

    //Expenses...
    Route::resource('expenses', ExpenseController::class);
    Route::get('import-expense', [ExpenseController::class, 'importExpense']);
    Route::post('store-import-expense', [ExpenseController::class, 'storeExpenseImport']);

    //Transaction payments...
    // Route::get('/payments/opening-balance/{contact_id}', 'TransactionPaymentController@getOpeningBalancePayments');
    Route::get('/payments/show-child-payments/{payment_id}', [TransactionPaymentController::class, 'showChildPayments']);
    Route::get('/payments/view-payment/{payment_id}', [TransactionPaymentController::class, 'viewPayment']);
    Route::get('/payments/add_payment/{transaction_id}', [TransactionPaymentController::class, 'addPayment']);
    Route::get('/payments/pay-contact-due/{contact_id}', [TransactionPaymentController::class, 'getPayContactDue']);
    Route::post('/payments/pay-contact-due', [TransactionPaymentController::class, 'postPayContactDue']);
    Route::resource('payments', TransactionPaymentController::class);

    //Printers...
    Route::resource('printers', PrinterController::class);

    Route::get('/stock-adjustments/remove-expired-stock/{purchase_line_id}', [StockAdjustmentController::class, 'removeExpiredStock']);
    Route::post('/stock-adjustments/get_product_row', [StockAdjustmentController::class, 'getProductRow']);
    Route::resource('stock-adjustments', StockAdjustmentController::class);

    Route::get('/cash-register/register-details', [CashRegisterController::class, 'getRegisterDetails']);
    Route::get('/cash-register/close-register/{id?}', [CashRegisterController::class, 'getCloseRegister']);
    Route::post('/cash-register/close-register', [CashRegisterController::class, 'postCloseRegister']);
    Route::resource('cash-register', CashRegisterController::class);

    //Import products
    Route::get('/import-products', [ImportProductsController::class, 'index']);
    Route::post('/import-products/store', [ImportProductsController::class, 'store']);

    //Sales Commission Agent
    Route::resource('sales-commission-agents', SalesCommissionAgentController::class);

    //Stock Transfer
    Route::get('stock-transfers/print/{id}', [StockTransferController::class, 'printInvoice']);
    Route::post('stock-transfers/update-status/{id}', [StockTransferController::class, 'updateStatus']);
    Route::resource('stock-transfers', StockTransferController::class);

    Route::get('/opening-stock/add/{product_id}', [OpeningStockController::class, 'add']);
    Route::post('/opening-stock/save', [OpeningStockController::class, 'save']);

    //Customer Groups
    Route::resource('customer-group', CustomerGroupController::class);

    //Import opening stock
    Route::get('/import-opening-stock', [ImportOpeningStockController::class, 'index']);
    Route::post('/import-opening-stock/store', [ImportOpeningStockController::class, 'store']);

    //Sell return
    Route::get('validate-invoice-to-return/{invoice_no}', [SellReturnController::class, 'validateInvoiceToReturn']);
    // service staff replacement
    Route::get('validate-invoice-to-service-staff-replacement/{invoice_no}', [SellPosController::class, 'validateInvoiceToServiceStaffReplacement']);
    Route::put('change-service-staff/{id}', [SellPosController::class, 'change_service_staff'])->name('change_service_staff');

    Route::resource('sell-return', SellReturnController::class);
    Route::get('sell-return/get-product-row', [SellReturnController::class, 'getProductRow']);
    Route::get('/sell-return/print/{id}', [SellReturnController::class, 'printInvoice']);
    Route::get('/sell-return/add/{id}', [SellReturnController::class, 'add']);

    //Backup
    Route::get('backup/download/{file_name}', [BackUpController::class, 'download']);
    Route::get('backup/{id}/delete', [BackUpController::class, 'delete'])->name('delete_backup');
    Route::resource('backup', BackUpController::class)->only('index', 'create', 'store');

    Route::get('selling-price-group/activate-deactivate/{id}', [SellingPriceGroupController::class, 'activateDeactivate']);
    Route::get('update-product-price', [SellingPriceGroupController::class, 'updateProductPrice'])->name('update-product-price');
    Route::get('export-product-price', [SellingPriceGroupController::class, 'export']);
    Route::post('import-product-price', [SellingPriceGroupController::class, 'import']);

    Route::resource('selling-price-group', SellingPriceGroupController::class);

    Route::resource('notification-templates', NotificationTemplateController::class)->only(['index', 'store']);
    Route::get('notification/get-template/{transaction_id}/{template_for}', [NotificationController::class, 'getTemplate']);
    Route::post('notification/send', [NotificationController::class, 'send']);

    Route::post('/purchase-return/update', [CombinedPurchaseReturnController::class, 'update']);
    Route::get('/purchase-return/edit/{id}', [CombinedPurchaseReturnController::class, 'edit']);
    Route::post('/purchase-return/save', [CombinedPurchaseReturnController::class, 'save']);
    Route::post('/purchase-return/get_product_row', [CombinedPurchaseReturnController::class, 'getProductRow']);
    Route::get('/purchase-return/create', [CombinedPurchaseReturnController::class, 'create']);
    Route::get('/purchase-return/add/{id}', [PurchaseReturnController::class, 'add']);
    Route::resource('/purchase-return', PurchaseReturnController::class)->except('create');

    Route::get('/discount/activate/{id}', [DiscountController::class, 'activate']);
    Route::post('/discount/mass-deactivate', [DiscountController::class, 'massDeactivate']);
    Route::resource('discount', DiscountController::class);

    Route::prefix('account')->group(function () {
        Route::resource('/account', AccountController::class);
        Route::get('/fund-transfer/{id}', [AccountController::class, 'getFundTransfer']);
        Route::post('/fund-transfer', [AccountController::class, 'postFundTransfer']);
        Route::get('/deposit/{id}', [AccountController::class, 'getDeposit']);
        Route::post('/deposit', [AccountController::class, 'postDeposit']);
        Route::get('/close/{id}', [AccountController::class, 'close']);
        Route::get('/activate/{id}', [AccountController::class, 'activate']);
        Route::get('/delete-account-transaction/{id}', [AccountController::class, 'destroyAccountTransaction']);
        Route::get('/edit-account-transaction/{id}', [AccountController::class, 'editAccountTransaction']);
        Route::post('/update-account-transaction/{id}', [AccountController::class, 'updateAccountTransaction']);
        Route::get('/get-account-balance/{id}', [AccountController::class, 'getAccountBalance']);
        Route::get('/balance-sheet', [AccountReportsController::class, 'balanceSheet']);
        Route::get('/trial-balance', [AccountReportsController::class, 'trialBalance']);
        Route::get('/payment-account-report', [AccountReportsController::class, 'paymentAccountReport']);
        Route::get('/link-account/{id}', [AccountReportsController::class, 'getLinkAccount']);
        Route::post('/link-account', [AccountReportsController::class, 'postLinkAccount']);
        Route::get('/cash-flow', [AccountController::class, 'cashFlow']);
    });

    Route::resource('account-types', AccountTypeController::class);

    //Restaurant module
    Route::middleware('business.feature:restaurant_operations')->prefix('modules')->group(function () {
        Route::resource('tables', Restaurant\TableController::class);
        Route::resource('modifiers', Restaurant\ModifierSetsController::class);

        //Map modifier to products
        Route::get('/product-modifiers/{id}/edit', [Restaurant\ProductModifierSetController::class, 'edit']);
        Route::post('/product-modifiers/{id}/update', [Restaurant\ProductModifierSetController::class, 'update']);
        Route::get('/product-modifiers/product-row/{product_id}', [Restaurant\ProductModifierSetController::class, 'product_row']);

        Route::get('/add-selected-modifiers', [Restaurant\ProductModifierSetController::class, 'add_selected_modifiers']);

        Route::get('/kitchen', [Restaurant\KitchenController::class, 'index']);
        Route::post('/kitchen/mark-as-cooked/{id}', [Restaurant\KitchenController::class, 'markAsCooked']);
        Route::post('/kitchen/mark-line-as-cooked/{id}', [Restaurant\KitchenController::class, 'markLineAsCooked']);
        Route::post('/refresh-orders-list', [Restaurant\KitchenController::class, 'refreshOrdersList']);
        Route::post('/refresh-line-orders-list', [Restaurant\KitchenController::class, 'refreshLineOrdersList']);

        Route::get('/orders', [Restaurant\OrderController::class, 'index']);
        Route::post('/orders/mark-as-served/{id}', [Restaurant\OrderController::class, 'markAsServed']);
        Route::get('/data/get-pos-details', [Restaurant\DataController::class, 'getPosDetails']);
        Route::get('/data/check-staff-pin', [Restaurant\DataController::class, 'checkStaffPin']);
        Route::post('/orders/mark-line-order-as-served/{id}', [Restaurant\OrderController::class, 'markLineOrderAsServed']);
        Route::get('/print-line-order', [Restaurant\OrderController::class, 'printLineOrder']);
    });

    Route::middleware('business.feature:restaurant_operations')->group(function () {
        Route::get('bookings/get-todays-bookings', [Restaurant\BookingController::class, 'getTodaysBookings']);
        Route::resource('bookings', Restaurant\BookingController::class);

        Route::prefix('restaurant-operations')->name('restaurant-operations.')->group(function () {
            Route::get('/', Restaurant\OperationsDashboardController::class)->name('dashboard');
            Route::get('/stations', [Restaurant\KitchenStationController::class, 'index'])->name('stations.index');
            Route::post('/stations', [Restaurant\KitchenStationController::class, 'store'])->name('stations.store');
            Route::put('/stations/{station}', [Restaurant\KitchenStationController::class, 'update'])->name('stations.update');
            Route::delete('/stations/{station}', [Restaurant\KitchenStationController::class, 'destroy'])->name('stations.destroy');
            Route::post('/stations/{station}/products', [Restaurant\KitchenStationController::class, 'mapProduct'])->name('stations.products.store');

            Route::get('/kitchen-board', [Restaurant\KitchenTicketController::class, 'index'])->name('kitchen-board');
            Route::patch('/kitchen-tickets/{ticket}/status', [Restaurant\KitchenTicketController::class, 'transition'])->name('kitchen-tickets.transition');

            Route::get('/orders', [Restaurant\FulfilmentController::class, 'index'])->name('fulfilments.index');
            Route::patch('/orders/{fulfilment}', [Restaurant\FulfilmentController::class, 'update'])->name('fulfilments.update');

            Route::get('/recipes', [Restaurant\RecipeController::class, 'index'])->name('recipes.index');
            Route::post('/recipes', [Restaurant\RecipeController::class, 'store'])->name('recipes.store');
            Route::delete('/recipes/{recipe}', [Restaurant\RecipeController::class, 'destroy'])->name('recipes.destroy');
            Route::get('/ingredient-ledger', [Restaurant\RestaurantInventoryController::class, 'index'])->name('inventory.index');
            Route::post('/ingredient-ledger/waste', [Restaurant\RestaurantInventoryController::class, 'waste'])->name('inventory.waste');

            Route::get('/waiter-requests', [Restaurant\WaiterRequestController::class, 'index'])->name('waiter-requests.index');
            Route::post('/waiter-requests', [Restaurant\WaiterRequestController::class, 'store'])->name('waiter-requests.store');
            Route::patch('/waiter-requests/{waiterRequest}', [Restaurant\WaiterRequestController::class, 'update'])->name('waiter-requests.update');

            Route::get('/register-control', [Restaurant\RegisterControlController::class, 'index'])->name('registers.index');
            Route::post('/register-control/movements', [Restaurant\RegisterControlController::class, 'movement'])->name('registers.movements.store');
            Route::post('/register-control/{register}/reconcile', [Restaurant\RegisterControlController::class, 'submit'])->name('registers.reconcile');
            Route::patch('/register-control/reconciliations/{reconciliation}', [Restaurant\RegisterControlController::class, 'review'])->name('registers.review');
            Route::get('/register-control/export', [Restaurant\RegisterControlController::class, 'export'])->name('registers.export');

            Route::get('/settings', [Restaurant\RestaurantSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('/settings', [Restaurant\RestaurantSettingsController::class, 'update'])->name('settings.update');
        });
    });

    Route::resource('types-of-service', TypesOfServiceController::class);
    Route::get('sells/edit-shipping/{id}', [SellController::class, 'editShipping']);
    Route::put('sells/update-shipping/{id}', [SellController::class, 'updateShipping']);
    Route::get('shipments', [SellController::class, 'shipments']);

    Route::post('upload-module', [Install\ModulesController::class, 'uploadModule']);
    Route::delete('manage-modules/destroy/{module_name}', [Install\ModulesController::class, 'destroy']);
    Route::resource('manage-modules', Install\ModulesController::class)
        ->only(['index', 'update']);
    Route::get('regenerate', [Install\ModulesController::class, 'regenerate']);

    Route::resource('warranties', WarrantyController::class);

    Route::resource('dashboard-configurator', DashboardConfiguratorController::class)
    ->only(['edit', 'update']);

    Route::get('view-media/{model_id}', [SellController::class, 'viewMedia']);

    //common controller for document & note
    Route::get('get-document-note-page', [DocumentAndNoteController::class, 'getDocAndNoteIndexPage']);
    Route::post('post-document-upload', [DocumentAndNoteController::class, 'postMedia']);
    Route::resource('note-documents', DocumentAndNoteController::class);
    Route::middleware('business.feature:procurement')->group(function () {
        Route::resource('purchase-order', PurchaseOrderController::class);
        Route::get('get-purchase-orders/{contact_id}', [PurchaseOrderController::class, 'getPurchaseOrders']);
        Route::get('get-purchase-order-lines/{purchase_order_id}', [PurchaseController::class, 'getPurchaseOrderLines']);
        Route::get('edit-purchase-orders/{id}/status', [PurchaseOrderController::class, 'getEditPurchaseOrderStatus']);
        Route::put('update-purchase-orders/{id}/status', [PurchaseOrderController::class, 'postEditPurchaseOrderStatus']);
        Route::get('/download-purchase-order/{id}/pdf', [PurchaseOrderController::class, 'downloadPdf'])->name('purchaseOrder.downloadPdf');
    });
    Route::resource('sales-order', SalesOrderController::class)->only(['index']);
    Route::get('get-sales-orders/{customer_id}', [SalesOrderController::class, 'getSalesOrders']);
    Route::get('get-sales-order-lines', [SellPosController::class, 'getSalesOrderLines']);
    Route::get('edit-sales-orders/{id}/status', [SalesOrderController::class, 'getEditSalesOrderStatus']);
    Route::put('update-sales-orders/{id}/status', [SalesOrderController::class, 'postEditSalesOrderStatus']);
    Route::get('reports/activity-log', [ReportController::class, 'activityLog']);
    Route::get('user-location/{latlng}', [HomeController::class, 'getUserLocation']);
});

// Route::middleware(['EcomApi'])->prefix('api/ecom')->group(function () {
//     Route::get('products/{id?}', [ProductController::class, 'getProductsApi']);
//     Route::get('categories', [CategoryController::class, 'getCategoriesApi']);
//     Route::get('brands', [BrandController::class, 'getBrandsApi']);
//     Route::post('customers', [ContactController::class, 'postCustomersApi']);
//     Route::get('settings', [BusinessController::class, 'getEcomSettings']);
//     Route::get('variations', [ProductController::class, 'getVariationsApi']);
//     Route::post('orders', [SellPosController::class, 'placeOrdersApi']);
// });

//common route
Route::middleware(['auth'])->group(function () {
    Route::get('/logout', [App\Http\Controllers\Auth\LoginController::class, 'logout'])->name('logout');
});

Route::middleware(['setData', 'auth', 'SetSessionData', 'language', 'timezone'])->group(function () {
    Route::get('/load-more-notifications', [HomeController::class, 'loadMoreNotifications']);
    Route::get('/get-total-unread', [HomeController::class, 'getTotalUnreadNotifications']);
    Route::get('/purchases/print/{id}', [PurchaseController::class, 'printInvoice']);
    Route::get('/purchases/{id}', [PurchaseController::class, 'show']);
    Route::get('/sells/{id}', [SellController::class, 'show']);
    Route::get('/sells/{transaction_id}/document/preview', [SellPosController::class, 'previewDocument'])->name('sell.document.preview');
    Route::get('/sells/{transaction_id}/document/print', [SellPosController::class, 'printDocument'])->name('sell.document.print');
    Route::get('/sells/{transaction_id}/print', [SellPosController::class, 'printInvoice'])->name('sell.printInvoice');
    Route::get('/download-sells/{transaction_id}/pdf', [SellPosController::class, 'downloadPdf'])->name('sell.downloadPdf');
    Route::get('/download-quotation/{id}/pdf', [SellPosController::class, 'downloadQuotationPdf'])
        ->name('quotation.downloadPdf');
    Route::get('/download-packing-list/{id}/pdf', [SellPosController::class, 'downloadPackingListPdf'])
        ->name('packing.downloadPdf');
    Route::get('/sells/invoice-url/{id}', [SellPosController::class, 'showInvoiceUrl']);
    Route::get('/show-notification/{id}', [HomeController::class, 'showNotification']);
    Route::post('/sell/check-invoice-number', [SellController::class, 'checkInvoiceNumber']);
});
