<?php

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

Route::middleware(['web', 'throttle:30,1'])->group(function () {
    Route::get('/hotel/{slug}', [Modules\Hms\Http\Controllers\PublicBookingController::class, 'show'])
        ->name('hms.public_booking.show');
    Route::post('/hotel/{slug}', [Modules\Hms\Http\Controllers\PublicBookingController::class, 'store'])
        ->name('hms.public_booking.store');
});

Route::middleware('web', 'auth', 'SetSessionData', 'language', 'AdminSidebarMenu', 'business.feature:hms')->prefix('hms')->group(function () {
    Route::get('dashboard', [Modules\Hms\Http\Controllers\HmsController::class, 'index']);
    Route::resource('/rooms', Modules\Hms\Http\Controllers\RoomController::class);
    Route::delete('/room/{id}/destroy', [Modules\Hms\Http\Controllers\RoomController::class, 'destroy'])
        ->name('hms.delete_room');
    Route::delete('/room-media/{media}/destroy', [Modules\Hms\Http\Controllers\RoomController::class, 'deleteMedia'])
        ->name('hms.room_media.destroy');
    Route::get('/room/pricing', [Modules\Hms\Http\Controllers\RoomController::class, 'pricing'])->name('room_pricing');
    Route::get('/room/spacial-pricing', [Modules\Hms\Http\Controllers\RoomController::class, 'get_spacial_pricing_html'])->name('get_spacial_pricing_html');
    Route::post('/room/pricing', [Modules\Hms\Http\Controllers\RoomController::class, 'post_pricing']);

    // hms extra  route
    Route::resource('/extras', Modules\Hms\Http\Controllers\ExtraController::class);
    Route::delete('/extra/{id}/destroy', [Modules\Hms\Http\Controllers\ExtraController::class, 'destroy'])
        ->name('hms.delete_extra');

    Route::get('settings', [Modules\Hms\Http\Controllers\HmsSettingController::class, 'index']);
    Route::post('settings', [Modules\Hms\Http\Controllers\HmsSettingController::class, 'store']);
    Route::post('settings-print-pdf', [Modules\Hms\Http\Controllers\HmsSettingController::class, 'post_pdf']);


    Route::post('store-email-template', [Modules\Hms\Http\Controllers\HmsSettingController::class, 'store_email_template']);

    Route::get('/front-desk', [Modules\Hms\Http\Controllers\FrontDeskController::class, 'index'])
        ->name('hms.front_desk.index');
    Route::get('/room-status', [Modules\Hms\Http\Controllers\RoomStatusBoardController::class, 'index'])
        ->name('hms.room_status.index');
    Route::post('/bookings/{booking}/occupants', [Modules\Hms\Http\Controllers\BookingGuestController::class, 'store'])
        ->name('hms.booking_guests.store');
    Route::delete('/booking-occupants/{guest}', [Modules\Hms\Http\Controllers\BookingGuestController::class, 'destroy'])
        ->name('hms.booking_guests.destroy');
    Route::post('/booking-lines/{line}/room-moves', [Modules\Hms\Http\Controllers\RoomMoveController::class, 'store'])
        ->name('hms.room_moves.store');
    Route::post('/front-desk/{booking}/check-in', [Modules\Hms\Http\Controllers\FrontDeskController::class, 'checkIn'])
        ->name('hms.front_desk.check_in');
    Route::post('/front-desk/{booking}/check-out', [Modules\Hms\Http\Controllers\FrontDeskController::class, 'checkOut'])
        ->name('hms.front_desk.check_out');
    Route::post('/front-desk/{booking}/cancel', [Modules\Hms\Http\Controllers\FrontDeskController::class, 'cancel'])
        ->name('hms.front_desk.cancel');
    Route::post('/front-desk/{booking}/no-show', [Modules\Hms\Http\Controllers\FrontDeskController::class, 'noShow'])
        ->name('hms.front_desk.no_show');

    Route::get('/properties', [Modules\Hms\Http\Controllers\PropertyController::class, 'index'])->name('hms.properties.index');
    Route::post('/properties', [Modules\Hms\Http\Controllers\PropertyController::class, 'store'])->name('hms.properties.store');
    Route::put('/properties/{property}', [Modules\Hms\Http\Controllers\PropertyController::class, 'update'])->name('hms.properties.update');
    Route::get('/event-venues', [Modules\Hms\Http\Controllers\EventVenueController::class, 'index'])->name('hms.event_venues.index');
    Route::post('/event-venues', [Modules\Hms\Http\Controllers\EventVenueController::class, 'store'])->name('hms.event_venues.store');
    Route::post('/event-venues/{venue}/toggle', [Modules\Hms\Http\Controllers\EventVenueController::class, 'toggle'])->name('hms.event_venues.toggle');
    Route::get('/events', [Modules\Hms\Http\Controllers\EventBookingController::class, 'index'])->name('hms.events.index');
    Route::post('/events', [Modules\Hms\Http\Controllers\EventBookingController::class, 'store'])->name('hms.events.store');
    Route::post('/events/{event}/transition', [Modules\Hms\Http\Controllers\EventBookingController::class, 'transition'])->name('hms.events.transition');
    Route::post('/events/{event}/charges', [Modules\Hms\Http\Controllers\EventBookingController::class, 'addCharge'])->name('hms.events.charges.store');

    Route::get('/rate-plans', [Modules\Hms\Http\Controllers\RatePlanController::class, 'index'])->name('hms.rate_plans.index');
    Route::post('/rate-plans', [Modules\Hms\Http\Controllers\RatePlanController::class, 'store'])->name('hms.rate_plans.store');
    Route::post('/rate-plans/{plan}/toggle', [Modules\Hms\Http\Controllers\RatePlanController::class, 'toggle'])->name('hms.rate_plans.toggle');

    Route::get('/groups', [Modules\Hms\Http\Controllers\GroupBookingController::class, 'index'])->name('hms.groups.index');
    Route::post('/groups', [Modules\Hms\Http\Controllers\GroupBookingController::class, 'store'])->name('hms.groups.store');
    Route::post('/groups/{group}/blocks', [Modules\Hms\Http\Controllers\GroupBookingController::class, 'addBlock'])->name('hms.groups.blocks.store');
    Route::post('/group-blocks/{block}/release', [Modules\Hms\Http\Controllers\GroupBookingController::class, 'releaseBlock'])->name('hms.groups.blocks.release');

    Route::get('/folios', [Modules\Hms\Http\Controllers\FolioController::class, 'index'])->name('hms.folios.index');
    Route::get('/folios/{folio}', [Modules\Hms\Http\Controllers\FolioController::class, 'show'])->name('hms.folios.show');
    Route::post('/folios/{folio}/entries', [Modules\Hms\Http\Controllers\FolioController::class, 'postEntry'])->name('hms.folios.entries.store');
    Route::post('/folio-entries/{entry}/approve-refund', [Modules\Hms\Http\Controllers\FolioController::class, 'approveRefund'])->name('hms.folios.refunds.approve');
    Route::post('/folio-entries/{entry}/void', [Modules\Hms\Http\Controllers\FolioController::class, 'voidEntry'])->name('hms.folios.entries.void');
    Route::post('/folio-entries/{entry}/transfer', [Modules\Hms\Http\Controllers\FolioController::class, 'transferEntry'])->name('hms.folios.entries.transfer');
    Route::post('/folios/{folio}/deposits', [Modules\Hms\Http\Controllers\FolioController::class, 'scheduleDeposit'])->name('hms.folios.deposits.store');
    Route::put('/folios/{folio}/security-deposit', [Modules\Hms\Http\Controllers\FolioController::class, 'updateSecurityDeposit'])->name('hms.folios.security_deposit.requirement');
    Route::post('/folios/{folio}/security-deposit/receipts', [Modules\Hms\Http\Controllers\FolioController::class, 'receiptSecurityDeposit'])->name('hms.folios.security_deposit.receipt');
    Route::post('/folios/{folio}/security-deposit/damages', [Modules\Hms\Http\Controllers\FolioController::class, 'damageSecurityDeposit'])->name('hms.folios.security_deposit.damage');
    Route::post('/folios/{folio}/security-deposit/refunds', [Modules\Hms\Http\Controllers\FolioController::class, 'refundSecurityDeposit'])->name('hms.folios.security_deposit.refund');
    Route::post('/security-deposit-refunds/{entry}/approve', [Modules\Hms\Http\Controllers\FolioController::class, 'approveSecurityDepositRefund'])->name('hms.folios.security_deposit.refunds.approve');
    Route::post('/security-deposit-refunds/{entry}/pay', [Modules\Hms\Http\Controllers\FolioController::class, 'paySecurityDepositRefund'])->name('hms.folios.security_deposit.refunds.pay');
    Route::post('/security-deposit-entries/{entry}/void', [Modules\Hms\Http\Controllers\FolioController::class, 'voidSecurityDepositEntry'])->name('hms.folios.security_deposit.entries.void');
    Route::post('/folios/{folio}/security-deposit/waive', [Modules\Hms\Http\Controllers\FolioController::class, 'waiveSecurityDeposit'])->name('hms.folios.security_deposit.waive');

    Route::get('/night-audit', [Modules\Hms\Http\Controllers\NightAuditController::class, 'index'])->name('hms.night_audit.index');
    Route::post('/night-audit/close', [Modules\Hms\Http\Controllers\NightAuditController::class, 'close'])->name('hms.night_audit.close');
    Route::post('/cashier-shifts/open', [Modules\Hms\Http\Controllers\NightAuditController::class, 'openShift'])->name('hms.cashier_shifts.open');
    Route::post('/cashier-shifts/{shift}/close', [Modules\Hms\Http\Controllers\NightAuditController::class, 'closeShift'])->name('hms.cashier_shifts.close');

    Route::get('/hotel-services', [Modules\Hms\Http\Controllers\HotelOperationsController::class, 'index'])->name('hms.operations.index');
    Route::post('/hotel-services', [Modules\Hms\Http\Controllers\HotelOperationsController::class, 'store'])->name('hms.operations.store');
    Route::post('/hotel-services/{task}/transition', [Modules\Hms\Http\Controllers\HotelOperationsController::class, 'transition'])->name('hms.operations.transition');

    Route::get('/guest-profiles', [Modules\Hms\Http\Controllers\GuestProfileController::class, 'index'])->name('hms.guests.index');
    Route::put('/guest-profiles/{profile}', [Modules\Hms\Http\Controllers\GuestProfileController::class, 'update'])->name('hms.guests.update');
    Route::get('/guest-profiles/{profile}/export', [Modules\Hms\Http\Controllers\GuestProfileController::class, 'export'])->name('hms.guests.export');
    Route::post('/guest-profiles/{profile}/anonymize', [Modules\Hms\Http\Controllers\GuestProfileController::class, 'anonymize'])->name('hms.guests.anonymize');

    Route::get('/guest-messages', [Modules\Hms\Http\Controllers\GuestMessagingController::class, 'index'])->name('hms.guest_messages.index');
    Route::post('/guest-messages', [Modules\Hms\Http\Controllers\GuestMessagingController::class, 'store'])->name('hms.guest_messages.store');
    Route::post('/guest-messages/{rule}/toggle', [Modules\Hms\Http\Controllers\GuestMessagingController::class, 'toggle'])->name('hms.guest_messages.toggle');

    Route::get('/revenue', [Modules\Hms\Http\Controllers\RevenueController::class, 'index'])->name('hms.revenue.index');
    Route::post('/revenue/budget', [Modules\Hms\Http\Controllers\RevenueController::class, 'saveBudget'])->name('hms.revenue.budget');

    Route::get('/channels', [Modules\Hms\Http\Controllers\ChannelManagerController::class, 'index'])->name('hms.channels.index');
    Route::post('/channels', [Modules\Hms\Http\Controllers\ChannelManagerController::class, 'store'])->name('hms.channels.store');
    Route::post('/channels/{connection}/mappings', [Modules\Hms\Http\Controllers\ChannelManagerController::class, 'addMapping'])->name('hms.channels.mappings.store');
    Route::post('/channels/{connection}/rotate-secret', [Modules\Hms\Http\Controllers\ChannelManagerController::class, 'rotateSecret'])->name('hms.channels.rotate');
    Route::post('/channels/{connection}/toggle', [Modules\Hms\Http\Controllers\ChannelManagerController::class, 'toggle'])->name('hms.channels.toggle');
    
    // booking route 
    Route::resource('/bookings', Modules\Hms\Http\Controllers\HmsBookingController::class);

    Route::get('/booking-room-add', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'booking_room_add'])->name('booking_room_add');

    Route::get('/booking-room-edit', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'booking_room_edit'])->name('booking_room_edit');

    Route::get('/get-room-type-by', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_room_type_by'])->name('get_room_type_by');

    Route::get('/get-room-detail', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_room_detail'])->name('get_room_detail');
    Route::get('/print/{id}', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'print'])->name('print');
    Route::get('/print-receipt/{id}', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'printReceipt'])->name('print_receipt');

    // coupons code
    Route::resource('/coupons', Modules\Hms\Http\Controllers\HmsCouponController::class);
    Route::delete('/coupon/{id}/destroy', [Modules\Hms\Http\Controllers\HmsCouponController::class, 'destroy'])
        ->name('hms.delete_coupon');

    Route::get('/get-coupon-discount', [Modules\Hms\Http\Controllers\HmsCouponController::class, 'get_coupon_discount'])->name('get_coupon_discount');

    Route::get('/calendar', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'calendar'])->name('booking_calendar');
    Route::get('/get-check-in-out/{id}', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'get_check_in_out'])->name('get_check_in_out');
    Route::put('/get-check-in-out/{id}', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'post_check_in_out'])->name('post_check_in_out');

    Route::delete('/delete-media/{media_id}', [Modules\Hms\Http\Controllers\HmsBookingController::class, 'deleteMedia']);

    Route::get('/housekeeping', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'index'])
        ->name('hms.housekeeping.index');
    Route::get('/housekeeping/create', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'create'])
        ->name('hms.housekeeping.create');
    Route::post('/housekeeping', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'store'])
        ->name('hms.housekeeping.store');
    Route::get('/housekeeping/{task}', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'show'])
        ->name('hms.housekeeping.show');
    Route::post('/housekeeping/{task}/assign', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'assign'])
        ->name('hms.housekeeping.assign');
    Route::post('/housekeeping/{task}/start', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'start'])
        ->name('hms.housekeeping.start');
    Route::post('/housekeeping/{task}/cleaned', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'markCleaned'])
        ->name('hms.housekeeping.cleaned');
    Route::post('/housekeeping/{task}/inspect', [Modules\Hms\Http\Controllers\HousekeepingController::class, 'inspect'])
        ->name('hms.housekeeping.inspect');


    Route::resource('/unavailables', Modules\Hms\Http\Controllers\UnavailableController::class);
    Route::delete('/unavailable/{id}/destroy', [Modules\Hms\Http\Controllers\UnavailableController::class, 'destroy'])
        ->name('hms.delete_unavailable');


    Route::get('/reports', [Modules\Hms\Http\Controllers\HmsReportController::class, 'index'])
        ->name('reports.index');

    Route::get('install', [\Modules\Hms\Http\Controllers\InstallController::class, 'index']);
    Route::post('install', [\Modules\Hms\Http\Controllers\InstallController::class, 'install']);
    Route::get('install/uninstall', [\Modules\Hms\Http\Controllers\InstallController::class, 'uninstall']);
    Route::get('install/update', [\Modules\Hms\Http\Controllers\InstallController::class, 'update']);

}); 
