<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('throttle:60,1')->post(
    '/hms/v1/channels/{publicKey}/events',
    [Modules\Hms\Http\Controllers\ChannelWebhookController::class, 'ingest']
)->name('hms.api.channels.events');
